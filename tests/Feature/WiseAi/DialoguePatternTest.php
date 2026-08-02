<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Dialogue\DialoguePatternDetector;
use App\WiseAi\Dialogue\DialoguePatterns;
use App\WiseAi\Explain\ExplainBuilder;
use App\WiseAi\TurnRunner;

it('detects bare price vs price-on-offer dialogue patterns', function () {
    $detector = app(DialoguePatternDetector::class);

    $bare = $detector->detect([
        'intent' => 'price',
        'kind' => 'business',
        'has_product' => false,
    ]);
    expect($bare['id'])->toBe('ask_price_bare')
        ->and($bare['version'])->toBe(DialoguePatterns::VERSION)
        ->and($bare['slots'])->toHaveKey('has_product')
        ->and($bare['slots']['has_product'])->toBeFalse();

    $onOffer = $detector->detect([
        'intent' => 'price',
        'kind' => 'business',
        'has_product' => true,
    ]);
    expect($onOffer['id'])->toBe('ask_price_on_offer')
        ->and($onOffer['slots']['has_product'])->toBeTrue();
});

it('prefers followup_carry when memory applied intent_carry', function () {
    $row = app(DialoguePatternDetector::class)->detect([
        'intent' => 'price',
        'kind' => 'business',
        'memory_applied' => 'intent_carry',
        'has_product' => true,
        'prior_intent' => 'price',
    ]);
    expect($row['id'])->toBe('followup_carry')
        ->and($row['memory'])->toBeTrue()
        ->and($row['family'])->toBe('memory');
});

it('marks memory true when product subject came from memory', function () {
    $row = app(DialoguePatternDetector::class)->detect([
        'intent' => 'price',
        'kind' => 'business',
        'has_product' => true,
        'product_from_memory' => true,
    ]);
    expect($row['id'])->toBe('ask_price_on_offer')
        ->and($row['memory'])->toBeTrue()
        ->and($row['slots']['product_from_memory'] ?? null)->toBeTrue();
});

it('seals dialogue on decide and surfaces it in Explain after memory', function () {
    $key = WiseApiKey::generate('dialogue-seal')['key'];
    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dam koto',
        'channel' => 'test',
        'conversation_id' => 'dlg-1',
    ]));

    expect($run['decision']['dialogue']['id'] ?? null)->toBe('ask_price_bare')
        ->and($run['decision']['dialogue']['slots']['has_product'] ?? null)->toBeFalse()
        ->and($run['decision']['brain_version'])->toBe(\App\WiseAi\DecideEngine::BRAIN_VERSION)
        ->and($run['turn']->config_snapshot['dialogue_version'] ?? null)->toBe(DialoguePatterns::VERSION)
        ->and($run['turn']->trace['P2_dialogue']['id'] ?? null)->toBe('ask_price_bare');

    // Judge still owns action — dialogue does not invent a price reply.
    expect($run['decision']['action'])->toBeIn(['clarify', 'suggest_reply', 'needs_human'])
        ->and($run['decision']['suggested_reply'] ?? null)->not->toMatch('/^\d+\s*(tk|taka|৳)/i');

    $explain = app(ExplainBuilder::class)->build($run['turn']);
    $steps = collect($explain['timeline'] ?? [])->pluck('step')->values();
    $idxMemory = $steps->search('memory');
    $idxDialogue = $steps->search('dialogue');
    expect($idxMemory)->not->toBeFalse()
        ->and($idxDialogue)->not->toBeFalse()
        ->and($idxMemory)->toBeLessThan($idxDialogue)
        ->and($explain['answers']['why_dialogue'] ?? '')->toContain('ask_price_bare')
        ->and($explain['sealed']['dialogue_version'] ?? null)->toBe(DialoguePatterns::VERSION);

    // With asserted offer → ask_price_on_offer
    WiseKnowledgeItem::query()->create([
        'wise_api_key_id' => $key->id,
        'type' => 'product',
        'scope' => 'merchant',
        'external_id' => 'sku-dlg-1',
        'title' => 'Test Tee',
        'answer' => '500 taka',
        'status' => 'published',
        'version' => 1,
        'meta' => ['offer_kind' => 'physical', 'price' => 500],
        'match_text' => 'test tee',
    ]);

    $run2 = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dam koto',
        'channel' => 'test',
        'context' => ['product_id' => 'sku-dlg-1'],
    ]));
    expect($run2['decision']['dialogue']['id'] ?? null)->toBe('ask_price_on_offer')
        ->and($run2['decision']['dialogue']['slots']['has_product'] ?? null)->toBeTrue();

    $key->delete();
});

