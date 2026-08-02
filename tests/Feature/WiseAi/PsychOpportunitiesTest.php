<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Psychology\BizOpportunities;
use App\WiseAi\Psychology\PsychSignals;
use App\WiseAi\TurnRunner;

it('seals psych and opportunities as assist side-channel without changing reply facts', function () {
    $key = WiseApiKey::generate('psych-c3')['key'];

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'দাম কত? জলদি বলেন',
        'channel' => 'test',
        'conversation_id' => 'psych-1',
    ]));

    $decision = $run['decision'];

    expect($decision['psych']['version'] ?? null)->toBe(PsychSignals::VERSION)
        ->and($decision['psych']['side_channel'] ?? null)->toBeTrue()
        ->and($decision['psych']['emotion'] ?? null)->not->toBeEmpty()
        ->and($decision['opportunities']['version'] ?? null)->toBe(BizOpportunities::VERSION)
        ->and($decision['opportunities']['side_channel'] ?? null)->toBeTrue()
        ->and($decision['opportunities']['items'])->not->toBeEmpty();

    // Bare price clarify must still be clarify — psych never invents a price reply.
    expect($decision['action'] ?? null)->toBe('clarify')
        ->and($decision['suggested_reply'] ?? null)->not->toBeNull();

    /** @var WiseTurn $turn */
    $turn = $run['turn'];
    expect($turn->decision['psych']['priority'] ?? null)->not->toBeEmpty();

    WiseTurn::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});

it('flags angry+urgent as critical assist priority', function () {
    $psych = app(PsychSignals::class)->tag(
        'এটা scam! এখনই ঠিক করেন',
        ['canonical' => 'এটা scam! এখনই ঠিক করেন', 'emoji_signals' => []],
        ['intent' => 'unknown', 'kind' => 'business', 'confidence' => 40],
        ['intent' => 'unknown', 'action' => 'clarify', 'gap' => false],
    );

    expect($psych['priority'])->toBe('critical')
        ->and($psych['emotions'])->toContain('angry', 'urgent');
});

it('suggests draft_knowledge opportunity for gaps', function () {
    $ops = app(BizOpportunities::class)->suggest(
        ['intent' => 'price', 'action' => 'needs_human', 'gap' => true],
        ['priority' => 'high', 'emotion' => 'curious'],
        [],
    );

    expect(collect($ops['items'])->pluck('id'))->toContain('draft_knowledge');
});
