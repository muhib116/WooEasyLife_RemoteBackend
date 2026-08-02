<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\TurnRunner;

it('clarifies with a shortlist when many weak offer matches exist', function () {
    $key = WiseApiKey::generate('shortlist-s5')['key'];

    foreach (['Alpha Box Kit', 'Beta Box Kit', 'Gamma Box Kit', 'Delta Box Kit'] as $i => $title) {
        WiseKnowledgeItem::create([
            'wise_api_key_id' => $key->id,
            'external_id' => 'box-'.$i,
            'type' => 'product',
            'scope' => 'merchant',
            'title' => $title,
            'question' => 'box price '.$i,
            'answer' => $title.' ৳'.(100 + $i),
            'keywords' => ['box', 'kit'],
            'meta' => ['offer_kind' => 'physical'],
            'status' => 'published',
            'version' => 1,
        ]);
    }

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'box dam?',
        'channel' => 'test',
        'conversation_id' => 'shortlist-1',
    ]));

    expect($run['decision']['action'])->toBe('clarify')
        ->and($run['decision']['source'])->toBe('shortlist')
        ->and($run['decision']['gap'])->toBeFalse()
        ->and($run['decision']['missing_context'])->toBe('offer')
        ->and($run['decision']['shortlist'] ?? [])->toHaveCount(4)
        ->and($run['decision']['suggested_reply'] ?? '')->toContain('Alpha Box')
        ->and($run['decision']['suggested_reply'] ?? '')->not->toContain('৳');

    WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->delete();
    WiseTurn::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});
