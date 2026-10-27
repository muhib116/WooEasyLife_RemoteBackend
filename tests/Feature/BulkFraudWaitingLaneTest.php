<?php

use App\Models\BulkFraudBatchItem;
use App\Models\BulkFraudDailyResult;
use App\Models\User;
use App\Services\FraudCheck\MerchantBulkDailySync;
use App\Services\FraudCheck\MerchantBulkFraudRow;
use App\Services\FraudCheck\MerchantBulkWaitingLane;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config([
        'fraud_check.bulk_retry_attempts' => 1,
        'fraud_check.bulk_retry_pause_ms' => 0,
        'fraud_check.bulk_incomplete_retries' => 5,
        'fraud_check.bulk_incomplete_retry_gap_seconds' => 5,
        'fraud_check.bulk_waiting.max_minutes' => 360,
    ]);

    // The test cache can outlive one test, so another test's lane run must
    // not look like a scheduler run here.
    foreach (['steadfast', 'pathao', 'paperfly', 'redx', 'carrybee'] as $courier) {
        Cache::forget('fraud_check_courier_retry_paused:'.$courier);
        Cache::forget('fraud_check_courier_retry_failures:'.$courier);
    }
    Cache::forget('bulk_waiting_lane:heartbeat');
    Cache::forget('bulk_waiting_lane:nudge');

    Sleep::fake();
});

function waitingLaneMerchant(string $email = 'waiting-lane@example.com', string $phone = '01700000091'): User
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
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function waitingLaneCourier(array $overrides): array
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
function waitingLaneReport(array $pathao): array
{
    return [
        'total_order' => 10,
        'confirmed' => 8,
        'cancel' => 2,
        'success_rate' => '80%',
        'frauds' => [['name' => 'Hidden Customer', 'details' => 'Refused at the gate']],
        'courier' => [
            ['title' => 'Stead Fast', 'report' => waitingLaneCourier([
                'total_order' => 10,
                'confirmed' => 8,
                'cancel' => 2,
                'success_rate' => '80%',
                'status' => 'ok',
                'api_success' => true,
            ])],
            ['title' => 'Pathao', 'report' => $pathao],
            ['title' => 'Paper Fly', 'report' => waitingLaneCourier(['status' => 'ok', 'api_success' => true])],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function waitingLaneAnsweredPathao(): array
{
    return waitingLaneCourier(['total_order' => 2, 'confirmed' => 2, 'success_rate' => '100%', 'status' => 'ok', 'api_success' => true]);
}

/**
 * Pathao had no room, so the number was not asked.
 *
 * @return array<string, mixed>
 */
function waitingLaneBusyPathao(int $retryAfter = 20): array
{
    return waitingLaneCourier([
        'status' => 'unavailable',
        'unavailable' => true,
        'deferred' => true,
        'rate_limited' => true,
        'retry_after' => $retryAfter,
        'message' => 'Pathao is busy, so this number was not asked yet. Ask again in about '.$retryAfter.' seconds.',
    ]);
}

/**
 * Pathao was asked and did not answer.
 *
 * @return array<string, mixed>
 */
function waitingLaneFailedPathao(): array
{
    return waitingLaneCourier([
        'status' => 'unavailable',
        'unavailable' => true,
        'retryable' => true,
        'message' => 'Pathao did not answer this time. This number is checked again automatically.',
    ]);
}

/**
 * @param  array<string, mixed>  $live
 * @return array{report: array<string, mixed>, answered: list<string>, live: array<string, array<string, mixed>>}
 */
function waitingLaneAsked(array $live, bool $answered = false): array
{
    return [
        'report' => waitingLaneReport($live),
        'answered' => $answered ? ['pathao'] : [],
        'live' => ['pathao' => $live],
    ];
}

/**
 * Start a one-number batch and check the number once.
 */
function waitingLaneStart(User $merchant, string $phone): int
{
    $batch = (int) test()->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.store'), ['phones' => $phone])
        ->assertOk()
        ->json('batch.id');

    test()->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.next', $batch))
        ->assertOk();

    return $batch;
}

function waitingLaneItem(): BulkFraudBatchItem
{
    return BulkFraudBatchItem::query()->firstOrFail();
}

it('keeps a number waiting for pathao and fills it later, even after the batch finished', function (): void {
    $asked = [];
    $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$asked): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('retryCouriers')->never();
        $mock->shouldReceive('askCouriers')->once()->andReturnUsing(function ($request, array $payload, array $report, array $couriers) use (&$asked): array {
            $asked[] = [$payload['phone'], $couriers];

            return waitingLaneAsked(waitingLaneAnsweredPathao(), answered: true);
        });
    });

    $merchant = waitingLaneMerchant();
    $batch = (int) $this->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01755550001'])
        ->json('batch.id');

    $this->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.next', $batch))
        ->assertOk()
        ->assertJsonPath('done', true)
        ->assertJsonPath('item.risk', 'waiting')
        ->assertJsonPath('item.received', 8)
        ->assertJsonPath('item.couriers.1.reason', 'waiting')
        ->assertJsonPath('item.waiting.pathao.tries', 0)
        ->assertJsonPath('item.waiting.pathao.reason', 'busy')
        ->assertJsonPath('batch.waiting', 1);

    expect(json_encode(waitingLaneItem()->fill_report))->not->toContain('Hidden Customer');

    // Pathao said 20 seconds; nothing is asked before that.
    $this->travel(10)->seconds();
    app(MerchantBulkWaitingLane::class)->run(1, wait: false);
    expect($asked)->toBe([]);

    $this->travel(11)->seconds();
    $this->artisan('fraud-check:fill-waiting', ['--seconds' => 1])->assertSuccessful();

    expect($asked)->toBe([['01755550001', ['pathao']]]);

    $item = waitingLaneItem();
    expect($item->risk)->toBe('safe')
        ->and((int) $item->received)->toBe(10)
        ->and((int) $item->cancelled)->toBe(2)
        ->and($item->waiting_couriers)->toBeNull()
        ->and($item->fill_report)->toBeNull()
        ->and($item->next_fill_at)->toBeNull();

    $daily = BulkFraudDailyResult::query()->firstOrFail();
    expect($daily->risk)->toBe('safe')
        ->and((int) $daily->received)->toBe(10)
        ->and(MerchantBulkFraudRow::hasEveryCoreCourier($daily->couriers))->toBeTrue();
});

