<?php

use App\Services\FraudCheck\CourierCallContext;
use App\Services\FraudCheck\CourierPace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Cache::flush();
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();
});

function paceAsBulk(callable $callback): mixed
{
    return app(CourierCallContext::class)->asBulk($callback);
}

it('keeps the last Pathao slot of a window for a live check', function () {
    $pace = app(CourierPace::class);

    foreach (range(1, 4) as $call) {
        expect($pace->claim('pathao', 'acct-a'))->toBe(0);
    }

    expect(paceAsBulk(fn () => $pace->claim('pathao', 'acct-a')))->toBeGreaterThan(55)
        ->and($pace->claim('pathao', 'acct-a'))->toBe(0)
        ->and($pace->claim('pathao', 'acct-a'))->toBeGreaterThan(55);

    Sleep::assertNeverSlept();
});

it('spreads bulk Pathao calls over the window instead of sending a burst', function () {
    $pace = app(CourierPace::class);

    paceAsBulk(function () use ($pace) {
        expect($pace->claim('pathao', 'acct-a'))->toBe(0)
            ->and($pace->claim('pathao', 'acct-a'))->toBe(16)
            ->and($pace->waitSeconds('pathao', 'acct-a'))->toBe(16);

        foreach (range(1, 3) as $call) {
            $this->travel(16)->seconds();
            expect($pace->claim('pathao', 'acct-a'))->toBe(0);
        }
    });

    expect($pace->status('pathao', ['acct-a'])['per_minute'])->toBe(3.9)
        ->and($pace->status('pathao', ['acct-a', 'acct-b'])['per_minute'])->toBe(7.7);
});

it('lets a live Pathao check wait a few seconds for its slot', function () {
    $pace = app(CourierPace::class);

    foreach (range(1, 5) as $call) {
        $pace->claim('pathao', 'acct-a');
    }

    $this->travel(55)->seconds();

    expect($pace->claim('pathao', 'acct-a'))->toBe(0);
    Sleep::assertSleptTimes(1);
});

it('rests a rate-limited Pathao account at least 65 seconds and never wakes it early', function () {
    $pace = app(CourierPace::class);

    expect($pace->rateLimited('pathao', 'acct-a', 30))->toBe(65)
        ->and($pace->claim('pathao', 'acct-a'))->toBe(65)
        ->and(paceAsBulk(fn () => $pace->claim('pathao', 'acct-a')))->toBe(65)
        ->and($pace->claim('pathao', 'acct-b'))->toBe(0);

    $this->travel(50)->seconds();
    expect($pace->restingSeconds('pathao', 'acct-a'))->toBe(15)
        ->and(paceAsBulk(fn () => $pace->claim('pathao', 'acct-a')))->toBe(15);

    $this->travel(16)->seconds();
    expect($pace->restingSeconds('pathao', 'acct-a'))->toBe(0)
        ->and(paceAsBulk(fn () => $pace->claim('pathao', 'acct-a')))->toBe(0);
});

it('honors a longer Retry-After, up to 15 minutes', function () {
    $pace = app(CourierPace::class);

    expect($pace->rateLimited('pathao', 'acct-a', 120))->toBe(120)
        ->and($pace->rateLimited('pathao', 'acct-b', 99999))->toBe(900);
});

it('rests every Pathao account once a limit is seen to cover all of them', function () {
    $pace = app(CourierPace::class);

    foreach (range(1, 3) as $call) {
        $pace->claim('pathao', 'acct-a');
        $pace->claim('pathao', 'acct-b');
    }

    $pace->rateLimited('pathao', 'acct-b');

    expect($pace->shared('pathao'))->toBeTrue()
        ->and($pace->claim('pathao', 'acct-a'))->toBe(65)
        ->and($pace->status('pathao', ['acct-a', 'acct-b'])['per_minute'])->toBe(3.9);
});

it('keeps Pathao accounts apart when only one of them went over its own limit', function () {
    $pace = app(CourierPace::class);

    foreach (range(1, 5) as $call) {
        $pace->claim('pathao', 'acct-a');
    }

    $pace->rateLimited('pathao', 'acct-a');

    expect($pace->shared('pathao'))->toBeFalse()
        ->and($pace->claim('pathao', 'acct-b'))->toBe(0);
});

