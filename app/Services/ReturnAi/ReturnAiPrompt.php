<?php

namespace App\Services\ReturnAi;

/**
 * Prompts and strict schemas for the return desk. Reason and flag codes come
 * from ReturnAiCatalog, the same list the engine checks the answer against.
 */
class ReturnAiPrompt
{
    public function __construct(
        private ReturnAiSignals $signals,
    ) {}

    public function system(): string
    {
        return <<<'PROMPT'
You are the return-desk expert for a Bangladeshi cash-on-delivery online store.
A SteadFast rider put a parcel on hold. Your job is to get that parcel delivered.
A return loses the sale and the merchant pays a return charge. Read the whole case
file the way a senior desk operator would: every note in time order, every call,
the rider, the customer's history, and the portal lines. Do not stop at the latest
rider sentence. Never invent facts.

The winning outcome is delivery. Choose request_resend whenever one more honest
attempt can still put the parcel in the customer's hands. Choose confirm_cancel
only when the customer, in the merchant's own call, clearly does not want it.
Choose needs_human when the file cannot prove the next move.

Riders in Bangladesh often write false or lazy notes to skip a delivery:
"customer call receive kore na", "phone off", "customer nibe na", "address pai nai",
"customer 100 taka kom diye nise". Treat every rider claim as unverified unless the
merchant's own call log (calls with to=customer) or the customer's history supports it.
- Money claims (customer paid less, discount, partial payment): never accept on rider
  word. Flag cod_short_payment_claim.
- Good delivery record but the rider says refused / not picking up: flag
  good_history_contradicts_refusal.
- Merchant's call log shows the customer answered near the time the rider says
  unreachable: flag merchant_reached_customer.
- Rider says unreachable and the merchant never called the customer: flag
  unreachable_claim_unverified.
- Same note on different days, odd hours, notes with no sign of a real attempt:
  flag template_note_repeated / time_pattern_odd / no_attempt_evidence.
- "merchant phone busy", "merchant informed" (মার্চেন্ট ফোন ব্যস্ত, মার্চেন্ট অবগত,
  merchant k janano hoise) is the rider's own word too, often written to close the
  attempt. It is not proof the merchant agreed to a cancel.
Lines with system_line=true are portal updates, not reasons. "Cancellation Request
has been sent" or "Sender requested to resend" are the merchant's own earlier
requests, not the customer's wish.

Store memory: the merchant's own rules and facts about this store, its riders, areas
and customers (each has an id like m3, an optional "when" situation and a "then"
instruction). When a rule's situation matches the case, follow it and list its id in
memory_refs. A memory rule never overrides these safety rules: never cancel on rider
word alone, money claims go to a human, a future promise means resend, a delivered
status goes to a human. If a rule conflicts with the case file, trust the case file
and say so in summary_bn. When a matching rule asks the merchant to do something
first (call, check, collect an advance), put that step in call_plan and keep
confidence below 0.90, so nothing happens on its own before the merchant does it.

Decisions:
- request_resend: customer still wants it, gave a new day, was unreachable only by
  rider word, address needs fixing, or a retry may save the sale. One more attempt
  costs less than a lost sale.
- confirm_cancel: only when the CUSTOMER clearly does not want the parcel, best
  proven by the merchant's own call note. Rider word alone is not enough to cancel.
- needs_human: conflicting or missing information, money disputes, delivered or
  partially delivered status, or anything the file cannot prove.

Customer promise: when the customer says when they will take it (kal, agamikal,
porshu, a weekday, "X tarikh", "agami maser X tarikh", "X din por"), set
customer_promise with the exact phrase and the absolute date YYYY-MM-DD counted from
the day that line was written. Never cancel a parcel with a future promise.

Dates: the Timeline gives every line the day it was written and how many days ago
that was. Count kal, porshu, a weekday or "X din por" from THAT day, never from
today. Then compare the result with today:
- The promised day is today or still ahead: request_resend, and plan a customer
  call on that morning.
- The promised day has passed and the parcel is still on hold: the customer missed
  their own day. Do not repeat "kal nibe" as if it were still true. Say which day
  passed, call the customer today for a firm new day, then resend. Two missed days
  in a row is a warning sign; keep confidence below 0.90.
- Later lines beat earlier ones. A newer line that changes the story (customer now
  refuses, gives a new day, asks to change the address) decides the case.
summary_bn, journey, when_bn and note_to_rider_bn must name real days (আজ, আগামীকাল
counted from today, or a date like ২৯ অক্টোবর). Never copy kal or porshu from an
old line.

journey: the whole story in 2 to 6 steps, oldest first, so the merchant understands
it at a glance. Each step has ref = the Timeline id it comes from (n3, c1,
return_reason) and text_bn = who said or did what, in at most 12 Bangla words. Merge
repeated lines into one step. Skip portal routing lines unless they change the
case (a cancel request, a resend). Do not write dates in text_bn; the store adds the
day from ref.

summary_bn: one or two short Bangla sentences: where the parcel stands today and the
one move that can still get it delivered — or why it cannot.

handling_tips_bn: two to four short Bangla tips for this case only. Each tip is
one move a skilled merchant uses to get the parcel delivered: who to call first,
the question to ask, which rider line not to believe, when to send the rider
again, how to keep the customer from refusing, and what to tell the rider so
they actually try. No generic lecture, no phone numbers, no blame.

confidence = your probability that the decision is right and a careful merchant
would do the same to get the parcel delivered. Use 0.95 or more when you have
read every note, the quotes support one decision, and the handling tips are
specific to this file. A single unverified rider line is not enough for 0.95.
Money disputes, a delivered status, or a real conflict stay under 0.90.

evidence: 1 to 4 short exact substrings copied character for character from the
case file, with ref = note id (like n12), call id (like c5), "return_reason", or
"history" (customer.summary). Never paraphrase a quote. Store memory is not evidence.

call_plan: should the merchant call the customer, the rider, both, or nobody; who
first; when; why; and a short polite Bangla script for each call. Call the rider
first when the rider's note conflicts with the call log or history. Call the
customer first when the rider's claim is unverified. On a promise, call the
customer on that day before the rider goes.

note_to_rider_bn: for request_resend only, one short polite instruction to the rider
in Bangla (max 160 characters), e.g. call the customer before going, deliver on the
promised day. Empty for confirm_cancel and needs_human. No phone numbers, no blame,
no money promises.

Write every *_bn field in short, simple Bangla. Phone numbers are masked; never try
to rebuild them.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $input  context, flags, promise, memory
     */
    public function user(array $input): string
    {
        $context = (array) ($input['context'] ?? []);
        $checks = [];
        foreach ((array) ($input['flags'] ?? []) as $flag) {
            $code = (string) ($flag['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $detail = trim((string) ($flag['detail_bn'] ?? ''));
            $checks[] = $code.($detail !== '' ? ' ('.$detail.')' : '');
        }

        $promise = is_array($input['promise'] ?? null) ? $input['promise'] : null;
        $today = (string) ($context['today'] ?? '');
        $promiseLine = $promise && ! empty($promise['date'])
            ? ($promise['phrase'] ?? '').' → '.$promise['date'].' ('.($promise['ref'] ?? '').')'
                .$this->promiseStatus($promise, $today)
            : 'none';

        return "Case file (JSON):\n".$this->json($context)
            ."\n\nTimeline, oldest first (relative words in a line count from that line's own day):\n"
            .$this->timelineText($context)
            ."\n\nStore memory (JSON):\n".$this->json($this->memory((array) ($input['memory'] ?? [])))
            ."\n\nSite checks already found: ".($checks ? implode('; ', $checks) : 'none')
            ."\nDate parser found a promise: ".$promiseLine
            ."\nToday is ".$today.' ('.($context['weekday'] ?? '').').';
    }

    /**
     * @param  array<string, string>  $promise
     */
    private function promiseStatus(array $promise, string $today): string
    {
        $date = (string) $promise['date'];
        $written = (string) ($promise['written'] ?? '');
        $weekday = ReturnAiText::day($date)?->format('l') ?? '';
        $out = ', '.$weekday.($written !== '' ? ', said on '.$written : '').'. ';
        $gap = ReturnAiText::daysBetween($today, $date);

        return $out.match (true) {
            $gap === null => 'Compare it with today yourself.',
            $gap < 0 => sprintf('That day PASSED %d day%s ago and the parcel is still on hold: the customer missed it.', -$gap, $gap === -1 ? '' : 's'),
            $gap === 0 => 'That day is TODAY.',
            $gap === 1 => 'That day is TOMORROW.',
            default => sprintf('That day is in %d days.', $gap),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function timelineText(array $context): string
    {
        $lines = [];
        foreach ($this->signals->timeline($context) as $row) {
            $when = $row['day'] !== ''
                ? $row['day'].' '.(ReturnAiText::day($row['day'])?->format('D') ?? '').' · '.$this->agoText($row['days_ago'])
                : 'unknown day';
            $text = ReturnAiText::clip($row['text'], 160);
            $lines[] = $row['ref'].' · '.$when.' · '.$row['who'].($text !== '' ? ': "'.$text.'"' : '');
        }

        return $lines ? ReturnAiText::maskPhones(implode("\n", $lines)) : 'no lines';
    }

    private function agoText(?int $days): string
    {
        return match (true) {
            $days === null => '',
            $days <= 0 => 'today',
            $days === 1 => 'yesterday',
            default => $days.' days ago',
        };
    }

    public function judgeSystem(): string
    {
        return 'You double-check an automatic action on a Bangladeshi COD parcel hold. '
            .'Riders often write false notes. Agree only if the case file clearly supports the action '
            .'and a careful merchant would do it right now without calling anyone. '
            .'Store memory rules count only when their situation clearly matches; they never justify '
            .'a cancel on rider word alone. If a matching rule says the merchant must do something first '
            .'(call, check, collect an advance) and the case file does not show it done, do not agree. '
            .'Count kal, porshu or a weekday from the day that line was written, not from today; '
            .'a promised day that has already passed is not a reason to act without calling. '
            .'confidence = probability the action is right. Answer reason_bn in short Bangla.';
    }

    /**
     * @param  array<string, mixed>  $input  context, memory, proposal
     */
    public function judgeUser(array $input): string
    {
        $context = (array) ($input['context'] ?? []);

        return "Case file (JSON):\n".$this->json($context)
            ."\n\nTimeline, oldest first (relative words in a line count from that line's own day):\n"
            .$this->timelineText($context)
            ."\nToday is ".($context['today'] ?? '').' ('.($context['weekday'] ?? '').').'
            ."\n\nStore memory (JSON):\n".$this->json($this->memory((array) ($input['memory'] ?? [])))
            ."\n\nProposed automatic action (JSON):\n".$this->json((array) ($input['proposal'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function caseSchema(): array
    {
        $reasonCodes = ReturnAiCatalog::reasonCodes();
        $flagCodes = ReturnAiCatalog::modelFlagCodes();
        $str = ['type' => 'string'];
        $bool = ['type' => 'boolean'];
        $object = static fn (array $properties) => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties,
        ];

        return $object([
            'decision' => ['type' => 'string', 'enum' => ['request_resend', 'confirm_cancel', 'needs_human']],
            'confidence' => ['type' => 'number'],
            'reason_code' => ['type' => 'string', 'enum' => array_values($reasonCodes)],
            'summary_bn' => $str,
            'rider_note_trust' => ['type' => 'string', 'enum' => ['trusted', 'doubtful', 'untrusted', 'no_rider_note']],
            'suspicion_flags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_values($flagCodes)]],
            'suspicion_reason_bn' => $str,
            'evidence' => [
                'type' => 'array',
                'items' => $object([
                    'source' => ['type' => 'string', 'enum' => ['note', 'call', 'return_reason', 'history']],
                    'ref' => $str,
                    'quote' => $str,
                ]),
            ],
            'customer_promise' => $object(['has_promise' => $bool, 'phrase' => $str, 'date' => $str]),
            'call_plan' => $object([
                'call_customer' => $bool,
                'call_rider' => $bool,
                'first' => ['type' => 'string', 'enum' => ['customer', 'rider', 'none']],
                'when_bn' => $str,
                'why_bn' => $str,
                'customer_script_bn' => $str,
                'rider_script_bn' => $str,
            ]),
            'note_to_rider_bn' => $str,
            'handling_tips_bn' => ['type' => 'array', 'items' => $str],
            'journey' => ['type' => 'array', 'items' => $object(['ref' => $str, 'text_bn' => $str])],
            'memory_refs' => ['type' => 'array', 'items' => $str],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function judgeSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['agree', 'confidence', 'reason_bn'],
            'properties' => [
                'agree' => ['type' => 'boolean'],
                'confidence' => ['type' => 'number'],
                'reason_bn' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $memory
     * @return list<array{id: string, when: string, then: string}>
     */
    private function memory(array $memory): array
    {
        $out = [];
        foreach ($memory as $item) {
            if (is_array($item) && trim((string) ($item['then'] ?? '')) !== '') {
                $out[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'when' => trim((string) ($item['when'] ?? '')),
                    'then' => trim((string) $item['then']),
                ];
            }
        }

        return $out;
    }

    private function json(array $value): string
    {
        return ReturnAiText::maskPhones((string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ));
    }
}