it('counts a try only when the courier was really asked', function (): void {
    $answers = [waitingLaneBusyPathao(30), waitingLaneFailedPathao(), waitingLaneBusyPathao(40)];
    $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$answers): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('askCouriers')->times(3)->andReturnUsing(function () use (&$answers): array {
            return waitingLaneAsked(array_shift($answers));
        });
    });

    waitingLaneStart(waitingLaneMerchant(), '01755550002');
    $lane = app(MerchantBulkWaitingLane::class);

    $this->travel(21)->seconds();
    $lane->run(1, wait: false);
    $waiting = waitingLaneItem()->waitingCouriers();
    expect($waiting['pathao']['tries'])->toBe(0)
        ->and($waiting['pathao']['next_at'])->toBe(now()->getTimestamp() + 30)
        ->and($waiting['pathao']['reason'])->toBe('busy');

    $this->travel(31)->seconds();
    $lane->run(1, wait: false);
    $waiting = waitingLaneItem()->waitingCouriers();
    expect($waiting['pathao']['tries'])->toBe(1)
        ->and($waiting['pathao']['next_at'])->toBe(now()->getTimestamp() + 5)
        ->and($waiting['pathao']['reason'])->toBe('no_answer');

    $this->travel(6)->seconds();
    $lane->run(1, wait: false);
    $waiting = waitingLaneItem()->waitingCouriers();
    expect($waiting['pathao']['tries'])->toBe(1)
        ->and($waiting['pathao']['next_at'])->toBe(now()->getTimestamp() + 40)
        ->and(waitingLaneItem()->risk)->toBe('waiting');
});

it('saves the number as incomplete after five real tries and stops asking', function (): void {
    $calls = 0;
    $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$calls): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneFailedPathao()));
        $mock->shouldReceive('retryCouriers')->once()->andReturnUsing(fn ($request, array $payload, array $report): array => $report);
        $mock->shouldReceive('askCouriers')->times(5)->andReturnUsing(function () use (&$calls): array {
            $calls++;

            return waitingLaneAsked(waitingLaneFailedPathao());
        });
    });

    $merchant = waitingLaneMerchant();
    $batch = waitingLaneStart($merchant, '01755550003');
    expect(waitingLaneItem()->risk)->toBe('waiting');

    $lane = app(MerchantBulkWaitingLane::class);

    for ($round = 1; $round <= 5; $round++) {
        $this->travel(61)->seconds();
        $lane->run(1, wait: false);
        expect($calls)->toBe($round);
    }

    $item = waitingLaneItem();
    expect($item->risk)->toBe('incomplete')
        ->and($item->waiting_couriers)->toBeNull()
        ->and($item->next_fill_at)->toBeNull();

    $this->travel(10)->minutes();
    $lane->run(1, wait: false);
    expect($calls)->toBe(5);

    $this->actingAs($merchant)
        ->getJson(route('portal.fraud-check.batches.preview', $batch))
        ->assertOk()
        ->assertJsonPath('rows.0.6', 'Incomplete');
});