it('seals followup_carry via conversation memory on TurnRunner', function () {
    $key = WiseApiKey::generate('dialogue-followup')['key'];

    app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dam koto',
        'channel' => 'test',
        'conversation_id' => 'dlg-follow',
    ]));

    $follow = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'again?',
        'channel' => 'test',
        'conversation_id' => 'dlg-follow',
    ]));

    expect($follow['decision']['dialogue']['id'] ?? null)->toBe('followup_carry')
        ->and($follow['decision']['dialogue']['memory'] ?? null)->toBeTrue()
        ->and($follow['decision']['memory_used'] ?? null)->toBeTrue();

    $key->delete();
});

it('applies dialogue clarify script on bare price without inventing a price', function () {
    $key = WiseApiKey::generate('dialogue-script')['key'];
    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dam koto',
        'channel' => 'test',
        'conversation_id' => 'dlg-script-1',
        'context' => ['offer_kind' => 'physical'],
    ]));

    expect($run['decision']['dialogue']['id'] ?? null)->toBe('ask_price_bare')
        ->and($run['decision']['dialogue']['version'] ?? null)->toBe(DialoguePatterns::VERSION)
        ->and($run['decision']['dialogue']['script']['id'] ?? null)->toBe('ask_price_bare.clarify')
        ->and($run['decision']['dialogue']['script_applied'] ?? null)->toBeTrue()
        ->and($run['decision']['action'])->toBe('clarify')
        ->and($run['decision']['suggested_reply'] ?? '')->toContain('ছবি')
        ->and($run['decision']['suggested_reply'] ?? null)->not->toMatch('/^\d+\s*(tk|taka|৳)/i')
        ->and($run['turn']->trace['P2_dialogue']['script_applied'] ?? null)->toBeTrue();

    WiseTurn::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});

it('keeps knowledge reply when ask_price_on_offer — script is assist_hint only', function () {
    $key = WiseApiKey::generate('dialogue-hint')['key'];
    WiseKnowledgeItem::create([
        'wise_api_key_id' => $key->id,
        'external_id' => 'dlg-offer-1',
        'type' => 'product',
        'scope' => 'merchant',
        'title' => 'Dialogue Tee',
        'answer' => 'Price: 777 BDT',
        'keywords' => ['tee'],
        'meta' => ['platform' => 'woocommerce', 'offer_kind' => 'physical'],
        'status' => 'published',
        'version' => 1,
    ]);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dam koto',
        'channel' => 'test',
        'conversation_id' => 'dlg-script-2',
        'context' => ['product_id' => 'dlg-offer-1', 'platform' => 'woocommerce'],
    ]));

    expect($run['decision']['dialogue']['id'] ?? null)->toBe('ask_price_on_offer')
        ->and($run['decision']['action'])->toBe('suggest_reply')
        ->and($run['decision']['source'])->toBe('knowledge')
        ->and($run['decision']['suggested_reply'])->toBe('Price: 777 BDT')
        ->and($run['decision']['dialogue']['script']['apply'] ?? null)->toBe('assist_hint')
        ->and($run['decision']['dialogue']['script_applied'] ?? null)->toBeFalse();

    WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->delete();
    WiseTurn::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});
