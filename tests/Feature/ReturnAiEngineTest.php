<?php

use App\Services\LandingSettingsService;
use App\Services\ReturnAi\ReturnAiEngine;
use App\Services\ReturnAi\ReturnAiPrompt;
use App\Services\ReturnAi\ReturnAiSettings;
use App\Services\ReturnAi\ReturnAiSignals;
use App\Services\ReturnAi\ReturnAiText;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

/**
 * Case file with the given rider notes, calls and extras, as the store sends it.
 *
 * @param  list<array{0: string, 1?: string}>  $notes
 * @param  list<array<string, mixed>>  $calls
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function deskCase(array $notes = [], array $calls = [], array $extra = []): array
{
    $built = [
        'today' => '2026-10-26',
        'parcel' => [
            'consignment_id' => '123456789',
            'return_status' => 'pending',
            'return_reason' => $extra['reason'] ?? '',
            'requested_at' => '2026-10-26 10:00:00',
            'courier_status' => $extra['courier_status'] ?? 'hold',
            'cod_amount' => 1200,
        ],
        'order' => ['total' => 1200],
        'notes' => [],
        'calls' => [],
        'rider' => ['name' => 'Rider', 'has_phone' => true],
        'customer' => ['courier_history' => $extra['history'] ?? null, 'store_history' => null],
    ];
    foreach ($notes as $i => $note) {
        $built['notes'][] = [
            'id' => 'n'.($i + 1),
            'at' => $note[1] ?? '2026-10-26 09:00:00',
            'type' => $extra['note_type'] ?? 'rider_note',
            'source' => 'portal',
            'text' => $note[0],
        ];
    }
    foreach ($calls as $i => $call) {
        $built['calls'][] = array_merge([
            'id' => 'c'.($i + 1),
            'at' => '2026-10-26 11:00:00',
            'to' => 'customer',
            'direction' => 'outbound',
            'status' => 'answered',
            'duration_seconds' => 40,
            'note' => '',
        ], $call);
    }

    return app(ReturnAiSignals::class)->prepare($built);
}

/**
 * @param  array<string, mixed>  $over
 * @return array<string, mixed>
 */
function deskAnswer(array $over = []): array
{
    return array_merge([
        'decision' => 'request_resend',
        'confidence' => 0.97,
        'reason_code' => 'reschedule',
        'summary_bn' => 'সারাংশ',
        'rider_note_trust' => 'doubtful',
        'suspicion_flags' => [],
        'suspicion_reason_bn' => '',
        'evidence' => [],
        'customer_promise' => ['has_promise' => false, 'phrase' => '', 'date' => ''],
        'call_plan' => [
            'call_customer' => false, 'call_rider' => false, 'first' => 'none',
            'when_bn' => '', 'why_bn' => '', 'customer_script_bn' => '', 'rider_script_bn' => '',
        ],
        'note_to_rider_bn' => 'যাওয়ার আগে কাস্টমারকে কল করুন',
        'memory_refs' => [],
    ], $over);
}

/**
 * @param  array<string, mixed>  $raw
 * @param  array<string, mixed>  $context  Prepared case file.
 * @param  array<string, mixed>  $settings
 * @return array<string, mixed>
 */
function deskFinalize(array $raw, array $context, array $settings = []): array
{
    $signals = app(ReturnAiSignals::class);

    return app(ReturnAiEngine::class)->finalize(
        $raw,
        $context,
        $signals->detectFlags($context),
        $signals->findPromise($context),
        ReturnAiSettings::sanitize($settings, []),
        'ai',
    );
}

/**
 * @return list<string>
 */
function deskCodes(array $flags): array
{
    return array_map(static fn ($f) => $f['code'], $flags);
}

