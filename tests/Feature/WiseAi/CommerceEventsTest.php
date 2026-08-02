<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseCommerceEvent;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Commerce\CommerceAttribution;
use App\WiseAi\Commerce\CommerceEventIngestor;
use App\WiseAi\Commerce\CommerceEventTypes;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Intelligence\MetricDefinitions;
use App\WiseAi\TurnRunner;

it('ingests commerce events idempotently and attributes GMV only with prior turns', function () {
    $key = WiseApiKey::generate('commerce-c4')['key'];

    app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'দাম কত এই প্রোডাক্টের?',
        'channel' => 'website_bubble',
        'conversation_id' => 'buyer-42',
        'context' => ['product_id' => 'sku-1', 'platform' => 'custom'],
    ]));

    $ingestor = app(CommerceEventIngestor::class);
    $first = $ingestor->ingest($key, [
        'event_type' => CommerceEventTypes::ORDER_CREATED,
        'idempotency_key' => 'ord-42-created',
        'conversation_id' => 'buyer-42',
        'external_order_id' => 'ORD-42',
        'platform' => 'custom',
        'amount' => 1500,
        'currency' => 'BDT',
    ]);
    $second = $ingestor->ingest($key, [
        'event_type' => CommerceEventTypes::ORDER_CREATED,
        'idempotency_key' => 'ord-42-created',
        'conversation_id' => 'buyer-42',
        'amount' => 1500,
        'currency' => 'BDT',
    ]);

    expect($first['created'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and($first['event']->id)->toBe($second['event']->id)
        ->and($first['event']->wise_turn_id)->not->toBeNull();

    $report = app(CommerceAttribution::class)->report(7, $key->id, true);

    expect($report['attributed_orders'])->toBe(1)
        ->and((float) $report['attributed_gmv'])->toBe(1500.0)
        ->and($report['attributed_gmv_currency'])->toBe('BDT')
        ->and(MetricDefinitions::VERSION)->toBe('1.1');

    // Unattributed: order with no conversation turns.
    $ingestor->ingest($key, [
        'event_type' => CommerceEventTypes::ORDER_CREATED,
        'idempotency_key' => 'ghost-order',
        'conversation_id' => 'never-chatted',
        'amount' => 9999,
        'currency' => 'BDT',
    ]);
    $after = app(CommerceAttribution::class)->report(7, $key->id, true);
    expect($after['attributed_orders'])->toBe(1)
        ->and((float) $after['attributed_gmv'])->toBe(1500.0);

    WiseCommerceEvent::query()->where('wise_api_key_id', $key->id)->delete();
    WiseTurn::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});

it('rejects invalid turn_id ownership on commerce ingest', function () {
    $key = WiseApiKey::generate('commerce-bad-turn')['key'];
    $other = WiseApiKey::generate('commerce-other')['key'];
    $turn = WiseTurn::create([
        'wise_api_key_id' => $other->id,
        'channel' => 'test',
        'conversation_id' => 'x',
        'text' => 'hi',
        'payload' => [],
        'config_snapshot' => ['sandbox' => false],
        'decision' => ['action' => 'suggest_reply'],
        'status' => 'ok',
    ]);

    expect(fn () => app(CommerceEventIngestor::class)->ingest($key, [
        'event_type' => CommerceEventTypes::ORDER_PAID,
        'idempotency_key' => 'bad-turn',
        'turn_id' => $turn->id,
        'amount' => 10,
        'currency' => 'BDT',
    ]))->toThrow(InvalidArgumentException::class);

    $turn->delete();
    $key->delete();
    $other->delete();
});
