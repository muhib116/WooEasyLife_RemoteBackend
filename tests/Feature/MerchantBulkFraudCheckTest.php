<?php

namespace Tests\Feature;

use App\Models\BulkFraudBatchItem;
use App\Models\BulkFraudDailyResult;
use App\Models\CourierHistoryUse;
use App\Models\Role;
use App\Models\User;
use App\Services\FraudCheckService;
use App\Services\MerchantEmployeeService;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantBulkFraudCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'fraud_check.bulk_retry_attempts' => 3,
            'fraud_check.bulk_retry_pause_ms' => 0,
        ]);
    }

    public function test_guest_cannot_open_bulk_fraud_check(): void
    {
        $this->get(route('portal.fraud-check'))->assertRedirect();
    }

    public function test_new_batch_and_saved_batches_are_separate_pages(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('bulk-pages@example.com', '01700000061');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Morning list',
                'phones' => '01711111111',
            ])
            ->assertOk();

        $this->actingAs($merchant)
            ->get(route('portal.fraud-check'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/FraudCheck/Bulk')
                ->has('maxNumbers')
                ->missing('recentBatches'));

        $this->actingAs($merchant)
            ->get(route('portal.fraud-check.batches.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/FraudCheck/Batches')
                ->has('batches.data', 1)
                ->where('batches.data.0.name', 'Morning list'));
    }

    public function test_admin_is_sent_away_from_the_merchant_bulk_check(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-bulk@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('portal.fraud-check'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_duplicates_are_removed_before_any_courier_call(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $response = $this->actingAs($this->merchant())
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Morning',
                'phones' => "01711111111\n+880 1711-111111\n01711111111\n01111111111",
            ]);

        $response->assertOk()
            ->assertJsonPath('batch.duplicates_removed', 2)
            ->assertJsonPath('batch.unique_count', 2)
            ->assertJsonPath('batch.pending', 1);

        $items = $response->json('items');
        $this->assertSame('01711111111', $items[0]['phone']);
        $this->assertSame('pending', $items[0]['status']);
        $this->assertSame('invalid', $items[1]['status']);
    }

    public function test_the_same_invalid_number_in_another_format_is_kept_once(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $response = $this->actingAs($this->merchant())
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Invalid formats',
                'phones' => "01111111111\n01111111111\n+8801111111111\nnot-a-number",
            ]);

        $response->assertOk()
            ->assertJsonPath('batch.duplicates_removed', 2)
            ->assertJsonPath('batch.unique_count', 2)
            ->assertJsonPath('batch.pending', 0)
            ->assertJsonPath('batch.status', 'completed');

        $items = $response->json('items');
        $this->assertSame(['01111111111', 'not-a-number'], array_column($items, 'phone'));
        $this->assertSame(['invalid', 'invalid'], array_column($items, 'status'));
    }

    public function test_a_number_checked_today_is_reused_without_another_courier_call(): void
    {
        $calls = 0;
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function () use (&$calls) {
                $calls++;

                return $this->report(10, 8, 2, '80%');
            });
        });

        $first = $this->merchant('first-bulk@example.com', '01700000021');
        $batch = $this->actingAs($first)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'First',
                'phones' => '01711111111',
            ])->json('batch.id');

        $this->actingAs($first)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.source', 'live')
            ->assertJsonPath('item.received', 8)
            ->assertJsonPath('item.cancelled', 2)
            ->assertJsonPath('item.risk', 'safe')
            ->assertJsonMissing(['name' => 'Hidden Customer']);

        $second = $this->merchant('second-bulk@example.com', '01700000022');
        $reused = $this->actingAs($second)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Second',
                'phones' => '+8801711111111',
            ]);

        $reused->assertOk()
            ->assertJsonPath('batch.pending', 0)
            ->assertJsonPath('batch.status', 'completed')
            ->assertJsonPath('items.0.source', 'cache')
            ->assertJsonPath('items.0.received', 8)
            ->assertJsonPath('items.0.risk', 'safe');

        $this->assertSame(1, $calls);
        $this->assertSame(1, BulkFraudDailyResult::query()->count());
    }

    public function test_a_failed_check_is_retried_and_is_not_saved_for_the_rest_of_the_day(): void
    {
        $calls = 0;
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function () use (&$calls) {
                $calls++;

                if ($calls < 3) {
                    throw new \RuntimeException('courier down');
                }

                return $this->report(5, 1, 4, '20%');
            });
        });

        $merchant = $this->merchant();
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01722222222',
            ])->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.risk', 'high')
            ->assertJsonPath('item.attempts', 3);

        $this->assertSame(3, $calls);
    }

    public function test_a_total_failure_is_not_kept_as_todays_result(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->twice()->andThrow(new \RuntimeException('courier down'));
        });

        config(['fraud_check.bulk_retry_attempts' => 1]);

        $merchant = $this->merchant();
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01744444444',
            ])->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'error')
            ->assertJsonPath('item.attempts', 1);

        $this->assertSame(0, BulkFraudDailyResult::query()->count());

        $again = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01744444444',
            ])->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $again))
            ->assertOk()
            ->assertJsonPath('item.status', 'error')
            ->assertJsonPath('item.source', 'live');
    }

    public function test_partial_courier_counts_are_stored(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn([
                'total_order' => 0,
                'confirmed' => 0,
                'cancel' => 0,
                'success_rate' => 'No order history found!',
                'frauds' => [['name' => 'Hidden Customer']],
                'courier' => [[
                    'title' => 'Paper Fly',
                    'report' => [
                        'total_order' => 0,
                        'confirmed' => 1,
                        'cancel' => 1,
                        'partial' => 2,
                        'success_rate' => '50%',
                        'status' => 'ok',
                        'api_success' => true,
                        'frauds' => [
                            ['name' => 'Hidden Customer', 'details' => 'Refused at the gate'],
                            ['type' => 'fake_order', 'count' => 2],
                        ],
                    ],
                ], [
                    'title' => 'Pathao',
                    'report' => [
                        'status' => 'unavailable',
                        'total_order' => 0,
                        'confirmed' => 0,
                        'cancel' => 0,
                    ],
                ]],
            ]);
        });

        $merchant = $this->merchant();
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Partial',
                'phones' => '01733333333',
            ])->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.received', 1)
            ->assertJsonPath('item.cancelled', 1)
            ->assertJsonPath('item.partial', 2)
            ->assertJsonPath('item.total_orders', 4)
            ->assertJsonPath('item.couriers.0.title', 'Paper Fly')
            ->assertJsonPath('item.couriers.0.partial', 2)
            ->assertJsonPath('item.couriers.0.notes.0', 'Refused at the gate')
            ->assertJsonPath('item.couriers.0.notes.1', 'Fake order × 2')
            ->assertJsonPath('item.couriers.1.available', false)
            ->assertJsonMissing(['name' => 'Hidden Customer']);

        $preview = $this->actingAs($merchant)
            ->getJson(route('portal.fraud-check.batches.preview', $batch));

        $preview->assertOk()
            ->assertJsonPath('headers', [
                'Phone',
                'Delivery Status',
                'Total',
                'Received',
                'Cancelled',
                'Partial',
                'Rate',
                'Risk',
            ])
            ->assertJsonPath('rows.0', [
                '01733333333',
                'Found',
                4,
                1,
                1,
                2,
                '50%',
                'Review',
            ]);

        $csv = $this->actingAs($merchant)
            ->get(route('portal.fraud-check.batches.export', $batch))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('01733333333', $csv);
        $this->assertStringContainsString('50%', $csv);
        $this->assertStringNotContainsString('Refused at the gate', $csv);
        $this->assertStringNotContainsString('Hidden Customer', $csv);
        $this->assertStringNotContainsString('Paper Fly', $csv);
        $this->assertStringNotContainsString('Notes', $csv);
    }

    public function test_a_merchant_cannot_open_another_merchants_batch(): void
    {
        $owner = $this->merchant('owner-bulk@example.com', '01700000031');
        $other = $this->merchant('other-bulk@example.com', '01700000032');

        $batch = $this->actingAs($owner)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01111111111',
            ])->json('batch.id');

        $this->actingAs($other)
            ->getJson(route('portal.fraud-check.batches.show', $batch))
            ->assertNotFound();
    }

    public function test_a_failed_number_can_be_retried_from_its_row(): void
    {
        $calls = 0;

        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->twice()->andReturnUsing(function () use (&$calls) {
                $calls++;

                if ($calls === 1) {
                    throw new \RuntimeException('courier down');
                }

                return $this->report(2, 2, 0, '100%');
            });
        });

        config(['fraud_check.bulk_retry_attempts' => 1]);

        $merchant = $this->merchant('bulk-retry@example.com', '01700000031');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01755555555',
            ])->json('batch.id');

        $failed = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'error')
            ->json('item.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.retry', ['batch' => $batch, 'item' => $failed]))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.risk', 'safe')
            ->assertJsonPath('item.received', 2);

        $this->assertSame(2, $calls);
    }

    public function test_refresh_refetches_one_row_and_replaces_todays_saved_result(): void
    {
        $calls = 0;

        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function ($request, array $payload) use (&$calls) {
                $calls++;

                if (! empty($payload['refresh'])) {
                    return $this->report(4, 1, 3, '25%');
                }

                return $this->report(10, 8, 2, '80%');
            });
        });

        $merchant = $this->merchant('bulk-refresh@example.com', '01700000041');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01766666666',
            ])->json('batch.id');

        $item = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.received', 8)
            ->json('item.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.refresh', ['batch' => $batch, 'item' => $item]))
            ->assertOk()
            ->assertJsonPath('refreshed', true)
            ->assertJsonPath('item.source', 'live')
            ->assertJsonPath('item.received', 1)
            ->assertJsonPath('item.cancelled', 3)
            ->assertJsonPath('item.risk', 'high');

        $this->assertSame(1, BulkFraudDailyResult::query()->where('phone', '01766666666')->value('received'));

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01766666666',
            ])
            ->assertOk()
            ->assertJsonPath('items.0.source', 'cache')
            ->assertJsonPath('items.0.received', 1);

        $this->assertSame(2, $calls);
    }

    public function test_a_failed_refresh_keeps_the_previous_row(): void
    {
        $calls = 0;

        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function ($request, array $payload) use (&$calls) {
                $calls++;

                if (! empty($payload['refresh'])) {
                    throw new \RuntimeException('courier down');
                }

                return $this->report(10, 8, 2, '80%');
            });
        });

        config(['fraud_check.bulk_retry_attempts' => 1]);

        $merchant = $this->merchant('bulk-refresh-fail@example.com', '01700000042');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01777777777',
            ])->json('batch.id');

        $item = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->json('item.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.refresh', ['batch' => $batch, 'item' => $item]))
            ->assertOk()
            ->assertJsonPath('refreshed', false)
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.received', 8);

        $this->assertSame(8, BulkFraudDailyResult::query()->where('phone', '01777777777')->value('received'));
    }

    public function test_courier_history_stays_closed_until_an_admin_enables_it(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'closed-history@example.com',
            'phone' => '01700000071',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $this->actingAs($merchant)
            ->get(route('portal.fraud-check'))
            ->assertRedirect(route('portal.dashboard'));

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01711111111',
            ])
            ->assertForbidden();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'history-admin@example.com',
            'phone' => '01700000072',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('users.courier-history.update', $merchant->id), [
                'courier_history_enabled' => true,
                'courier_history_daily_limit' => 25,
            ])
            ->assertRedirect();

        $merchant->refresh();
        $this->assertTrue($merchant->courier_history_enabled);
        $this->assertSame(25, $merchant->courier_history_daily_limit);

        $this->actingAs($merchant)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.portal.courier_history.enabled', true)
                ->where('auth.portal.courier_history.daily_limit', 25)
                ->where('auth.portal.courier_history.remaining', 25));
    }

    public function test_only_a_successful_check_uses_the_daily_limit(): void
    {
        $calls = 0;

        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function () use (&$calls) {
                $calls++;

                if ($calls === 1) {
                    throw new \RuntimeException('courier down');
                }

                if ($calls === 2) {
                    return $this->report(0, 0, 0, null);
                }

                return $this->report(10, 8, 2, '80%');
            });
        });

        config(['fraud_check.bulk_retry_attempts' => 1]);

        $merchant = $this->merchant('quota-merchant@example.com', '01700000073');
        $merchant->update(['courier_history_daily_limit' => 1]);

        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => "01711111111\n01722222222\n01733333333",
            ])
            ->assertOk()
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'error')
            ->assertJsonPath('quota.used_today', 0)
            ->assertJsonPath('quota.remaining', 1);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'not_found')
            ->assertJsonPath('quota.used_today', 1)
            ->assertJsonPath('quota.remaining', 0);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('limit_reached', true)
            ->assertJsonPath('quota.remaining', 0);

        $this->assertSame(1, CourierHistoryUse::query()->where('user_id', $merchant->id)->count());
        $this->assertSame(2, $calls);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01733333333',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phones');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01722222222',
            ])
            ->assertOk()
            ->assertJsonPath('items.0.source', 'cache')
            ->assertJsonPath('quota.used_today', 1);
    }

    public function test_bangladesh_numbers_keep_the_local_form(): void
    {
        $phones = app(FraudCheckService::class);

        $this->assertSame('01711111111', $phones->normalizePhone('01711111111'));
        $this->assertSame('01711111111', $phones->normalizePhone('8801711111111'));
        $this->assertSame('01711111111', $phones->normalizePhone('+880 1711-111111'));
    }

    public function test_a_merchant_cannot_raise_their_own_courier_history_limit(): void
    {
        $merchant = $this->merchant('self-limit@example.com', '01700000081');
        $merchant->update([
            'courier_history_enabled' => false,
            'courier_history_daily_limit' => 0,
        ]);

        $this->actingAs($merchant)
            ->post(route('users.courier-history.update', $merchant->id), [
                'courier_history_enabled' => true,
                'courier_history_daily_limit' => 50,
            ])
            ->assertRedirect(route('portal.dashboard'));

        $this->actingAs($merchant)
            ->patch(route('portal.profile.update'), [
                'name' => 'Merchant',
                'email' => $merchant->email,
                'courier_history_enabled' => true,
                'courier_history_daily_limit' => 999,
            ])
            ->assertRedirect(route('portal.profile'));

        $merchant->refresh();
        $this->assertFalse($merchant->courier_history_enabled);
        $this->assertSame(0, (int) $merchant->courier_history_daily_limit);
    }

    public function test_another_merchant_cannot_read_or_export_a_batch(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $owner = $this->merchant('owner-batch@example.com', '01700000082');
        $created = $this->actingAs($owner)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Private',
                'phones' => '01711111111',
            ])
            ->assertOk();

        $batch = $created->json('batch.id');
        $item = $created->json('items.0.id');
        $other = $this->merchant('other-batch@example.com', '01700000083');

        $this->actingAs($other)->get(route('portal.fraud-check.batches.show', $batch))->assertNotFound();
        $this->actingAs($other)->getJson(route('portal.fraud-check.batches.preview', $batch))->assertNotFound();
        $this->actingAs($other)->get(route('portal.fraud-check.batches.export', $batch))->assertNotFound();
        $this->actingAs($other)->postJson(route('portal.fraud-check.batches.next', $batch))->assertNotFound();
        $this->actingAs($other)
            ->postJson(route('portal.fraud-check.batches.retry', ['batch' => $batch, 'item' => $item]))
            ->assertNotFound();
    }

    public function test_a_retry_cannot_target_an_item_from_another_batch(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('two-batches@example.com', '01700000084');
        $first = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->json('batch.id');
        $secondItem = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01722222222'])
            ->json('items.0.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.retry', ['batch' => $first, 'item' => $secondItem]))
            ->assertNotFound();
    }

    public function test_invalid_numbers_do_not_use_the_daily_limit(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('invalid-quota@example.com', '01700000085');
        $merchant->update(['courier_history_daily_limit' => 1]);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => "01111111111\nnot-a-number",
            ])
            ->assertOk()
            ->assertJsonPath('batch.status', 'completed')
            ->assertJsonPath('quota.used_today', 0)
            ->assertJsonPath('quota.remaining', 1);

        $this->assertSame(0, CourierHistoryUse::query()->where('user_id', $merchant->id)->count());
    }

    public function test_refreshing_a_counted_number_does_not_use_another_slot(): void
    {
        $calls = 0;
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function () use (&$calls) {
                $calls++;

                return $calls === 1
                    ? $this->report(10, 8, 2, '80%')
                    : $this->report(4, 1, 3, '25%');
            });
        });

        $merchant = $this->merchant('refresh-quota@example.com', '01700000086');
        $merchant->update(['courier_history_daily_limit' => 1]);

        $created = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->assertOk();
        $batch = $created->json('batch.id');

        $item = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('quota.used_today', 1)
            ->json('item.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.refresh', ['batch' => $batch, 'item' => $item]))
            ->assertOk()
            ->assertJsonPath('refreshed', true)
            ->assertJsonPath('item.received', 1)
            ->assertJsonPath('quota.used_today', 1)
            ->assertJsonPath('quota.remaining', 0);

        $this->assertSame(1, CourierHistoryUse::query()->where('user_id', $merchant->id)->count());
    }

    public function test_a_stuck_check_is_picked_up_again(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->report(2, 2, 0, '100%'));
        });

        $merchant = $this->merchant('stuck-check@example.com', '01700000087');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->json('batch.id');

        $item = BulkFraudBatchItem::query()->where('batch_id', $batch)->firstOrFail();
        $item->forceFill(['status' => 'checking'])->save();
        BulkFraudBatchItem::query()->whereKey($item->id)->update([
            'updated_at' => now()->subMinutes(3),
        ]);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.received', 2);
    }

    public function test_a_check_still_in_progress_asks_the_page_to_wait(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('busy-check@example.com', '01700000088');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->json('batch.id');

        BulkFraudBatchItem::query()->where('batch_id', $batch)->update(['status' => 'checking']);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('retry', true)
            ->assertJsonPath('done', false)
            ->assertJsonPath('item', null);

        $this->assertSame('checking', BulkFraudBatchItem::query()->where('batch_id', $batch)->value('status'));
    }

    public function test_csv_download_matches_the_results_table(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('csv-safe@example.com', '01700000089');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => '=1+1',
                'phones' => '=CMD()',
            ])
            ->json('batch.id');

        $csv = $this->actingAs($merchant)
            ->get(route('portal.fraud-check.batches.export', $batch))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('Phone,"Delivery Status",Total,Received,Cancelled,Partial,Rate,Risk', $csv);
        $this->assertStringContainsString("'=CMD()", $csv);
        $this->assertStringNotContainsString('Notes', $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|[\r\n,])=/', $csv);
    }

    public function test_an_extreme_courier_rate_is_stored_as_a_percent(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn([
                'total_order' => 2,
                'confirmed' => -4,
                'cancel' => 2,
                'success_rate' => 900,
                'courier' => [[
                    'title' => 'Stead Fast',
                    'report' => [
                        'confirmed' => -4,
                        'cancel' => 2,
                        'partial' => -3,
                        'success_rate' => 900,
                        'status' => 'ok',
                        'api_success' => true,
                    ],
                ]],
            ]);
        });

        $merchant = $this->merchant('extreme-rate@example.com', '01700000090');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.received', 0)
            ->assertJsonPath('item.cancelled', 2)
            ->assertJsonPath('item.partial', 0)
            ->assertJsonPath('item.success_rate', 100);
    }

    public function test_staff_checks_use_the_owner_daily_limit(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->report(1, 1, 0, '100%'));
        });

        $owner = $this->merchant('staff-owner-quota@example.com', '01700000091');
        $owner->update(['courier_history_daily_limit' => 1]);

        $this->seed(RolePermissionSeeder::class);

        $viewerRole = Role::query()
            ->where('slug', 'merchant-viewer')
            ->where('scope', 'merchant')
            ->firstOrFail();

        app(MerchantEmployeeService::class)->create($owner, [
            'name' => 'Counter Staff',
            'email' => 'counter-staff@example.com',
            'phone' => '01700000092',
            'role_id' => $viewerRole->id,
            'status' => true,
            'grant_portal_access' => true,
            'portal_password' => 'password123',
        ]);

        $staff = User::query()->where('email', 'counter-staff@example.com')->firstOrFail();
        $batch = $this->actingAs($staff)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->assertOk()
            ->json('batch.id');

        $this->actingAs($staff)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('quota.used_today', 1)
            ->assertJsonPath('quota.remaining', 0);

        $this->assertSame(1, CourierHistoryUse::query()->where('user_id', $owner->id)->count());
        $this->assertSame(0, CourierHistoryUse::query()->where('user_id', $staff->id)->count());
    }

    private function merchant(string $email = 'bulk-merchant@example.com', string $phone = '01700000021'): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
            'courier_history_enabled' => true,
            'courier_history_daily_limit' => 1000,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(int $total, int $received, int $cancelled, ?string $rate, ?string $note = null): array
    {
        $frauds = [['name' => 'Hidden Customer']];
        if ($note !== null) {
            $frauds[] = ['details' => $note];
        }

        return [
            'total_order' => $total,
            'confirmed' => $received,
            'cancel' => $cancelled,
            'success_rate' => $rate,
            'frauds' => $frauds,
            'courier' => [[
                'title' => 'Stead Fast',
                'report' => [
                    'total_order' => $total,
                    'confirmed' => $received,
                    'cancel' => $cancelled,
                    'success_rate' => $rate,
                    'status' => 'ok',
                    'api_success' => true,
                    'frauds' => $frauds,
                ],
            ]],
        ];
    }
}
