<?php

namespace Tests\Feature;

use App\Models\BulkFraudBatchItem;
use App\Models\BulkFraudDailyResult;
use App\Models\CourierHistoryUse;
use App\Models\Role;
use App\Models\User;
use App\Services\FraudCheck\BulkFraudScheduler;
use App\Services\FraudCheck\MerchantBulkFraudCheck;
use App\Services\FraudCheckService;
use App\Services\MerchantEmployeeService;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

        // The test cache store can outlive one test, so a paused courier from
        // another test must not skip retries here.
        foreach (['steadfast', 'pathao', 'paperfly', 'redx', 'carrybee'] as $courier) {
            Cache::forget('fraud_check_courier_retry_paused:'.$courier);
            Cache::forget('fraud_check_courier_retry_failures:'.$courier);
        }
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
                ->where('batches.data.0.name', 'Morning list')
                ->where('filters.q', ''));
    }

    public function test_a_merchant_can_search_rename_and_delete_their_batches(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('bulk-manage@example.com', '01700000062');
        $other = $this->merchant('bulk-manage-other@example.com', '01700000063');

        $morning = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Morning list',
                'phones' => '01711111111',
            ])
            ->assertOk()
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Evening list',
                'phones' => '01722222222',
            ])
            ->assertOk();

        $this->actingAs($merchant)
            ->get(route('portal.fraud-check.batches.index', ['q' => 'Morning']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('batches.data', 1)
                ->where('batches.data.0.name', 'Morning list')
                ->where('filters.q', 'Morning'));

        $this->actingAs($merchant)
            ->patch(route('portal.fraud-check.batches.update', $morning), ['name' => '  '])
            ->assertSessionHasErrors('name');

        $this->actingAs($merchant)
            ->patch(route('portal.fraud-check.batches.update', $morning), ['name' => 'Afternoon list'])
            ->assertRedirect();

        $this->assertDatabaseHas('bulk_fraud_batches', [
            'id' => $morning,
            'name' => 'Afternoon list',
        ]);

        $this->actingAs($other)
            ->patch(route('portal.fraud-check.batches.update', $morning), ['name' => 'Stolen'])
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('portal.fraud-check.batches.destroy', $morning))
            ->assertNotFound();

        $this->actingAs($merchant)
            ->delete(route('portal.fraud-check.batches.destroy', $morning))
            ->assertRedirect(route('portal.fraud-check.batches.index'));

        $this->assertDatabaseMissing('bulk_fraud_batches', ['id' => $morning]);
        $this->assertDatabaseMissing('bulk_fraud_batch_items', ['batch_id' => $morning]);
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

    public function test_a_saved_row_with_missing_pathao_is_not_reused_from_todays_cache(): void
    {
        $calls = 0;
        $retried = [];
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls, &$retried): void {
            $mock->shouldReceive('retryCouriers')->once()->andReturnUsing(
                function ($request, array $payload, array $report, array $couriers) use (&$retried): array {
                    $retried = $couriers;

                    return $report;
                },
            );
            $mock->shouldReceive('checkSingle')->andReturnUsing(function () use (&$calls) {
                $calls++;

                if ($calls === 1) {
                    return [
                        'total_order' => 10,
                        'confirmed' => 8,
                        'cancel' => 2,
                        'success_rate' => '80%',
                        'frauds' => [],
                        'courier' => [
                            [
                                'title' => 'Stead Fast',
                                'report' => [
                                    'total_order' => 10,
                                    'confirmed' => 8,
                                    'cancel' => 2,
                                    'status' => 'ok',
                                    'api_success' => true,
                                ],
                            ],
                            [
                                'title' => 'Pathao',
                                'report' => [
                                    'total_order' => 0,
                                    'confirmed' => 0,
                                    'cancel' => 0,
                                    'status' => 'unavailable',
                                    'unavailable' => true,
                                    'message' => 'Pathao returned no delivery data',
                                ],
                            ],
                            [
                                'title' => 'Paperfly',
                                'report' => [
                                    'total_order' => 0,
                                    'confirmed' => 0,
                                    'cancel' => 0,
                                    'status' => 'ok',
                                    'api_success' => true,
                                ],
                            ],
                        ],
                    ];
                }

                return [
                    'total_order' => 12,
                    'confirmed' => 10,
                    'cancel' => 2,
                    'success_rate' => '83%',
                    'frauds' => [],
                    'courier' => [
                        [
                            'title' => 'Stead Fast',
                            'report' => [
                                'total_order' => 10,
                                'confirmed' => 8,
                                'cancel' => 2,
                                'status' => 'ok',
                                'api_success' => true,
                            ],
                        ],
                        [
                            'title' => 'Pathao',
                            'report' => [
                                'total_order' => 2,
                                'confirmed' => 2,
                                'cancel' => 0,
                                'status' => 'ok',
                                'api_success' => true,
                            ],
                        ],
                        [
                            'title' => 'Paperfly',
                            'report' => [
                                'total_order' => 0,
                                'confirmed' => 0,
                                'cancel' => 0,
                                'status' => 'ok',
                                'api_success' => true,
                            ],
                        ],
                    ],
                ];
            });
        });

        config(['fraud_check.bulk_retry_attempts' => 3, 'fraud_check.bulk_retry_pause_ms' => 0]);

        $first = $this->merchant('recheck-one@example.com', '01700000031');
        $batch = $this->actingAs($first)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01733333333',
            ])->json('batch.id');

        $this->actingAs($first)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.source', 'live')
            ->assertJsonPath('item.received', 8)
            ->assertJsonPath('item.risk', 'waiting')
            ->assertJsonPath('item.waiting.pathao.tries', 0);

        $this->assertSame(1, $calls);
        $this->assertSame(['pathao'], $retried);

        $second = $this->merchant('recheck-two@example.com', '01700000032');
        $again = $this->actingAs($second)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01733333333',
            ])
            ->assertOk()
            ->assertJsonPath('items.0.status', 'pending')
            ->json('batch.id');

        $this->actingAs($second)
            ->postJson(route('portal.fraud-check.batches.next', $again))
            ->assertOk()
            ->assertJsonPath('item.source', 'live')
            ->assertJsonPath('item.received', 10)
            ->assertJsonPath('item.risk', 'safe');

        $this->assertSame(2, $calls);

        $third = $this->merchant('recheck-three@example.com', '01700000033');
        $this->actingAs($third)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => '01733333333',
            ])
            ->assertOk()
            ->assertJsonPath('items.0.source', 'cache')
            ->assertJsonPath('items.0.received', 10);
    }

    public function test_only_the_courier_that_did_not_answer_is_asked_again(): void
    {
        $missingPathao = $this->courierReport(['status' => 'unavailable', 'unavailable' => true, 'retryable' => true]);
        $answeredPathao = $this->courierReport(['total_order' => 2, 'confirmed' => 2, 'success_rate' => '100%', 'status' => 'ok', 'api_success' => true]);

        $this->mock(FraudCheckCoordinator::class, function ($mock) use ($missingPathao, $answeredPathao): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->threeCourierReport($missingPathao));
            $mock->shouldReceive('retryCouriers')
                ->once()
                ->withArgs(fn ($request, array $payload, array $report, array $couriers): bool => $couriers === ['pathao'] && $payload['phone'] === '01755555555')
                ->andReturn($this->threeCourierReport($answeredPathao, 12, 10));
        });

        config(['fraud_check.bulk_retry_pause_ms' => 0]);

        $merchant = $this->merchant('only-missing@example.com', '01700000034');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01755555555'])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.received', 10)
            ->assertJsonPath('item.couriers.1.title', 'Pathao')
            ->assertJsonPath('item.couriers.1.received', 2)
            ->assertJsonPath('item.risk', 'safe')
            ->assertJsonPath('item.attempts', 1);
    }

    public function test_a_rate_limited_courier_is_not_asked_again_during_the_same_check(): void
    {
        $limitedPathao = $this->courierReport([
            'status' => 'unavailable',
            'unavailable' => true,
            'rate_limited' => true,
            'retryable' => false,
        ]);

        $this->mock(FraudCheckCoordinator::class, function ($mock) use ($limitedPathao): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->threeCourierReport($limitedPathao));
            $mock->shouldReceive('retryCouriers')->never();
        });

        config(['fraud_check.bulk_retry_pause_ms' => 0]);

        $merchant = $this->merchant('rate-limited@example.com', '01700000035');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01766666666'])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.received', 8)
            ->assertJsonPath('item.couriers.1.available', false)
            ->assertJsonPath('item.couriers.1.reason', 'waiting')
            ->assertJsonPath('item.waiting.pathao.reason', 'busy')
            ->assertJsonPath('item.risk', 'waiting');
    }

    public function test_a_courier_that_keeps_failing_is_not_asked_again_for_the_next_number(): void
    {
        $missingPathao = $this->courierReport(['status' => 'unavailable', 'unavailable' => true, 'retryable' => true]);

        $this->mock(FraudCheckCoordinator::class, function ($mock) use ($missingPathao): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->threeCourierReport($missingPathao));
            $mock->shouldReceive('retryCouriers')->never();
        });

        config(['fraud_check.bulk_retry_pause_ms' => 0, 'fraud_check.courier_retry.pause_after_failures' => 2]);
        FraudCheckService::noteCourierRetry('pathao', false);
        FraudCheckService::noteCourierRetry('pathao', false);
        $this->assertTrue(FraudCheckService::courierRetryPaused('pathao'));

        $merchant = $this->merchant('outage@example.com', '01700000036');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01777777777'])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.risk', 'waiting');

        Cache::forget('fraud_check_courier_retry_paused:pathao');
    }

    public function test_the_real_coordinator_asks_only_pathao_again_and_saves_its_counts(): void
    {
        $courierCalls = [];
        $this->mock(FraudCheckService::class, function ($mock) use (&$courierCalls): void {
            $mock->shouldReceive('normalizePhone')->andReturnUsing(fn ($phone) => '01788889999');
            $mock->shouldReceive('getReport')->once()->andReturn($this->threeCourierReport(
                $this->courierReport(['status' => 'unavailable', 'unavailable' => true, 'retryable' => true]),
            ));
            $mock->shouldReceive('checkCourier')->andReturnUsing(function (string $courier) use (&$courierCalls): array {
                $courierCalls[] = $courier;

                return $this->courierReport([
                    'total_order' => 3,
                    'confirmed' => 2,
                    'cancel' => 1,
                    'success_rate' => '67%',
                    'status' => 'ok',
                    'api_success' => true,
                ]);
            });
        });

        config([
            'order_intelligence.fraud_check.mode' => 'external_only',
            'fraud_check.include_redx' => false,
            'fraud_check.include_carrybee' => false,
            'fraud_check.bulk_retry_pause_ms' => 0,
        ]);

        $merchant = $this->merchant('real-coordinator@example.com', '01700000037');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01788889999'])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.couriers.1.title', 'Pathao')
            ->assertJsonPath('item.couriers.1.available', true)
            ->assertJsonPath('item.couriers.1.received', 2)
            ->assertJsonPath('item.couriers.1.cancelled', 1)
            ->assertJsonPath('item.received', 10)
            ->assertJsonPath('item.cancelled', 3);

        $this->assertSame(['pathao'], $courierCalls);
        $this->assertFalse(FraudCheckService::courierRetryPaused('pathao'));

        $saved = BulkFraudDailyResult::query()->where('phone', '01788889999')->firstOrFail();
        $this->assertSame(10, (int) $saved->received);
    }

    public function test_a_merchant_never_checks_more_numbers_at_once_than_the_worker_limit(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->report(2, 2, 0, '100%'));
        });

        config(['fraud_check.bulk_workers' => 2]);

        $merchant = $this->merchant('worker-limit@example.com', '01700000038');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->json('batch.id');

        $first = Cache::lock('bulk-fraud-worker:'.$merchant->id.':0', 60);
        $second = Cache::lock('bulk-fraud-worker:'.$merchant->id.':1', 60);
        $this->assertTrue($first->get());
        $this->assertTrue($second->get());

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('retry', true)
            ->assertJsonPath('item', null);

        $this->assertSame('pending', BulkFraudBatchItem::query()->where('batch_id', $batch)->value('status'));

        $second->release();

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found');

        $first->release();
    }

    public function test_a_number_another_request_is_checking_is_skipped(): void
    {
        $phones = [];
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$phones): void {
            $mock->shouldReceive('checkSingle')->once()->andReturnUsing(function ($request, array $payload) use (&$phones): array {
                $phones[] = $payload['phone'];

                return $this->report(2, 2, 0, '100%');
            });
        });

        $merchant = $this->merchant('parallel-claim@example.com', '01700000039');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => "01711111111\n01722222222"])
            ->json('batch.id');

        BulkFraudBatchItem::query()->where('batch_id', $batch)->where('phone', '01711111111')->update(['status' => 'checking']);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.phone', '01722222222')
            ->assertJsonPath('item.status', 'found');

        $this->assertSame(['01722222222'], $phones);
        $this->assertSame('checking', BulkFraudBatchItem::query()->where('batch_id', $batch)->where('phone', '01711111111')->value('status'));
    }

    public function test_the_daily_slot_is_taken_before_the_couriers_are_called(): void
    {
        $merchant = $this->merchant('slot-first@example.com', '01700000040');
        $merchant->update(['courier_history_daily_limit' => 1]);
        $usedDuringCheck = null;

        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$usedDuringCheck, $merchant): void {
            $mock->shouldReceive('checkSingle')->once()->andReturnUsing(function () use (&$usedDuringCheck, $merchant): array {
                $usedDuringCheck = CourierHistoryUse::query()->where('user_id', $merchant->id)->count();

                throw new \RuntimeException('courier down');
            });
        });

        config(['fraud_check.bulk_retry_attempts' => 1]);

        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711111111'])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'error')
            ->assertJsonPath('quota.used_today', 0);

        $this->assertSame(1, $usedDuringCheck);
        $this->assertSame(0, CourierHistoryUse::query()->where('user_id', $merchant->id)->count());
    }

    public function test_the_batch_page_gets_the_worker_count(): void
    {
        config(['fraud_check.bulk_workers' => 3]);

        $merchant = $this->merchant('worker-prop@example.com', '01700000041');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01111111111'])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->get(route('portal.fraud-check.batches.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/FraudCheck/Batch')
                ->where('workers', 3)
                ->where('incompleteRetries', 5)
                ->where('incompleteRetryGapSeconds', 5));
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
        config(['fraud_check.bulk_retry_attempts' => 1, 'fraud_check.bulk_retry_pause_ms' => 0]);

        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('retryCouriers')->once()->andReturnUsing(fn ($request, array $payload, array $report): array => $report);
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
            ->assertJsonPath('item.risk', 'waiting')
            ->assertJsonMissing(['name' => 'Hidden Customer']);

        $this->assertStringNotContainsString('Hidden Customer', json_encode(BulkFraudBatchItem::query()->firstOrFail()->fill_report));

        $preview = $this->actingAs($merchant)
            ->getJson(route('portal.fraud-check.batches.preview', $batch));

        $preview->assertOk()
            ->assertJsonPath('headers', [
                'Phone Number',
                'Status',
                'Total Orders',
                'Successful',
                'Cancelled',
                'Success Rate',
                'Risk',
            ])
            ->assertJsonPath('rows.0', [
                '01733333333',
                'Found',
                4,
                1,
                1,
                '50.0%',
                'Waiting for courier',
            ]);

        $csv = $this->actingAs($merchant)
            ->get(route('portal.fraud-check.batches.export', $batch))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('01733333333', $csv);
        $this->assertStringContainsString('50.0%', $csv);
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

    public function test_a_fast_bulk_run_does_not_use_up_the_refresh_or_search_limit(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->andReturn($this->report(2, 2, 0, '100%'));
        });

        // Throttle counters live in the cache, which can outlive one test.
        Cache::flush();
        $limiter = new class(Cache::store()) extends RateLimiter
        {
            public array $keys = [];

            public function hit($key, $decaySeconds = 60)
            {
                $this->keys[] = $key;

                return parent::hit($key, $decaySeconds);
            }
        };
        $this->app->instance(RateLimiter::class, $limiter);

        $merchant = $this->merchant('busy-bulk-limits@example.com', '01700000985');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => "01711111111\n01722222222"])
            ->json('batch.id');

        $item = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->json('item.id');

        // A stopped batch answers next at once, like workers on a fast run.
        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.stop', $batch))
            ->assertOk()
            ->assertJsonPath('batch.status', 'stopped');

        // More next calls in one minute than single search allows.
        for ($call = 0; $call < 35; $call++) {
            $this->actingAs($merchant)
                ->postJson(route('portal.fraud-check.batches.next', $batch))
                ->assertOk();
        }

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.retry', ['batch' => $batch, 'item' => $item]))
            ->assertOk();

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.refresh', ['batch' => $batch, 'item' => $item]))
            ->assertOk()
            ->assertJsonPath('refreshed', true);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '01733333333'])
            ->assertOk();

        // store, next, retry, refresh and search each keep their own count.
        $this->assertCount(5, array_unique($limiter->keys));
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

        $this->assertStringContainsString('"Phone Number",Status,"Total Orders",Successful,Cancelled,"Success Rate",Risk', $csv);
        $this->assertStringContainsString("'=CMD()", $csv);
        $this->assertStringNotContainsString('Notes', $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|[\r\n,])=/', $csv);
    }

    public function test_csv_is_sorted_by_success_rate_high_to_low(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function ($request, array $payload) {
                return match ($payload['phone']) {
                    '01711111111' => $this->report(2, 1, 1, '50%'),
                    '01722222222' => $this->report(0, 0, 0, null),
                    '01733333333' => $this->report(4, 4, 0, '100%'),
                    '01744444444' => $this->report(5, 4, 1, '80%'),
                    default => $this->report(1, 0, 1, '0%'),
                };
            });
        });

        $merchant = $this->merchant('csv-sort@example.com', '01700000095');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => "01711111111\n01722222222\n01733333333\n01744444444",
            ])
            ->json('batch.id');

        for ($step = 0; $step < 4; $step++) {
            $this->actingAs($merchant)
                ->postJson(route('portal.fraud-check.batches.next', $batch))
                ->assertOk();
        }

        $this->actingAs($merchant)
            ->getJson(route('portal.fraud-check.batches.preview', $batch))
            ->assertOk()
            ->assertJsonPath('rows.0.0', '01733333333')
            ->assertJsonPath('rows.0.5', '100.0%')
            ->assertJsonPath('rows.0.6', 'Safe')
            ->assertJsonPath('rows.1.0', '01744444444')
            ->assertJsonPath('rows.1.5', '80.0%')
            ->assertJsonPath('rows.1.6', 'Safe')
            ->assertJsonPath('rows.2.0', '01711111111')
            ->assertJsonPath('rows.2.5', '50.0%')
            ->assertJsonPath('rows.2.6', 'Review')
            ->assertJsonPath('rows.3.0', '01722222222')
            ->assertJsonPath('rows.3.5', '')
            ->assertJsonPath('rows.3.6', '');

        $csv = $this->actingAs($merchant)
            ->get(route('portal.fraud-check.batches.export', $batch))
            ->assertOk()
            ->streamedContent();

        $this->assertMatchesRegularExpression(
            '/100\.0%.*80\.0%.*50\.0%/s',
            $csv,
        );
        $this->assertGreaterThan(strpos($csv, '01733333333'), strpos($csv, '01722222222'));
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

    public function test_single_phone_search_shows_the_courier_breakdown_and_uses_one_daily_slot(): void
    {
        $calls = 0;
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function () use (&$calls) {
                $calls++;

                return [
                    'total_order' => 5,
                    'confirmed' => 4,
                    'cancel' => 1,
                    'success_rate' => '80%',
                    'courier' => [
                        [
                            'title' => 'Pathao',
                            'report' => [
                                'total_order' => 4,
                                'confirmed' => 4,
                                'cancel' => 0,
                                'success_rate' => '100%',
                                'status' => 'ok',
                                'api_success' => true,
                            ],
                        ],
                        [
                            'title' => 'Stead Fast',
                            'report' => [
                                'total_order' => 1,
                                'confirmed' => 0,
                                'cancel' => 1,
                                'success_rate' => '0%',
                                'status' => 'ok',
                                'api_success' => true,
                                'frauds' => [['details' => 'Customer refused the parcel']],
                            ],
                        ],
                        [
                            'title' => 'RedX',
                            'report' => [
                                'total_order' => 0,
                                'confirmed' => 0,
                                'cancel' => 0,
                                'status' => 'unavailable',
                                'message' => 'RedX login failed with configured credentials.',
                            ],
                        ],
                    ],
                ];
            });
        });

        $merchant = $this->merchant('single-search@example.com', '01700000093');

        $this->actingAs($merchant)
            ->get(route('portal.fraud-check.search'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/FraudCheck/Search'));

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '01876426700'])
            ->assertOk()
            ->assertJsonPath('result.phone', '01876426700')
            ->assertJsonPath('result.status', 'found')
            ->assertJsonPath('result.risk', 'safe')
            ->assertJsonPath('result.success_rate', 80)
            ->assertJsonPath('result.total_orders', 5)
            ->assertJsonPath('result.received', 4)
            ->assertJsonPath('result.cancelled', 1)
            ->assertJsonPath('result.couriers.0.title', 'Pathao')
            ->assertJsonPath('result.couriers.1.title', 'Stead Fast')
            ->assertJsonPath('result.couriers.1.notes.0', 'Customer refused the parcel')
            ->assertJsonPath('result.couriers.2.notes', [])
            ->assertJsonPath('source', 'live')
            ->assertJsonPath('quota.used_today', 1)
            ->assertJsonPath('quota.remaining', 999);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '+8801876426700'])
            ->assertOk()
            ->assertJsonPath('source', 'cache')
            ->assertJsonPath('quota.used_today', 1);

        $this->assertSame(1, $calls);
        $this->assertSame(1, CourierHistoryUse::query()->where('user_id', $merchant->id)->count());
    }

    public function test_single_phone_search_rejects_an_invalid_number_and_a_used_up_limit(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('single-search-limit@example.com', '01700000094');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $merchant->update(['courier_history_daily_limit' => 0]);

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '01711111111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
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

        $this->actingAs($staff)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '01711111111'])
            ->assertOk()
            ->assertJsonPath('source', 'cache')
            ->assertJsonPath('quota.used_today', 1)
            ->assertJsonPath('quota.remaining', 0);

        $this->actingAs($staff)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '01722222222'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertSame(1, CourierHistoryUse::query()->where('user_id', $owner->id)->count());
        $this->assertSame(0, CourierHistoryUse::query()->where('user_id', $staff->id)->count());
    }

    public function test_excel_ten_digit_and_bengali_phone_numbers_normalize_and_deduplicate(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('excel-phones@example.com', '01700000096');

        $response = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Excel CSV',
                'phones' => "1712345678\n01712345678\n+8801712345678\n008801712345678\n০১৭১২৩৪৫৬৭৮\n1812345678",
            ]);

        $response->assertOk()
            ->assertJsonPath('batch.submitted_count', 6)
            ->assertJsonPath('batch.unique_count', 2)
            ->assertJsonPath('batch.duplicates_removed', 4);

        $items = $response->json('items');
        $this->assertSame(['01712345678', '01812345678'], array_column($items, 'phone'));
    }

    public function test_zero_history_customer_in_platform_cache_stays_not_found_and_never_becomes_zero_percent_high_risk(): void
    {
        config([
            'order_intelligence.enabled' => true,
            'order_intelligence.fraud_check.mode' => 'hybrid',
            'order_intelligence.fraud_check.stale_while_revalidate' => true,
            'fraud_check.include_redx' => true,
            'fraud_check.include_carrybee' => true,
        ]);

        $this->mock(FraudCheckService::class, function ($mock): void {
            $mock->shouldReceive('normalizePhone')->andReturnUsing(fn ($phone) => '01799001122');
            $mock->shouldReceive('getReport')->once()->andReturn([
                'total_order' => 0,
                'confirmed' => 0,
                'cancel' => 0,
                'frauds' => [],
                'success_rate' => 'No order history found!',
                'carrybee_frauds_count' => 0,
                'courier' => [
                    [
                        'title' => 'Stead Fast',
                        'report' => [
                            'total_order' => 0,
                            'confirmed' => 0,
                            'cancel' => 0,
                            'success_rate' => 'No order history found!',
                            'status' => 'ok',
                            'api_success' => true,
                            'message' => 'No delivery history found on Steadfast.',
                        ],
                    ],
                    [
                        'title' => 'Pathao',
                        'report' => [
                            'total_order' => 0,
                            'confirmed' => 0,
                            'cancel' => 0,
                            'success_rate' => 'No order history found!',
                            'status' => 'ok',
                            'api_success' => true,
                            'message' => 'No delivery history found on Pathao.',
                        ],
                    ],
                    [
                        'title' => 'Paper Fly',
                        'report' => [
                            'total_order' => 0,
                            'confirmed' => 0,
                            'cancel' => 0,
                            'success_rate' => 'No order history found!',
                            'status' => 'ok',
                            'api_success' => true,
                            'message' => 'No delivery records found on Paperfly.',
                        ],
                    ],
                    [
                        'title' => 'RedX',
                        'report' => [
                            'total_order' => 0,
                            'confirmed' => 0,
                            'cancel' => 0,
                            'success_rate' => 'No order history found!',
                            'status' => 'ok',
                            'api_success' => true,
                            'message' => 'No delivery history found on RedX.',
                        ],
                    ],
                    [
                        'title' => 'Carrybee',
                        'report' => [
                            'total_order' => 0,
                            'confirmed' => 0,
                            'cancel' => 0,
                            'success_rate' => 'No order history found!',
                            'status' => 'ok',
                            'api_success' => true,
                            'message' => 'No delivery history found on Carrybee.',
                        ],
                    ],
                ],
            ]);
        });

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'consistency-admin@example.com',
            'phone' => '01700000097',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        // 1. Admin checks the phone first (populates PlatformCustomerStats with delivery_rate = '0%' and CourierCustomerSnapshots).
        $adminResponse = $this->actingAs($admin)
            ->postJson(route('frauds.adminFraudCheck'), ['phone' => '01799001122'])
            ->assertOk();

        $this->assertSame(0, $adminResponse->json('total_order'));
        $this->assertSame('No order history found!', $adminResponse->json('success_rate'));

        // 2. Admin checks a second time (served from platform cache via LegacyReportFormatter).
        $adminCached = $this->actingAs($admin)
            ->postJson(route('frauds.adminFraudCheck'), ['phone' => '01799001122'])
            ->assertOk();

        $this->assertSame('platform', $adminCached->json('source'));
        $this->assertSame(0, $adminCached->json('total_order'));
        $this->assertSame('No order history found!', $adminCached->json('success_rate'));

        // 3. Merchant checks the same phone in Bulk Fraud Checker (served from platform cache).
        $merchant = $this->merchant('consistency-merchant@example.com', '01700000098');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01799001122'])
            ->assertOk()
            ->json('batch.id');

        $bulkItem = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'not_found')
            ->assertJsonPath('item.total_orders', 0)
            ->assertJsonPath('item.received', 0)
            ->assertJsonPath('item.cancelled', 0)
            ->assertJsonPath('item.success_rate', null)
            ->assertJsonPath('item.risk', null)
            ->json('item');

        foreach ($bulkItem['couriers'] as $courier) {
            $this->assertSame([], $courier['notes'], "Courier {$courier['title']} should have no fake fraud notes.");
        }
    }

    public function test_platform_cached_check_preserves_fraud_notes_and_partial_counts_between_admin_and_merchant_bulk(): void
    {
        config([
            'order_intelligence.enabled' => true,
            'order_intelligence.fraud_check.mode' => 'hybrid',
            'order_intelligence.fraud_check.stale_while_revalidate' => true,
            'fraud_check.include_redx' => false,
            'fraud_check.include_carrybee' => false,
        ]);

        $this->mock(FraudCheckService::class, function ($mock): void {
            $mock->shouldReceive('normalizePhone')->andReturnUsing(fn ($phone) => '01788112233');
            $mock->shouldReceive('getReport')->once()->andReturn([
                'total_order' => 12,
                'confirmed' => 9,
                'cancel' => 3,
                'frauds' => [
                    ['name' => 'Shop A', 'details' => 'Refused COD at doorstep', 'courier' => 'steadfast'],
                ],
                'success_rate' => '75%',
                'carrybee_frauds_count' => 0,
                'courier' => [
                    [
                        'title' => 'Stead Fast',
                        'report' => [
                            'total_order' => 10,
                            'confirmed' => 8,
                            'cancel' => 2,
                            'success_rate' => '80%',
                            'status' => 'ok',
                            'api_success' => true,
                            'frauds' => [
                                ['name' => 'Shop A', 'details' => 'Refused COD at doorstep'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Pathao',
                        'report' => [
                            'total_order' => 0,
                            'confirmed' => 0,
                            'cancel' => 0,
                            'success_rate' => 'No order history found!',
                            'status' => 'ok',
                            'api_success' => true,
                            'message' => 'No delivery history found on Pathao.',
                        ],
                    ],
                    [
                        'title' => 'Paper Fly',
                        'report' => [
                            'total_order' => 2,
                            'confirmed' => 1,
                            'cancel' => 1,
                            'partial' => 3,
                            'success_rate' => '50%',
                            'status' => 'ok',
                            'api_success' => true,
                        ],
                    ],
                ],
            ]);
        });

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'cache-notes-admin@example.com',
            'phone' => '01700000101',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        // Admin runs live check first, populating platform cache.
        $this->actingAs($admin)
            ->postJson(route('frauds.adminFraudCheck'), ['phone' => '01788112233'])
            ->assertOk()
            ->assertJsonPath('total_order', 12)
            ->assertJsonPath('confirmed', 9)
            ->assertJsonPath('cancel', 3)
            ->assertJsonPath('frauds.0.details', 'Refused COD at doorstep');

        // Merchant runs Bulk Fraud Check (served from platform cache via LegacyReportFormatter).
        $merchant = $this->merchant('cache-notes-merchant@example.com', '01700000102');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01788112233'])
            ->assertOk()
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.status', 'found')
            ->assertJsonPath('item.received', 9)
            ->assertJsonPath('item.cancelled', 3)
            ->assertJsonPath('item.partial', 3)
            ->assertJsonPath('item.total_orders', 15)
            ->assertJsonPath('item.success_rate', 75)
            ->assertJsonPath('item.risk', 'review')
            ->assertJsonPath('item.couriers.0.notes', ['Refused COD at doorstep'])
            ->assertJsonPath('item.couriers.1.notes', [])
            ->assertJsonPath('item.couriers.2.partial', 3);
    }

    public function test_admin_check_syncs_existing_daily_bulk_result_for_merchant(): void
    {
        $calls = 0;
        $this->mock(FraudCheckService::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('normalizePhone')->andReturnUsing(fn ($phone) => '01766554433');
            $mock->shouldReceive('getReport')->andReturnUsing(function () use (&$calls) {
                $calls++;

                if ($calls === 1) {
                    return $this->report(5, 3, 2, '60%');
                }

                return $this->report(20, 18, 2, '90%', 'Verified customer note');
            });
        });

        config(['order_intelligence.fraud_check.mode' => 'external_only']);

        $merchant = $this->merchant('sync-daily-merchant@example.com', '01700000103');
        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '01766554433'])
            ->assertOk()
            ->assertJsonPath('result.received', 3)
            ->assertJsonPath('result.risk', 'review');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'sync-daily-admin@example.com',
            'phone' => '01700000104',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        // Admin checks the same number live and gets updated counts.
        $this->actingAs($admin)
            ->postJson(route('frauds.adminFraudCheck'), ['phone' => '01766554433'])
            ->assertOk()
            ->assertJsonPath('confirmed', 18);

        // Merchant searches the same number again today and sees the synced result from cache.
        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.search.store'), ['phone' => '01766554433'])
            ->assertOk()
            ->assertJsonPath('source', 'cache')
            ->assertJsonPath('result.received', 18)
            ->assertJsonPath('result.cancelled', 2)
            ->assertJsonPath('result.success_rate', 90)
            ->assertJsonPath('result.risk', 'safe')
            ->assertJsonPath('result.couriers.0.notes.0', 'Verified customer note');
    }

    public function test_pending_numbers_keep_going_after_the_page_is_left(): void
    {
        $calls = 0;
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->twice()->andReturnUsing(function () use (&$calls) {
                $calls++;

                return $this->report(2, 2, 0, '100%');
            });
        });

        $merchant = $this->merchant('leave-page@example.com', '01700000077');
        $batchId = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'phones' => "01711110001\n01711110002",
            ])
            ->assertOk()
            ->json('batch.id');

        $bulk = app(MerchantBulkFraudCheck::class);
        $batch = \App\Models\BulkFraudBatch::query()->findOrFail($batchId);
        $bulk->touchPage($batch);

        $this->assertSame(['checked' => 0], $bulk->runUnattended(5));
        $this->assertSame(0, $calls);

        $this->travel(31)->seconds();

        $this->assertSame(['checked' => 2], $bulk->runUnattended(30));
        $this->assertSame(2, $calls);
        $this->assertSame(0, BulkFraudBatchItem::query()->where('batch_id', $batchId)->where('status', 'pending')->count());
    }

    public function test_a_paused_batch_is_not_checked_when_its_page_is_closed(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('paused-leave@example.com', '01700000078');
        $batchId = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711110003'])
            ->assertOk()
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.stop', $batchId))
            ->assertOk()
            ->assertJsonPath('batch.status', 'stopped');

        $this->travel(31)->seconds();

        $this->assertSame(['checked' => 0], app(MerchantBulkFraudCheck::class)->runUnattended(5));
        $this->assertSame('pending', BulkFraudBatchItem::query()->where('batch_id', $batchId)->value('status'));
    }

    public function test_the_unattended_command_checks_numbers_for_a_closed_page(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->report(1, 1, 0, '100%'));
        });

        $merchant = $this->merchant('leave-command@example.com', '01700000079');
        $batchId = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711110004'])
            ->assertOk()
            ->json('batch.id');

        $this->artisan('fraud-check:advance-pending', ['--worker' => true, '--seconds' => 15])
            ->assertSuccessful();

        $this->assertSame(0, BulkFraudBatchItem::query()->where('batch_id', $batchId)->where('status', 'pending')->count());
    }

    public function test_turning_background_checking_off_leaves_pending_numbers_alone(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('scheduler-off@example.com', '01700000080');
        $batchId = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01711110005'])
            ->assertOk()
            ->json('batch.id');

        app(BulkFraudScheduler::class)->setEnabled(false);

        $this->artisan('fraud-check:advance-pending', ['--seconds' => 5])->assertSuccessful();
        $this->artisan('fraud-check:fill-waiting', ['--seconds' => 5])->assertSuccessful();

        $this->assertSame('pending', BulkFraudBatchItem::query()->where('batch_id', $batchId)->value('status'));

        app(BulkFraudScheduler::class)->setEnabled(true);
    }

    public function test_check_again_asks_the_couriers_again_and_keeps_other_merchants_results(): void
    {
        $calls = 0;
        $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('checkSingle')->andReturnUsing(function () use (&$calls) {
                $calls++;

                return $calls === 1 ? $this->report(4, 1, 3, '25%') : $this->report(6, 5, 1, '83%');
            });
        });

        $merchant = $this->merchant('replay-bulk@example.com', '01700000091');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), [
                'name' => 'Replay',
                'phones' => "01722223333\nnot-a-number",
            ])->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.source', 'live');

        $daily = BulkFraudDailyResult::query()->sole();
        $other = $this->merchant('replay-other@example.com', '01700000093');
        $otherBatch = $this->actingAs($other)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01722223333'])
            ->assertJsonPath('items.0.source', 'cache')
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.replay', $batch))
            ->assertOk()
            ->assertJsonPath('batch.status', 'running')
            ->assertJsonPath('batch.pending', 1);

        $this->assertSame('invalid', BulkFraudBatchItem::query()->where('batch_id', $batch)->where('phone', 'not-a-number')->value('status'));
        $this->assertSame((int) $daily->id, (int) BulkFraudBatchItem::query()->where('batch_id', $otherBatch)->value('daily_result_id'));

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.next', $batch))
            ->assertOk()
            ->assertJsonPath('item.source', 'live')
            ->assertJsonPath('item.received', 5);

        $this->assertSame(2, $calls);
        $this->assertSame(1, BulkFraudDailyResult::query()->count());
        $this->assertSame(5, (int) $daily->fresh()->received);
        $this->assertSame(1, app(\App\Services\FraudCheck\CourierHistoryQuota::class)->usedToday($merchant));
    }

    public function test_check_again_waits_while_the_batch_is_still_checking_and_is_limited_per_hour(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock): void {
            $mock->shouldReceive('checkSingle')->never();
        });

        $merchant = $this->merchant('replay-busy@example.com', '01700000094');
        $batch = $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.store'), ['phones' => "01722225555\n01722225556"])
            ->json('batch.id');

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.replay', $batch))
            ->assertUnprocessable()
            ->assertJsonPath('errors.batch.0', 'This batch is still checking. Pause it or let it finish, then check it again.');

        for ($try = 0; $try < 4; $try++) {
            $this->actingAs($merchant)->postJson(route('portal.fraud-check.batches.replay', $batch))->assertUnprocessable();
        }

        $this->actingAs($merchant)
            ->postJson(route('portal.fraud-check.batches.replay', $batch))
            ->assertStatus(429);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function courierReport(array $overrides): array
    {
        return array_merge([
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
        ], $overrides);
    }

    /**
     * SteadFast 8 of 10 delivered, Paperfly no history, and the given Pathao answer.
     *
     * @param  array<string, mixed>  $pathao
     * @return array<string, mixed>
     */
    private function threeCourierReport(array $pathao, int $total = 10, int $received = 8): array
    {
        return [
            'total_order' => $total,
            'confirmed' => $received,
            'cancel' => $total - $received,
            'success_rate' => (int) round($received / $total * 100).'%',
            'frauds' => [],
            'courier' => [
                [
                    'title' => 'Stead Fast',
                    'report' => $this->courierReport([
                        'total_order' => 10,
                        'confirmed' => 8,
                        'cancel' => 2,
                        'success_rate' => '80%',
                        'status' => 'ok',
                        'api_success' => true,
                    ]),
                ],
                ['title' => 'Pathao', 'report' => $pathao],
                ['title' => 'Paper Fly', 'report' => $this->courierReport(['status' => 'ok', 'api_success' => true])],
            ],
        ];
    }
}