function deskReply(array $answer): array
{
    return [
        'model' => 'gpt-4.1-mini-2025-04-14',
        'choices' => [['message' => ['content' => json_encode($answer, JSON_UNESCAPED_UNICODE)], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ];
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    app(LandingSettingsService::class)->update(['openai_api_key' => 'sk-test-return-ai-key']);
    // These cases assert the non-reasoning chat payload. Auto would also call /v1/models.
    app(ReturnAiSettings::class)->update(['model' => 'gpt-4.1-mini']);
});

it('reads Bangla and Banglish promise dates from the day the line was written', function () {
    $p = fn (string $text) => app(ReturnAiSignals::class)->parsePromiseDate($text, '2026-10-26')['date'] ?? null;

    expect($p('customer bolse kal nibo'))->toBe('2026-10-27')
        ->and($p('আগামীকাল নিবে'))->toBe('2026-10-27')
        ->and($p('porshu niben'))->toBe('2026-10-28')
        ->and($p('samner brihospotibar nibe'))->toBe('2026-10-29')
        ->and($p('সামনের বৃহস্পতিবার নিবে বলেছে'))->toBe('2026-10-29')
        ->and($p('sombar e nibe'))->toBe('2026-11-02')
        ->and($p('agami maser 1 tarikh nibe'))->toBe('2026-11-01')
        ->and($p('১৫ তারিখে নিবে'))->toBe('2026-11-15')
        ->and($p('২৮ তারিখ ডেলিভারি দিতে বলেছে'))->toBe('2026-10-28')
        ->and($p('3 din por nibe'))->toBe('2026-10-29')
        ->and($p('kal phone dhoreni'))->toBeNull()
        ->and($p('customer nibe na'))->toBeNull();

    $found = app(ReturnAiSignals::class)->findPromise(deskCase([
        ['customer phone dhore na', '2026-10-24 10:00:00'],
        ['customer bolse kal nibo', '2026-10-25 18:00:00'],
    ]));
    expect($found['date'])->toBe('2026-10-26')->and($found['ref'])->toBe('n2');
});

it('flags rider claims the store data does not back', function () {
    $flags = fn (array $context) => deskCodes(app(ReturnAiSignals::class)->detectFlags($context));

    expect($flags(deskCase([['customer call receive kore na']])))->toContain('unreachable_claim_unverified');

    $reached = $flags(deskCase(
        [['customer call receive kore na', '2026-10-26 10:00:00']],
        [['at' => '2026-10-26 11:30:00', 'status' => 'answered', 'duration_seconds' => 55]]
    ));
    expect($reached)->toContain('merchant_reached_customer')->not->toContain('unreachable_claim_unverified');

    expect($flags(deskCase([['customer 100 taka kom diye nise']])))->toContain('cod_short_payment_claim')
        ->and($flags(deskCase([['customer nibe na']], [], [
            'history' => ['total' => 10, 'delivered' => 9, 'cancel' => 1, 'success_rate' => 90.0, 'by_courier' => []],
        ])))->toContain('good_history_contradicts_refusal')
        ->and($flags(deskCase([['customer nibe na']], [], ['courier_status' => 'Partial Delivery'])))->toContain('already_delivered')
        ->and($flags(deskCase([
            ['Customer phone off', '2026-10-24 10:00:00'],
            ['Customer phone off', '2026-10-25 10:00:00'],
        ])))->toContain('template_note_repeated');
});

it('treats SteadFast portal lines as facts, not rider notes', function () {
    $signals = app(ReturnAiSignals::class);
    foreach ([
        'Cancellation Request has been sent.',
        'Sender requested to resend.',
        'Consignment sent to MIRPUR WAREHOUSE. Dispatch ID: 15864572',
        'Consignment has been resent to deliver.',
        'Consignment status has been updated as Pending',
        'Consignment has been cancelled by Merchant.',
    ] as $line) {
        expect($signals->isPortalLine($line))->toBeTrue();
    }
    expect($signals->isPortalLine('Rider Note: "customer phone dorche na"'))->toBeFalse();

    $context = deskCase([
        ['Cancellation Request has been sent.', '2026-10-24 10:00:00'],
        ['Cancellation Request has been sent.', '2026-10-25 10:00:00'],
    ], [], ['note_type' => 'status']);
    expect($context['notes'][0]['system_line'])->toBeTrue()
        ->and(deskCodes($signals->detectFlags($context)))->not->toContain('template_note_repeated');
});

it('verifies only quotes that are really in the case file', function () {
    $evidence = app(ReturnAiSignals::class)->verifyEvidence([
        ['source' => 'note', 'ref' => 'n1', 'quote' => 'kal nibo'],
        ['source' => 'note', 'ref' => 'n1', 'quote' => 'customer will take tomorrow'],
        ['source' => 'note', 'ref' => 'n9', 'quote' => 'kal nibo'],
        ['source' => 'history', 'ref' => 'x', 'quote' => '9 delivered'],
    ], deskCase([['Customer bolse kal nibo, bikele basay thakbe']], [], [
        'history' => ['total' => 10, 'delivered' => 9, 'cancel' => 1, 'success_rate' => 90.0],
    ]));

    expect(array_column($evidence, 'verified'))->toBe([true, false, false, true]);
});

it('masks phones again and writes the history line itself', function () {
    $context = deskCase([['customer 01712345678 phone off, ০১৮১২৩৪৫৬৭৮ e call din']], [], [
        'history' => ['total' => 4, 'delivered' => 3, 'cancel' => 1, 'success_rate' => 75],
    ]);

    expect($context['notes'][0]['text'])->not->toContain('01712345678')->toContain('01*******78')->not->toContain('1812')
        ->and($context['customer']['summary'])->toBe('courier history: 4 orders, 3 delivered, 1 cancelled, success 75%')
        ->and($context['weekday'])->toBe('Monday');
});

it('lets a clean, proven resend act and holds everything doubtful for a human', function () {
    $clean = deskCase([['Customer bolse address change hobe, abar pathan']]);
    $proof = ['evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'abar pathan']]];

    $case = deskFinalize(deskAnswer($proof), $clean);
    expect($case['human_review'])->toBeFalse()
        ->and($case['auto']['request_resend'])->toBeTrue()
        ->and($case['auto']['note'])->toBeTrue()
        ->and($case['auto']['confirm_cancel'])->toBeFalse();

    expect(deskFinalize(deskAnswer($proof), $clean, ['min_confidence' => 98])['auto']['request_resend'])->toBeFalse();

    $invented = deskFinalize(deskAnswer(['evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'customer confirmed']]]), $clean);
    expect($invented['confidence'])->toBeLessThanOrEqual(0.6)
        ->and(deskCodes($invented['flags']))->toContain('evidence_unverified')
        ->and($invented['auto']['request_resend'])->toBeFalse()
        ->and($invented['human_review'])->toBeTrue();

    $modelFlag = deskFinalize(deskAnswer([...$proof, 'suspicion_flags' => ['time_pattern_odd']]), $clean);
    expect(deskCodes($modelFlag['flags']))->toContain('time_pattern_odd')->and($modelFlag['human_review'])->toBeTrue();

    $badDate = deskFinalize(deskAnswer([...$proof, 'customer_promise' => ['has_promise' => true, 'phrase' => 'x', 'date' => '2026-02-31']]), $clean);
    expect($badDate['reminder'])->toBeNull();
});

it('never cancels on rider word, and only cancels on the merchant\'s own call', function () {
    $riderOnly = deskFinalize(deskAnswer([
        'decision' => 'confirm_cancel', 'reason_code' => 'changed_mind', 'note_to_rider_bn' => '',
        'evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'nibe na']],
    ]), deskCase([['customer nibe na']]));
    expect($riderOnly['decision'])->toBe('confirm_cancel')
        ->and($riderOnly['human_review'])->toBeTrue()
        ->and($riderOnly['auto']['confirm_cancel'])->toBeFalse()
        ->and($riderOnly['call_plan']['first'])->toBe('customer');

    $proven = deskFinalize(deskAnswer([
        'decision' => 'confirm_cancel', 'reason_code' => 'changed_mind', 'note_to_rider_bn' => 'x',
        'evidence' => [['source' => 'call', 'ref' => 'c1', 'quote' => 'does not want it anymore']],
    ]), deskCase([['customer nibe na']], [['note' => 'Customer said she does not want it anymore']]));
    expect($proven['auto']['confirm_cancel'])->toBeTrue()->and($proven['note_to_rider_bn'])->toBe('');

    $delivered = deskFinalize(deskAnswer([
        'decision' => 'confirm_cancel',
        'evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'nibe na']],
    ]), deskCase([['customer nibe na']], [], ['courier_status' => 'delivered']));
    expect($delivered['decision'])->toBe('needs_human');
});

it('turns a cancel with a promised day into a resend with a reminder', function () {
    $case = deskFinalize(deskAnswer([
        'decision' => 'confirm_cancel',
        'evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'kal nibo']],
    ]), deskCase([['customer bolse kal nibo']]));

    expect($case['decision'])->toBe('request_resend')
        ->and($case['reminder']['date'])->toBe('2026-10-27')
        ->and($case['reminder']['date_bn'])->toBe('২৭ অক্টোবর (মঙ্গলবার)')
        ->and($case['confidence'])->toBeLessThanOrEqual(0.8)
        ->and($case['auto']['request_resend'])->toBeFalse()
        ->and($case['call_plan']['call_customer'])->toBeTrue();

    $past = deskFinalize(deskAnswer([
        'customer_promise' => ['has_promise' => true, 'phrase' => 'last week', 'date' => '2026-10-20'],
        'evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'customer will receive']],
    ]), deskCase([['customer will receive']]));
    expect($past['reminder'])->toBeNull()->and(deskCodes($past['flags']))->toContain('promise_date_passed');
});

it('counts kal from the day the note was written and drops stale kal once that day has passed', function () {
    // "kal nibe" written on the 23rd means the 24th; today is the 26th.
    $context = deskCase([
        ['customer phone dhore na', '2026-10-22 10:00:00'],
        ['customer bolse kal nibe', '2026-10-23 18:00:00'],
    ]);
    $case = deskFinalize(deskAnswer([
        'summary_bn' => 'কাস্টমার বলেছেন কালকে নিবে, তাই আবার পাঠান',
        'note_to_rider_bn' => 'কাস্টমারকে ফোন দিয়ে কালকে ডেলিভারি দিন',
        'call_plan' => [
            'call_customer' => true, 'call_rider' => false, 'first' => 'customer',
            'when_bn' => 'কালকে', 'why_bn' => 'কাস্টমার কাল নেবেন', 'customer_script_bn' => '', 'rider_script_bn' => '',
        ],
        'evidence' => [['source' => 'note', 'ref' => 'n2', 'quote' => 'kal nibe']],
    ]), $context);

    expect($case['reminder'])->toBeNull()
        ->and(deskCodes($case['flags']))->toContain('promise_date_passed')
        ->and($case['call_plan']['when_bn'])->toBe('আজই')
        ->and($case['call_plan']['why_bn'])->toContain('২৪ অক্টোবর')
        ->and($case['note_to_rider_bn'])->not->toContain('কাল')
        ->and($case['summary_bn'])->toContain('২৪ অক্টোবর')
        ->and($case['summary_bn'])->toContain('পার হয়ে গেছে');

    $prompt = app(ReturnAiPrompt::class)->user([
        'context' => $context,
        'flags' => [],
        'promise' => app(ReturnAiSignals::class)->findPromise($context),
        'memory' => [],
    ]);
    expect($prompt)->toContain('n1 · 2026-10-22 Thu · 4 days ago · rider: "customer phone dhore na"')
        ->and($prompt)->toContain('n2 · 2026-10-23 Fri · 3 days ago · rider: "customer bolse kal nibe"')
        ->and($prompt)->toContain('kal → 2026-10-24 (n2), Saturday, said on 2026-10-23. That day PASSED 2 days ago');

    // A fresh "kal" still means tomorrow, and the words stay.
    $fresh = deskFinalize(deskAnswer([
        'note_to_rider_bn' => 'কাস্টমার আগামীকাল নেবেন, কল করে যান',
        'evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'kal nibe']],
    ]), deskCase([['customer bolse kal nibe', '2026-10-26 09:00:00']]));
    expect($fresh['reminder']['date'])->toBe('2026-10-27')
        ->and($fresh['note_to_rider_bn'])->toBe('কাস্টমার আগামীকাল নেবেন, কল করে যান');
});

it('tells the story in dated steps taken from the lines the model points at', function () {
    $context = deskCase(
        [
            ['customer phone dhore na', '2026-10-22 10:00:00'],
            ['customer bolse kal nibe', '2026-10-23 18:00:00'],
        ],
        [['at' => '2026-10-25 12:00:00', 'note' => 'customer bolse address change']],
    );
    $case = deskFinalize(deskAnswer([
        'journey' => [
            ['ref' => 'c1', 'text_bn' => 'আপনার কলে কাস্টমার ঠিকানা বদলাতে বলেছেন'],
            ['ref' => 'N1', 'text_bn' => 'রাইডার বলেছেন কাস্টমার ফোন ধরেননি 01712345678'],
            ['ref' => 'n2', 'text_bn' => 'কাস্টমার বলেছেন পরদিন নেবেন'],
            ['ref' => 'zz', 'text_bn' => 'পার্সেল এখনো হোল্ডে'],
            ['ref' => 'n2', 'text_bn' => ''],
        ],
        'evidence' => [['source' => 'note', 'ref' => 'n2', 'quote' => 'kal nibe']],
    ]), $context);

    // The undated step stays right after the step the model wrote before it (n2).
    expect(array_column($case['journey'], 'ref'))->toBe(['n1', 'n2', '', 'c1'])
        ->and($case['journey'][0]['date'])->toBe('2026-10-22')
        ->and($case['journey'][0]['date_bn'])->toBe('২২ অক্টো')
        ->and($case['journey'][0]['text_bn'])->toContain('01*******78')
        ->and($case['journey'][2]['date'])->toBe('')
        ->and($case['journey'][3]['date_bn'])->toBe('২৫ অক্টো');

    $rules = app(ReturnAiEngine::class)->finalize(
        app(ReturnAiEngine::class)->rulesCase($context, null),
        $context,
        [],
        null,
        ReturnAiSettings::sanitize([], []),
        'rules',
    );
    expect($rules['journey'])->toHaveCount(3)
        ->and($rules['journey'][0]['text_bn'])->toBe('রাইডার: customer phone dhore na')
        ->and($rules['journey'][2]['text_bn'])->toBe('কাস্টমারকে কল: customer bolse address change');
});

it('knows kal and porshu but not সকাল or বিকাল', function () {
    expect(ReturnAiText::hasRelativeDay('কালকে ডেলিভারি দিন'))->toBeTrue()
        ->and(ReturnAiText::hasRelativeDay('কাস্টমার কাল নেবেন'))->toBeTrue()
        ->and(ReturnAiText::hasRelativeDay('পরশু নেবেন'))->toBeTrue()
        ->and(ReturnAiText::hasRelativeDay('customer kal nibe'))->toBeTrue()
        ->and(ReturnAiText::hasRelativeDay('সকালে কল করে বিকালে ডেলিভারি দিন'))->toBeFalse()
        ->and(ReturnAiText::hasRelativeDay('আজ কল করুন'))->toBeFalse();
});

it('plans the right calls for each kind of doubt', function () {
    $unverified = deskFinalize(deskAnswer(['evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'call receive kore na']]]), deskCase([['customer call receive kore na']]));
    expect($unverified['human_review'])->toBeTrue()
        ->and($unverified['auto']['request_resend'])->toBeTrue()
        ->and($unverified['call_plan']['call_customer'])->toBeTrue();

    $money = deskFinalize(deskAnswer(['evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => '100 taka kom']]]), deskCase([['customer 100 taka kom diye nise']]));
    expect($money['auto']['request_resend'])->toBeFalse()
        ->and($money['auto']['note'])->toBeFalse()
        ->and($money['call_plan']['call_customer'] && $money['call_plan']['call_rider'])->toBeTrue();

    $conflict = deskFinalize(
        deskAnswer(['evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'phone dhore na']]]),
        deskCase([['customer phone dhore na', '2026-10-26 10:00:00']], [['at' => '2026-10-26 10:30:00']])
    );
    expect($conflict['call_plan']['first'])->toBe('rider')->and($conflict['auto']['request_resend'])->toBeFalse();
});

it('acts only in auto mode, only on ticked actions, and only when the judge agrees', function () {
    $engine = app(ReturnAiEngine::class);
    $context = deskCase([['Customer bolse address change hobe, abar pathan']]);
    $case = deskFinalize(deskAnswer(['evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'abar pathan']]]), $context);
    $auto = ReturnAiSettings::sanitize(['mode' => 'auto', 'auto_note' => true, 'auto_resend' => true], []);
    $calls = 0;
    $take = function () use (&$calls) {
        $calls++;

        return true;
    };

    $agree = deskReply(['agree' => true, 'confidence' => 0.97, 'reason_bn' => 'ঠিক আছে']);
    Http::fakeSequence('api.openai.com/*')
        ->push($agree)
        ->push($agree)
        ->push(deskReply(['agree' => true, 'confidence' => 0.9, 'reason_bn' => 'কম নিশ্চিত']));

    expect($engine->plan($case, $context, [], ReturnAiSettings::sanitize(['mode' => 'suggest'], []), true, $take)['act'])->toBe([])
        ->and($engine->plan($case, $context, [], $auto, false, $take)['act'])->toBe([])
        ->and($engine->plan($case, $context, [], ReturnAiSettings::sanitize(['mode' => 'auto', 'auto_note' => true], []), true, $take)['act'])->toBe(['note'])
        ->and($calls)->toBe(1);

    $planned = $engine->plan($case, $context, [['id' => 'm3', 'when' => '', 'then' => 'x']], $auto, true, $take);
    expect($planned['act'])->toBe(['note', 'request_resend'])
        ->and($planned['judge']['agree'])->toBeTrue();
    Http::assertSent(fn (ClientRequest $r) => $r->data()['response_format']['json_schema']['name'] === 'return_desk_judge'
        && str_contains($r->data()['messages'][1]['content'], '"action":"request_resend"')
        && str_contains($r->data()['messages'][1]['content'], '"id":"m3"'));

    $doubt = $engine->plan($case, $context, [], $auto, true, $take);
    expect($doubt['act'])->toBe([])
        ->and($doubt['human_review'])->toBeTrue()
        ->and(deskCodes($doubt['flags']))->toContain('verifier_disagreed')
        ->and($doubt['blocked_bn'])->toContain('কম নিশ্চিত')
        ->and($doubt['auto']['request_resend'])->toBeFalse();

    $noQuota = $engine->plan($case, $context, [], $auto, true, fn () => false);
    expect($noQuota['act'])->toBe([])->and($noQuota['judge']['agree'])->toBeFalse();
});

it('reviews end to end and falls back to rules when the model fails', function () {
    $engine = app(ReturnAiEngine::class);
    $settings = ReturnAiSettings::sanitize([], []);
    $memory = [['id' => 'm1', 'when' => 'রাইডার ফোন বন্ধ লিখলে', 'then' => 'আগে কাস্টমারকে কল দিন']];
    $store = deskCase([['customer bolse kal nibo, 01712345678']]);

    Http::fakeSequence('api.openai.com/*')
        ->push(deskReply(deskAnswer([
            'evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'kal nibo']],
            'memory_refs' => ['M1', 'm1', 'm77'],
        ])))
        ->push(['error' => ['message' => 'down']], 500);
    $out = $engine->review($store, $memory, $settings, false, fn () => true);
    expect($out['source'])->toBe('ai')
        ->and($out['model'])->toBe('gpt-4.1-mini-2025-04-14')
        ->and($out['case']['memory_used'])->toBe($memory)
        ->and($out['case']['reminder']['date'])->toBe('2026-10-27')
        ->and($out['case']['act'])->toBe([]);
    Http::assertSent(fn (ClientRequest $r) => ! str_contains($r->data()['messages'][1]['content'], '01712345678')
        && str_contains($r->data()['messages'][1]['content'], 'Date parser found a promise: kal → 2026-10-27 (n1)'));

    $rules = $engine->review($store, $memory, $settings, true, fn () => true);
    expect($rules['source'])->toBe('rules')
        ->and($rules['code'])->toBe('ai_failed')
        ->and($rules['error'])->toContain('এআই উত্তর দেয়নি')
        ->and($rules['case']['human_review'])->toBeTrue()
        ->and($rules['case']['confidence'])->toBeLessThanOrEqual(0.6)
        ->and($rules['case']['decision'])->toBe('request_resend')
        ->and($rules['case']['memory_used'])->toBe([])
        ->and($rules['case']['act'])->toBe([])
        ->and(deskCodes($rules['case']['flags']))->toContain('ai_unavailable');

    $limit = $engine->review($store, $memory, $settings, false, fn () => false);
    expect($limit['code'])->toBe('daily_limit')->and($limit['source'])->toBe('rules');
    Http::assertSentCount(2);
});

it('keeps calendar days the same in any PHP timezone', function () {
    $zone = date_default_timezone_get();
    date_default_timezone_set('Pacific/Kiritimati');

    expect(ReturnAiText::shift('2026-10-28', '+7 days'))->toBe('2026-11-04')
        ->and(ReturnAiText::shift('2026-10-28 06:26:18', '-30 days'))->toBe('2026-09-28 06:26:18')
        ->and(ReturnAiText::dateBn('2026-10-29'))->toBe('২৯ অক্টোবর (বৃহস্পতিবার)');

    date_default_timezone_set($zone);
});
