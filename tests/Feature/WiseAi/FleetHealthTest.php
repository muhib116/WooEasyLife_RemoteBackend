<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Intelligence\FleetAlerts;
use App\WiseAi\Intelligence\FleetHealth;
use App\WiseAi\Intelligence\MetricDefinitions;

it('builds founder fleet report excluding sandbox keys by default', function () {
    $live = WiseApiKey::generate('fleet-live')['key'];
    $sandbox = WiseApiKey::generate('fleet-sandbox')['key'];
    $sandbox->update([
        'meta' => array_merge($sandbox->meta ?? [], ['sandbox' => true]),
    ]);

    WiseTurn::create([
        'wise_api_key_id' => $live->id,
        'channel' => 'test',
        'conversation_id' => 'fleet-c1',
        'text' => 'hello',
        'payload' => ['text' => 'hello'],
        'config_snapshot' => ['sandbox' => false, 'metrics_version' => MetricDefinitions::VERSION],
        'decision' => ['action' => 'suggest_reply', 'intent' => 'greeting', 'confidence' => 90],
        'evidence' => [],
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
        'latency_ms' => 15,
    ]);

    WiseTurn::create([
        'wise_api_key_id' => $sandbox->id,
        'channel' => 'eval',
        'conversation_id' => 'fleet-c2',
        'text' => 'noise',
        'payload' => ['text' => 'noise'],
        'config_snapshot' => ['sandbox' => true],
        'decision' => ['action' => 'clarify', 'intent' => 'price', 'confidence' => 70],
        'evidence' => [],
        'trace' => [],
        'status' => 'ok',
        'gap' => true,
        'latency_ms' => 8,
    ]);

    $report = app(FleetHealth::class)->report(7, true);

    expect($report['alerts_version'])->toBe(FleetAlerts::VERSION)
        ->and($report['metrics_version'])->toBe(MetricDefinitions::VERSION)
        ->and($report['fleet']['turns'])->toBeGreaterThanOrEqual(1)
        ->and($report['fleet']['keys_sandbox_hidden'])->toBeGreaterThanOrEqual(1)
        ->and(collect($report['keys'])->pluck('wise_api_key_id'))->toContain($live->id)
        ->and(collect($report['keys'])->pluck('wise_api_key_id'))->not->toContain($sandbox->id)
        ->and($report['daily'])->not->toBeEmpty();

    WiseTurn::query()->whereIn('wise_api_key_id', [$live->id, $sandbox->id])->delete();
    $live->delete();
    $sandbox->delete();
});

it('flags allow_auto as a critical fleet alert', function () {
    $key = WiseApiKey::generate('fleet-auto')['key'];
    $key->update([
        'meta' => array_merge($key->meta ?? [], [
            'governance' => ['allow_auto' => true, 'mode' => 'assist'],
        ]),
    ]);
    $key->refresh();

    $report = app(FleetHealth::class)->report(7, true);
    $row = collect($report['keys'])->firstWhere('wise_api_key_id', $key->id);

    expect($row)->not->toBeNull()
        ->and($row['allow_auto'])->toBeTrue()
        ->and($row['alert_ids'])->toContain('auto_enabled');

    expect(collect($report['alerts'])->contains(
        fn ($a) => $a['id'] === 'auto_enabled' && $a['wise_api_key_id'] === $key->id
    ))->toBeTrue();

    $key->delete();
});
