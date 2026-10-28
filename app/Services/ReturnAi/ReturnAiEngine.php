<?php

namespace App\Services\ReturnAi;

/**
 * The return desk brain. One model pass over the whole case file and the
 * store's memory, hard guards on top, and a second "judge" pass before any
 * automatic action. When the model cannot run, the rules answer goes to a
 * human. The store only builds the case file and runs the actions it is given.
 */
class ReturnAiEngine
{
    public function __construct(
        private ReturnAiSignals $signals,
        private ReturnAiClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $context  Masked case file from the store.
     * @param  list<array{id: string, when: string, then: string}>  $memory
     * @param  array<string, mixed>  $settings  ReturnAiSettings::all()
     * @param  bool  $canAct  The store says its own gates allow an action now.
     * @param  callable(): bool  $takeCall  Spends one model call from today's quota.
     * @return array{case: array<string, mixed>, source: string, model: string, latency_ms: int, error: string, code: string}
     */
    public function review(array $context, array $memory, array $settings, bool $canAct, callable $takeCall): array
    {
        $context = $this->signals->prepare($context);
        $flags = $this->signals->detectFlags($context);
        $promise = $this->signals->findPromise($context);

        $error = '';
        $code = '';
        $model = '';
        $latency = 0;
        $raw = null;

        if (! $this->client->isConfigured()) {
            [$error, $code] = ['WooEasyLife হাবে এআই এখনো সেট হয়নি', 'not_configured'];
        } elseif (! $takeCall()) {
            [$error, $code] = ['আজকের এআই রিভিউ সীমা শেষ — কাল আবার চলবে', 'daily_limit'];
        } else {
            try {
                $answer = $this->client->decide([
                    'context' => $context,
                    'flags' => $flags,
                    'promise' => $promise,
                    'memory' => $memory,
                ]);
                $raw = $answer['data'];
                $model = $answer['model'];
                $latency = $answer['latency_ms'];
            } catch (ReturnAiException $e) {
                report($e);
                [$error, $code] = [$this->failureBn($e->status()), 'ai_failed'];
            }
        }

        $source = is_array($raw) ? 'ai' : 'rules';
        $case = $this->finalize($raw ?? $this->rulesCase($context, $promise), $context, $flags, $promise, $settings, $source);
        $case['memory_used'] = $source === 'ai' ? $this->memoryUsed((array) ($raw['memory_refs'] ?? []), $memory) : [];
        $case = $this->plan($case, $context, $memory, $settings, $canAct, $takeCall);

        return [
            'case' => $case,
            'source' => $source,
            'model' => $model,
            'latency_ms' => $latency,
            'error' => $error,
            'code' => $code,
        ];
    }

    /**
     * What the desk can say without the model. Always a human-review case.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, string>|null  $promise
     * @return array<string, mixed>
     */
    public function rulesCase(array $context, ?array $promise): array
    {
        $reason = (string) ($context['parcel']['return_reason'] ?? '');
        $notes = $this->signals->riderNotes($context);
        $texts = array_map(static fn ($n) => (string) $n['text'], $notes);
        $latest = $texts ? (string) end($texts) : $reason;
        $guess = $this->signals->guessReason($latest !== '' ? $latest : $reason);
        $decide = ReturnAiCatalog::reasons()[$guess['reason_code']]['decide'] ?? '';

        $evidence = [];
        foreach (array_slice(array_reverse($notes), 0, 2) as $note) {
            $evidence[] = ['source' => 'note', 'ref' => $note['id'], 'quote' => ReturnAiText::clip($note['text'], 120)];
        }

        return [
            'decision' => $promise ? 'request_resend' : ($decide === 'request_resend' ? 'request_resend' : 'needs_human'),
            'confidence' => min(0.6, $guess['confidence']),
            'reason_code' => $promise ? 'reschedule' : $guess['reason_code'],
            'summary_bn' => $guess['summary_bn'] !== '' ? $guess['summary_bn'] : 'নোট থেকে নিশ্চিত কারণ পাওয়া যায়নি',
            'rider_note_trust' => $texts ? 'doubtful' : 'no_rider_note',
            'suspicion_flags' => [],
            'suspicion_reason_bn' => '',
            'evidence' => $evidence,
            'customer_promise' => [
                'has_promise' => (bool) $promise,
                'phrase' => $promise['phrase'] ?? '',
                'date' => $promise['date'] ?? '',
            ],
            'call_plan' => [
                'call_customer' => true,
                'call_rider' => false,
                'first' => 'customer',
                'when_bn' => 'আজই',
                'why_bn' => 'রাইডারের নোট যাচাই করতে কাস্টমারের সাথে নিজে কথা বলুন',
                'customer_script_bn' => '',
                'rider_script_bn' => '',
            ],
            'note_to_rider_bn' => '',
        ];
    }

    /**
     * Hard guards on a raw model / rules answer.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $context  Prepared case file.
     * @param  list<array<string, mixed>>  $flags  Hub-detected flags.
     * @param  array<string, string>|null  $promise  Parser promise.
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function finalize(array $raw, array $context, array $flags, ?array $promise, array $settings, string $source): array
    {
        $min = $this->minConfidence($settings);
        $today = (string) $context['today'];
        $reviewReasons = [];

        $decision = in_array($raw['decision'] ?? '', ['request_resend', 'confirm_cancel', 'needs_human'], true) ? (string) $raw['decision'] : 'needs_human';
        $confidence = max(0.0, min(1.0, (float) ($raw['confidence'] ?? 0)));
        $reasonCode = in_array($raw['reason_code'] ?? '', ReturnAiCatalog::reasonCodes(), true) ? (string) $raw['reason_code'] : 'unknown';

        $byCode = [];
        foreach ($flags as $flag) {
            $byCode[$flag['code']] = $flag;
        }
        if ($source === 'ai') {
            foreach ((array) ($raw['suspicion_flags'] ?? []) as $code) {
                $code = ReturnAiText::key($code);
                if ($code !== '' && ! isset($byCode[$code])) {
                    $byCode[$code] = ReturnAiCatalog::flag($code, 'ai', ReturnAiText::clip($raw['suspicion_reason_bn'] ?? '', 200));
                }
            }
        } else {
            $byCode['ai_unavailable'] = ReturnAiCatalog::flag('ai_unavailable');
        }

        $evidence = $this->signals->verifyEvidence((array) ($raw['evidence'] ?? []), $context);
        $verified = array_values(array_filter($evidence, static fn ($e) => $e['verified']));
        if ($source === 'ai' && $verified === [] && $decision !== 'needs_human') {
            $confidence = min($confidence, 0.6);
            $byCode['evidence_unverified'] = ReturnAiCatalog::flag('evidence_unverified');
        }

        if (isset($byCode['already_delivered'])) {
            $decision = 'needs_human';
            $reviewReasons[] = 'কুরিয়ারে ডেলিভারড দেখাচ্ছে — ক্যানসেল করা যাবে না, রাইডারের সাথে কথা বলুন';
        }

        // The parser's date wins; the model's date is used only when it is real and near.
        $modelPromise = is_array($raw['customer_promise'] ?? null) ? $raw['customer_promise'] : [];
        $promiseDate = '';
        $promisePhrase = '';
        if ($promise) {
            $promiseDate = (string) $promise['date'];
            $promisePhrase = (string) $promise['phrase'];
        } elseif (! empty($modelPromise['has_promise']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($modelPromise['date'] ?? ''))) {
            $date = (string) $modelPromise['date'];
            $limit = ReturnAiText::shift($today, '+60 days');
            if ($limit !== '' && $date <= $limit && ReturnAiText::shift($date, '+0 days') === $date) {
                $promiseDate = $date;
                $promisePhrase = ReturnAiText::clip($modelPromise['phrase'] ?? '', 80);
            }
        }

        $reminder = null;
        if ($promiseDate !== '') {
            if ($promiseDate < $today) {
                $byCode['promise_date_passed'] = ReturnAiCatalog::flag('promise_date_passed', 'rules', ReturnAiText::dateBn($promiseDate));
            } else {
                $reminder = [
                    'date' => $promiseDate,
                    'date_bn' => ReturnAiText::dateBn($promiseDate),
                    'phrase' => $promisePhrase,
                    'note_bn' => sprintf('কাস্টমার বলেছেন "%s" — %s পার্সেল নেওয়ার কথা', $promisePhrase, ReturnAiText::dateBn($promiseDate)),
                ];
                if ($decision === 'confirm_cancel') {
                    $decision = 'request_resend';
                    $confidence = min($confidence, 0.8);
                    $reviewReasons[] = 'কাস্টমার নেওয়ার দিন বলেছেন — ক্যানসেল না করে রিসেন্ড';
                }
                if ($decision === 'request_resend') {
                    $reasonCode = 'reschedule';
                }
            }
        }

        $plan = is_array($raw['call_plan'] ?? null) ? $raw['call_plan'] : [];
        $callPlan = [
            'call_customer' => ! empty($plan['call_customer']),
            'call_rider' => ! empty($plan['call_rider']),
            'first' => in_array($plan['first'] ?? '', ['customer', 'rider', 'none'], true) ? (string) $plan['first'] : 'none',
            'when_bn' => ReturnAiText::clip($plan['when_bn'] ?? '', 80),
            'why_bn' => ReturnAiText::clip($plan['why_bn'] ?? '', 240),
            'customer_script_bn' => ReturnAiText::clip($plan['customer_script_bn'] ?? '', 400),
            'rider_script_bn' => ReturnAiText::clip($plan['rider_script_bn'] ?? '', 400),
        ];

        $callTo = [];
        foreach ((array) ($context['calls'] ?? []) as $call) {
            $callTo[(string) $call['id']] = (string) $call['to'];
        }
        $hasCustomerCallProof = (bool) array_filter(
            $verified,
            static fn ($e) => $e['source'] === 'call' && ($callTo[$e['ref']] ?? '') === 'customer'
        );
        if ($decision === 'confirm_cancel' && ! $hasCustomerCallProof) {
            $reviewReasons[] = 'ক্যানসেলের আগে কাস্টমারের সাথে নিজে কথা বলে নিশ্চিত হোন';
            $callPlan['call_customer'] = true;
            if ($callPlan['first'] === 'none') {
                $callPlan['first'] = 'customer';
            }
        }

        if (isset($byCode['merchant_reached_customer'])) {
            $callPlan['call_rider'] = true;
            $callPlan['first'] = 'rider';
        }
        if (isset($byCode['unreachable_claim_unverified']) || isset($byCode['good_history_contradicts_refusal'])) {
            $callPlan['call_customer'] = true;
            if ($callPlan['first'] === 'none') {
                $callPlan['first'] = 'customer';
            }
        }
        if (isset($byCode['cod_short_payment_claim']) || isset($byCode['partial_delivery_claim'])) {
            $callPlan['call_customer'] = true;
            $callPlan['call_rider'] = true;
        }
        if ($reminder) {
            $callPlan['call_customer'] = true;
            if ($callPlan['when_bn'] === '') {
                $callPlan['when_bn'] = $reminder['date_bn'].' সকালে';
            }
        }
        if (! $callPlan['call_customer'] && ! $callPlan['call_rider']) {
            $callPlan['first'] = 'none';
        } elseif ($callPlan['first'] === 'none'
            || ($callPlan['first'] === 'customer' && ! $callPlan['call_customer'])
            || ($callPlan['first'] === 'rider' && ! $callPlan['call_rider'])) {
            $callPlan['first'] = $callPlan['call_customer'] ? 'customer' : 'rider';
        }

        if ($confidence < 0.6 && $decision !== 'needs_human') {
            $byCode['low_confidence'] = ReturnAiCatalog::flag('low_confidence');
        }

        $flagsOut = array_values($byCode);
        $humanReview = $decision === 'needs_human' || $flagsOut !== [] || $reviewReasons !== [];

        $catalog = ReturnAiCatalog::flags();
        $allowed = ['note', 'request_resend', 'confirm_cancel'];
        foreach ($flagsOut as $flag) {
            $allowed = array_values(array_intersect($allowed, $catalog[$flag['code']]['auto'] ?? []));
        }

        $note = $decision === 'request_resend'
            ? ReturnAiText::clip(ReturnAiText::maskPhones((string) ($raw['note_to_rider_bn'] ?? '')), 160)
            : '';
        if ($note === '' && $decision === 'request_resend' && $reminder) {
            $note = sprintf('কাস্টমার %s নেবেন। যাওয়ার আগে কল করে ডেলিভারি দিন, ধন্যবাদ।', $reminder['date_bn']);
        }

        $strong = $source === 'ai' && $confidence >= $min && $verified !== [];

        return [
            'decision' => $decision,
            'confidence' => round($confidence, 3),
            'reason_code' => $reasonCode,
            'reason_label_bn' => ReturnAiCatalog::reasonLabel($reasonCode),
            'summary_bn' => ReturnAiText::clip($raw['summary_bn'] ?? '', 300),
            'rider_note_trust' => in_array($raw['rider_note_trust'] ?? '', ['trusted', 'doubtful', 'untrusted', 'no_rider_note'], true)
                ? (string) $raw['rider_note_trust']
                : 'doubtful',
            'flags' => $flagsOut,
            'review_reasons_bn' => array_values(array_unique($reviewReasons)),
            'human_review' => $humanReview,
            'evidence' => $evidence,
            'reminder' => $reminder,
            'call_plan' => $callPlan,
            'note_to_rider_bn' => $note,
            'auto' => [
                'note' => $strong && $decision === 'request_resend' && $note !== '' && in_array('note', $allowed, true),
                'request_resend' => $strong && $decision === 'request_resend' && in_array('request_resend', $allowed, true),
                'confirm_cancel' => $strong && $decision === 'confirm_cancel' && ! $humanReview && $hasCustomerCallProof,
                'min_confidence' => $min,
            ],
            'source' => $source,
        ];
    }

    /**
     * The actions to run now: allowed by the case, ticked in Settings → AI,
     * wanted by the store, and agreed by the judge.
     *
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $context
     * @param  list<array<string, string>>  $memory
     * @param  array<string, mixed>  $settings
     * @param  callable(): bool  $takeCall
     * @return array<string, mixed>
     */
    public function plan(array $case, array $context, array $memory, array $settings, bool $canAct, callable $takeCall): array
    {
        $case['act'] = [];
        $case['blocked_bn'] = '';
        $case['judge'] = null;

        if (! $canAct || ($settings['mode'] ?? '') !== 'auto') {
            return $case;
        }

        $auto = (array) $case['auto'];
        $want = [];
        if ($case['decision'] === 'request_resend') {
            if (! empty($auto['note']) && ! empty($settings['auto_note'])) {
                $want[] = 'note';
            }
            if (! empty($auto['request_resend']) && ! empty($settings['auto_resend'])) {
                $want[] = 'request_resend';
            }
        } elseif ($case['decision'] === 'confirm_cancel' && ! empty($auto['confirm_cancel']) && ! empty($settings['auto_cancel'])) {
            $want[] = 'confirm_cancel';
        }
        if ($want === []) {
            return $case;
        }

        $judge = ['agree' => false, 'confidence' => 0.0, 'reason_bn' => 'আজকের এআই রিভিউ সীমা শেষ'];
        if ($takeCall()) {
            $judge = $this->judge($case, $context, $memory, (string) end($want));
        }
        $case['judge'] = $judge;

        if (! $judge['agree'] || $judge['confidence'] < $this->minConfidence($settings)) {
            $case['flags'][] = ReturnAiCatalog::flag('verifier_disagreed', 'ai', $judge['reason_bn']);
            $case['human_review'] = true;
            $case['auto'] = array_merge($auto, ['note' => false, 'request_resend' => false, 'confirm_cancel' => false]);
            $case['blocked_bn'] = ReturnAiText::clip('দ্বিতীয় যাচাই একমত নয়: '.$judge['reason_bn'], 250);

            return $case;
        }

        $case['act'] = $want;

        return $case;
    }

    /**
     * Second opinion, asked only whether a careful merchant would take this
     * exact action now. Never agrees when it cannot run.
     *
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $context
     * @param  list<array<string, string>>  $memory
     * @return array{agree: bool, confidence: float, reason_bn: string}
     */
    public function judge(array $case, array $context, array $memory, string $action): array
    {
        $verified = array_values(array_filter((array) $case['evidence'], static fn ($e) => ! empty($e['verified'])));

        try {
            $data = $this->client->judge([
                'context' => $context,
                'memory' => $memory,
                'proposal' => [
                    'action' => $action,
                    'decision' => (string) $case['decision'],
                    'summary_bn' => (string) $case['summary_bn'],
                    'evidence' => array_map(static fn ($e) => ['source' => $e['source'], 'ref' => $e['ref'], 'quote' => $e['quote']], $verified),
                    'note_to_rider_bn' => (string) $case['note_to_rider_bn'],
                ],
            ])['data'];
        } catch (ReturnAiException $e) {
            report($e);

            return ['agree' => false, 'confidence' => 0.0, 'reason_bn' => $this->failureBn($e->status())];
        }

        return [
            'agree' => ! empty($data['agree']),
            'confidence' => max(0.0, min(1.0, (float) ($data['confidence'] ?? 0))),
            'reason_bn' => ReturnAiText::clip($data['reason_bn'] ?? '', 200),
        ];
    }

    /**
     * Memory rules the model said it followed: only ids that were sent, at most five.
     *
     * @param  array<int, mixed>  $refs
     * @param  list<array{id: string, when: string, then: string}>  $memory
     * @return list<array{id: string, when: string, then: string}>
     */
    public function memoryUsed(array $refs, array $memory): array
    {
        $byId = [];
        foreach ($memory as $rule) {
            $byId[$rule['id']] = $rule;
        }

        $out = [];
        foreach ($refs as $ref) {
            $id = strtolower(trim((string) $ref));
            if (isset($byId[$id]) && ! isset($out[$id])) {
                $out[$id] = $byId[$id];
            }
            if (count($out) >= 5) {
                break;
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function minConfidence(array $settings): float
    {
        return max(95, min(99, (int) ($settings['min_confidence'] ?? 95))) / 100;
    }

    private function failureBn(int $status): string
    {
        return match (true) {
            $status === 401 => 'হাবের এআই চাবি কাজ করছে না — WooEasyLife সাপোর্টে জানান',
            $status === 429 => 'এআই এখন ব্যস্ত — একটু পরে আবার চেষ্টা করুন',
            $status === 504 => 'এআই সার্ভারে পৌঁছানো যায়নি — একটু পরে আবার চেষ্টা করুন',
            default => 'এআই উত্তর দেয়নি — একটু পরে আবার চেষ্টা করুন',
        };
    }
}