it('keeps bulk Paperfly calls four seconds apart and leaves live checks alone', function () {
    $pace = app(CourierPace::class);

    paceAsBulk(function () use ($pace) {
        expect($pace->claim('paperfly', 'acct-p'))->toBe(0)
            ->and($pace->claim('paperfly', 'acct-p'))->toBe(4);
    });

    expect($pace->claim('paperfly', 'acct-p'))->toBe(0);

    $this->travel(4)->seconds();

    expect(paceAsBulk(fn () => $pace->claim('paperfly', 'acct-p')))->toBe(0)
        ->and($pace->status('paperfly', ['acct-p'])['per_minute'])->toBe(15.0);
});

it('pauses Paperfly after two server errors in a row and sends one probe after the pause', function () {
    $pace = app(CourierPace::class);

    expect($pace->serverError('paperfly', 'acct-p'))->toBe(0)
        ->and($pace->serverError('paperfly', 'acct-p'))->toBe(300)
        ->and($pace->claim('paperfly', 'acct-p'))->toBe(300);

    // An answer to a call sent before the pause does not make it longer.
    $this->travel(10)->seconds();
    expect($pace->serverError('paperfly', 'acct-p'))->toBe(290);

    $this->travel(291)->seconds();

    paceAsBulk(function () use ($pace) {
        expect($pace->claim('paperfly', 'acct-p'))->toBe(0);

        $this->travel(5)->seconds();
        expect($pace->claim('paperfly', 'acct-p'))->toBe(15);
    });

    // The probe failed too: the next pause is longer.
    expect($pace->serverError('paperfly', 'acct-p'))->toBe(600);

    $this->travel(601)->seconds();
    expect($pace->claim('paperfly', 'acct-p'))->toBe(0);
    $pace->answered('paperfly', 'acct-p');

    $this->travel(5)->seconds();
    expect(paceAsBulk(fn () => $pace->claim('paperfly', 'acct-p')))->toBe(0);

    // Back to the first step after Paperfly answered again.
    $pace->serverError('paperfly', 'acct-p');
    expect($pace->serverError('paperfly', 'acct-p'))->toBe(300);
});

it('needs the server errors in a row before Paperfly pauses', function () {
    $pace = app(CourierPace::class);

    expect($pace->serverError('paperfly', 'acct-p'))->toBe(0);
    $pace->answered('paperfly', 'acct-p');

    expect($pace->serverError('paperfly', 'acct-p'))->toBe(0)
        ->and($pace->restingSeconds('paperfly', 'acct-p'))->toBe(0);
});

it('grows the Paperfly pause to at most 30 minutes', function () {
    $pace = app(CourierPace::class);
    $pauses = [];

    $pace->serverError('paperfly', 'acct-p');
    $pauses[] = $pace->serverError('paperfly', 'acct-p');

    foreach (range(1, 4) as $round) {
        $this->travel($pauses[array_key_last($pauses)] + 1)->seconds();
        $pace->claim('paperfly', 'acct-p');
        $pauses[] = $pace->serverError('paperfly', 'acct-p');
    }

    expect($pauses)->toBe([300, 600, 1200, 1800, 1800]);
});

it('leaves bulk at least one Pathao lookup a window when the live reserve is set too high', function () {
    config(['fraud_check.pace.pathao.per_window' => 5, 'fraud_check.pace.pathao.live_reserve' => 5]);
    $pace = app(CourierPace::class);

    expect(paceAsBulk(fn () => $pace->claim('pathao', 'acct-a')))->toBe(0)
        ->and(paceAsBulk(fn () => $pace->claim('pathao', 'acct-a')))->toBe(63)
        ->and($pace->status('pathao', ['acct-a'])['per_minute'])->toBe(1.0);
});

it('does not pace a courier without settings', function () {
    $pace = app(CourierPace::class);

    foreach (range(1, 20) as $call) {
        expect(paceAsBulk(fn () => $pace->claim('steadfast', 'acct-s')))->toBe(0);
    }

    expect($pace->serverError('steadfast', 'acct-s'))->toBe(0);
});