it('stops waiting when the waiting time runs out', function (): void {
    config(['fraud_check.bulk_waiting.max_minutes' => 10]);

    $this->mock(FraudCheckCoordinator::class, function ($mock): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('askCouriers')->once()->andReturn(waitingLaneAsked(waitingLaneBusyPathao(60)));
    });

    waitingLaneStart(waitingLaneMerchant(), '01755550004');
    $lane = app(MerchantBulkWaitingLane::class);

    $this->travel(21)->seconds();
    $lane->run(1, wait: false);
    expect(waitingLaneItem()->risk)->toBe('waiting');

    $this->travel(11)->minutes();
    $lane->run(1, wait: false);

    expect(waitingLaneItem()->risk)->toBe('incomplete')
        ->and(waitingLaneItem()->waiting_couriers)->toBeNull();
});

it('does not wait for a courier that rejected the login', function (): void {
    $this->mock(FraudCheckCoordinator::class, function ($mock): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneCourier([
            'status' => 'unavailable',
            'unavailable' => true,
            'credential_error' => true,
            'retryable' => false,
        ])));
        $mock->shouldReceive('retryCouriers')->never();
        $mock->shouldReceive('askCouriers')->never();
    });

    $merchant = waitingLaneMerchant();
    $batch = (int) $this->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.store'), ['phones' => '01755550005'])
        ->json('batch.id');

    $this->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.next', $batch))
        ->assertOk()
        ->assertJsonPath('item.risk', 'incomplete')
        ->assertJsonPath('item.waiting', null)
        ->assertJsonPath('batch.waiting', 0);

    expect(waitingLaneItem()->next_fill_at)->toBeNull();
});

it('finishes from today\'s saved result when another check already got every courier', function (): void {
    $this->mock(FraudCheckCoordinator::class, function ($mock): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('askCouriers')->never();
    });

    waitingLaneStart(waitingLaneMerchant(), '01755550006');

    $complete = MerchantBulkFraudRow::fromReport('01755550006', waitingLaneReport(waitingLaneAnsweredPathao()));
    BulkFraudDailyResult::query()->firstOrFail()->update([
        'status' => $complete['status'],
        'total_orders' => $complete['total_orders'],
        'received' => $complete['received'],
        'cancelled' => $complete['cancelled'],
        'partial' => $complete['partial'],
        'success_rate' => $complete['success_rate'],
        'risk' => $complete['risk'],
        'couriers' => $complete['couriers'],
    ]);

    $this->travel(21)->seconds();
    app(MerchantBulkWaitingLane::class)->run(1, wait: false);

    $item = waitingLaneItem();
    expect($item->risk)->toBe('safe')
        ->and((int) $item->received)->toBe(10)
        ->and($item->waiting_couriers)->toBeNull();
});

it('asks a waiting courier only when it has room', function (): void {
    $this->mock(FraudCheckCoordinator::class, function ($mock): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('askCouriers')->never();
    });
    $this->mock(PathaoFraudChecker::class, function ($mock): void {
        $mock->shouldReceive('nextSlotSeconds')->andReturn(30);
    });

    waitingLaneStart(waitingLaneMerchant(), '01755550007');

    $this->travel(21)->seconds();
    $stats = app(MerchantBulkWaitingLane::class)->run(1, wait: false);

    expect($stats)->toBe(['asked' => 0, 'finished' => 0])
        ->and(waitingLaneItem()->risk)->toBe('waiting')
        ->and(waitingLaneItem()->waitingCouriers()['pathao']['tries'])->toBe(0);
});

