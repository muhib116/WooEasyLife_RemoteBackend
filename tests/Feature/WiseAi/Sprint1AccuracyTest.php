<?php

use App\Models\WiseAi\WiseApiKey;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\DecideEngine;
use App\WiseAi\TurnRunner;

it('prefers business price over greeting on mixed utterance', function () {
    $engine = app(DecideEngine::class);

    expect($engine->classify('hello dam koto')['intent'])->toBe('price')
        ->and($engine->classify('hlw etar dam')['intent'])->toBe('price')
        ->and($engine->classify('hello')['intent'])->toBe('greeting');
});

it('classifies payment, cod, and stock sample phrases', function () {
    $engine = app(DecideEngine::class);

    expect($engine->classify('bkash e payment kora jabe?')['intent'])->toBe('payment')
        ->and($engine->classify('nagad accept করেন?')['intent'])->toBe('payment')
        ->and($engine->classify('cod available?')['intent'])->toBe('cod')
        ->and($engine->classify('cash on delivery ache?')['intent'])->toBe('cod')
        ->and($engine->classify('stock ache?')['intent'])->toBe('stock')
        ->and($engine->classify('size ache ki')['intent'])->toBe('stock');
});

it('breaks equal composite scores with higher base confidence', function () {
    // cod (82+3) and stock/available (76+9) both score 85 — prefer higher confidence → cod.
    expect(app(DecideEngine::class)->classify('cod available?')['intent'])->toBe('cod');
});

it('keeps gap + needs_human with non-empty Bangla assist on knowledge miss', function () {
    $key = WiseApiKey::generate('sprint1-gap')['key'];
    \App\WiseAi\KnowledgeResolver::excludePlatform(true);

    try {
        foreach (['delivery charge koto?', 'order koi?', 'broken product return', 'bkash payment?', 'cod?'] as $text) {
            $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
                'text' => $text,
                'channel' => 'test',
                'conversation_id' => 'sprint1-gap-'.md5($text),
            ]));

            expect($run['decision']['gap'] ?? false)->toBeTrue()
                ->and($run['decision']['action'])->toBe('needs_human')
                ->and(trim((string) ($run['decision']['suggested_reply'] ?? '')))->not->toBe('')
                ->and($run['decision']['source'] ?? null)->toBe('gap_assist')
                ->and($run['decision']['suggested_reply'])->not->toMatch('/^\d+\s*(tk|taka|৳)/i');
        }

        // stock requires offer — bare ask clarifies (like price), not silent gap.
        $bareStock = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'stock ache?',
            'channel' => 'test',
            'conversation_id' => 'sprint1-stock-bare',
        ]));
        expect($bareStock['decision']['action'])->toBe('clarify')
            ->and($bareStock['decision']['gap'] ?? false)->toBeFalse()
            ->and(trim((string) ($bareStock['decision']['suggested_reply'] ?? '')))->not->toBe('');

        // Asserted product with no published row → gap assist (S6 path).
        $missStock = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'stock ache?',
            'channel' => 'test',
            'conversation_id' => 'sprint1-stock-miss',
            'context' => ['product_id' => 'missing-sprint1-stock-404'],
        ]));
        expect($missStock['decision']['gap'] ?? false)->toBeTrue()
            ->and($missStock['decision']['action'])->toBe('needs_human')
            ->and(trim((string) ($missStock['decision']['suggested_reply'] ?? '')))->not->toBe('')
            ->and($missStock['decision']['source'] ?? null)->toBe('gap_assist');
    } finally {
        \App\WiseAi\KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('reports brain version 0.6.4', function () {
    expect(DecideEngine::BRAIN_VERSION)->toBe('0.6.4');
});

it('uses casual greeting for hi/hello, salam reply only for salam', function () {
    $engine = app(DecideEngine::class);

    expect($engine->classify('hi')['social_reply'])->toBe(DecideEngine::REPLY_CASUAL_GREETING)
        ->and($engine->classify('hello')['social_reply'])->toBe(DecideEngine::REPLY_CASUAL_GREETING)
        ->and($engine->classify('হাই')['social_reply'])->toBe(DecideEngine::REPLY_CASUAL_GREETING)
        ->and($engine->classify('হ্যালো')['social_reply'])->toBe(DecideEngine::REPLY_CASUAL_GREETING)
        ->and($engine->classify('assalamu alaikum')['social_reply'])->toBe(DecideEngine::REPLY_SALAM_GREETING)
        ->and($engine->classify('salam')['social_reply'])->toBe(DecideEngine::REPLY_SALAM_GREETING)
        ->and($engine->classify('আসসালামু আলাইকুম')['social_reply'])->toBe(DecideEngine::REPLY_SALAM_GREETING);

    foreach (['hi', 'hello', 'হাই'] as $text) {
        expect($engine->classify($text)['social_reply'])->not->toContain('ওয়ালাইকুম');
    }
});
