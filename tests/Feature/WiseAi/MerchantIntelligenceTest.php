<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Governance\GovernanceSealer;
use App\WiseAi\Intelligence\MerchantIntelligence;
use App\WiseAi\Intelligence\MetricDefinitions;

it('seals metrics_version on governance snapshot', function () {
    $generated = WiseApiKey::generate('bi-seal-test');
    /** @var WiseApiKey $key */
    $key = $generated['key'];

    $seal = app(GovernanceSealer::class)->seal($key);

    expect($seal['metrics_version'] ?? null)->toBe(MetricDefinitions::VERSION);

    $key->delete();
});

it('builds a merchant intelligence report excluding sandbox by default', function () {
    $live = WiseApiKey::generate('bi-live')['key'];
    $sandbox = WiseApiKey::generate('bi-sandbox')['key'];
    $sandbox->update([
        'meta' => array_merge($sandbox->meta ?? [], ['sandbox' => true]),
    ]);

    WiseTurn::create([
        'wise_api_key_id' => $live->id,
        'channel' => 'test',
        'conversation_id' => 'c1',
        'text' => 'দাম কত?',
        'payload' => ['text' => 'দাম কত?'],
        'config_snapshot' => ['sandbox' => false, 'metrics_version' => MetricDefinitions::VERSION],
        'decision' => ['action' => 'clarify', 'intent' => 'price', 'confidence' => 70],
        'evidence' => [],
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
        'latency_ms' => 12,
    ]);

    WiseTurn::create([
        'wise_api_key_id' => $sandbox->id,
        'channel' => 'eval',
        'conversation_id' => 'c2',
        'text' => 'sandbox noise',
        'payload' => ['text' => 'sandbox noise'],
        'config_snapshot' => ['sandbox' => true, 'metrics_version' => MetricDefinitions::VERSION],
        'decision' => ['action' => 'clarify', 'intent' => 'price', 'confidence' => 70],
        'evidence' => [],
        'trace' => [],
        'status' => 'ok',
        'gap' => true,
        'latency_ms' => 9,
    ]);

    $report = app(MerchantIntelligence::class)->report(7, null, true);

    expect($report['metrics_version'])->toBe(MetricDefinitions::VERSION)
        ->and($report['metrics']['turns'])->toBeGreaterThanOrEqual(1)
        ->and($report['action_mix']['clarify'])->toBeGreaterThanOrEqual(1)
        ->and(collect($report['definitions'])->pluck('id'))->toContain('gap_rate', 'accept_rate', 'knowledge_leak_proxy');

    // Sandbox gap must not inflate default BI.
    $sandboxOnly = app(MerchantIntelligence::class)->report(7, $sandbox->id, true);
    expect($sandboxOnly['metrics']['turns'])->toBe(0);

    WiseTurn::query()->whereIn('wise_api_key_id', [$live->id, $sandbox->id])->delete();
    $live->delete();
    $sandbox->delete();
});
