<?php

use App\Models\WiseAi\WiseApiKey;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Explain\ExplainBuilder;
use App\WiseAi\Language\BclcBootstrap;
use App\WiseAi\Language\CorpusResolver;
use App\WiseAi\TurnRunner;

it('builds replay-safe explain with sealed language corpus snapshot', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();

    $key = WiseApiKey::generate('replay-ui')['key'];
    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'tnx vai',
        'channel' => 'website_bubble',
        'conversation_id' => 'replay-1',
    ]));

    $explain = app(ExplainBuilder::class)->build($run['turn']);

    expect($explain['replay_safe'])->toBeTrue()
        ->and($explain['sealed']['language_corpus_snapshot']['packs'] ?? [])->not->toBeEmpty()
        ->and($explain['replay']['question'] ?? null)->toBe('tnx vai')
        ->and($explain['answers']['why_corpus'] ?? '')->toContain('Replay language packs')
        ->and(collect($explain['timeline'])->firstWhere('step', 'language')['data']['language_corpus_snapshot']['packs'] ?? [])
        ->not->toBeEmpty();

    $key->delete();
});