it('gives the page the rows that changed, the waiting count, and the courier status', function (): void {
    $this->mock(FraudCheckCoordinator::class, function ($mock): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('askCouriers')->never();
    });

    $merchant = waitingLaneMerchant();
    $batch = waitingLaneStart($merchant, '01755550008');

    $this->actingAs($merchant)
        ->getJson(route('portal.fraud-check.batches.changes', ['batch' => $batch, 'since' => now()->subMinute()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.risk', 'waiting')
        ->assertJsonPath('batch.waiting', 1)
        ->assertJsonPath('couriers.0.courier', 'pathao')
        ->assertJsonPath('couriers.0.waiting', 1)
        ->assertJsonPath('couriers.0.paused_until', null)
        ->assertJsonStructure(['server_time', 'couriers' => [['per_minute', 'eta_minutes']]]);

    $this->actingAs($merchant)
        ->getJson(route('portal.fraud-check.batches.changes', ['batch' => $batch, 'since' => now()->addHour()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(0, 'items');

    $this->actingAs($merchant)
        ->getJson(route('portal.fraud-check.batches.changes', ['batch' => $batch, 'since' => 'not a time']))
        ->assertOk()
        ->assertJsonCount(1, 'items');

    $this->actingAs(waitingLaneMerchant('waiting-lane-other@example.com', '01700000092'))
        ->getJson(route('portal.fraud-check.batches.changes', ['batch' => $batch]))
        ->assertNotFound();
});

it('asks due couriers after a page poll when the scheduler has not run', function (): void {
    $this->mock(FraudCheckCoordinator::class, function ($mock): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('askCouriers')->once()->andReturn(waitingLaneAsked(waitingLaneAnsweredPathao(), answered: true));
    });

    $merchant = waitingLaneMerchant();
    $batch = waitingLaneStart($merchant, '01755550009');

    $this->travel(21)->seconds();
    $this->actingAs($merchant)
        ->getJson(route('portal.fraud-check.batches.changes', ['batch' => $batch]))
        ->assertOk();

    expect(waitingLaneItem()->risk)->toBe('safe');

    $this->actingAs($merchant)
        ->getJson(route('portal.fraud-check.batches.changes', ['batch' => $batch]))
        ->assertOk()
        ->assertJsonPath('items.0.risk', 'safe')
        ->assertJsonPath('batch.waiting', 0)
        ->assertJsonPath('couriers', []);
});

it('does not end the wait when a later check is still missing the courier', function (): void {
    $this->mock(FraudCheckCoordinator::class, function ($mock): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
    });

    waitingLaneStart(waitingLaneMerchant(), '01755550010');
    $sync = app(MerchantBulkDailySync::class);

    $sync->syncIfPresent('01755550010', waitingLaneReport(waitingLaneFailedPathao()));
    expect(waitingLaneItem()->risk)->toBe('waiting')
        ->and(waitingLaneItem()->waitingCouriers())->toHaveKey('pathao');

    $sync->syncIfPresent('01755550010', waitingLaneReport(waitingLaneAnsweredPathao()));
    $item = waitingLaneItem();
    expect($item->risk)->toBe('safe')
        ->and((int) $item->received)->toBe(10)
        ->and($item->waiting_couriers)->toBeNull()
        ->and($item->next_fill_at)->toBeNull();
});

it('stops the wait when a manual refresh gets every courier', function (): void {
    $reports = [waitingLaneReport(waitingLaneBusyPathao(20)), waitingLaneReport(waitingLaneAnsweredPathao())];
    $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$reports): void {
        $mock->shouldReceive('checkSingle')->twice()->andReturnUsing(function () use (&$reports): array {
            return array_shift($reports);
        });
        $mock->shouldReceive('askCouriers')->never();
    });

    $merchant = waitingLaneMerchant();
    $batch = waitingLaneStart($merchant, '01755550011');
    $item = waitingLaneItem();

    $this->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.refresh', ['batch' => $batch, 'item' => $item->id]))
        ->assertOk()
        ->assertJsonPath('refreshed', true)
        ->assertJsonPath('item.risk', 'safe')
        ->assertJsonPath('batch.waiting', 0)
        ->assertJsonMissingPath('retry_after_seconds');

    expect(waitingLaneItem()->next_fill_at)->toBeNull();
});

it('does not ask a waiting courier while the batch is paused', function (): void {
    $asked = 0;
    $this->mock(FraudCheckCoordinator::class, function ($mock) use (&$asked): void {
        $mock->shouldReceive('checkSingle')->once()->andReturn(waitingLaneReport(waitingLaneBusyPathao(20)));
        $mock->shouldReceive('askCouriers')->once()->andReturnUsing(function () use (&$asked): array {
            $asked++;

            return waitingLaneAsked(waitingLaneBusyPathao(30));
        });
    });

    $merchant = waitingLaneMerchant('paused-lane@example.com', '01700000092');
    $batch = waitingLaneStart($merchant, '01755550091');

    $this->travel(21)->seconds();
    $this->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.stop', $batch))
        ->assertOk()
        ->assertJsonPath('batch.status', 'stopped');

    app(MerchantBulkWaitingLane::class)->run(1, wait: false);
    expect($asked)->toBe(0)
        ->and(waitingLaneItem()->waitingCouriers()['pathao']['tries'])->toBe(0);

    $this->actingAs($merchant)
        ->postJson(route('portal.fraud-check.batches.resume', $batch))
        ->assertOk()
        ->assertJsonPath('batch.status', 'running');

    app(MerchantBulkWaitingLane::class)->run(1, wait: false);
    expect($asked)->toBe(1);
});
