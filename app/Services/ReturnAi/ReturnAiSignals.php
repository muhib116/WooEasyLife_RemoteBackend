<?php

namespace App\Services\ReturnAi;

/**
 * What the desk can prove without the model: portal lines, rider-note
 * suspicion, the customer's promised date and whether quoted evidence is real.
 */
class ReturnAiSignals
{
    private const REACHED = ['answered', 'completed', 'connected', 'received', 'success'];

    /**
     * Normalise the store's case file: portal lines are marked here, the history
     * line is written here, and every text is masked and clipped again.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function prepare(array $context): array
    {
        $parcel = (array) ($context['parcel'] ?? []);
        $parcel['return_reason'] = ReturnAiText::clip(ReturnAiText::maskPhones((string) ($parcel['return_reason'] ?? '')), 300);
        $parcel['courier_status'] = $this->normalizeStatus((string) ($parcel['courier_status'] ?? ''));
        $context['parcel'] = $parcel;

        $notes = [];
        foreach (array_slice((array) ($context['notes'] ?? []), -40) as $note) {
            if (! is_array($note)) {
                continue;
            }
            $text = ReturnAiText::clip(ReturnAiText::maskPhones((string) ($note['text'] ?? '')));
            if ($text === '') {
                continue;
            }
            $notes[] = [
                'id' => ReturnAiText::clip((string) ($note['id'] ?? ''), 20),
                'at' => ReturnAiText::clip((string) ($note['at'] ?? ''), 25),
                'type' => ReturnAiText::key($note['type'] ?? ''),
                'source' => ReturnAiText::key($note['source'] ?? ''),
                'system_line' => $this->isPortalLine((string) ($note['text'] ?? '')),
                'text' => $text,
            ];
        }
        $context['notes'] = $notes;

        $calls = [];
        foreach (array_slice((array) ($context['calls'] ?? []), -15) as $call) {
            if (! is_array($call)) {
                continue;
            }
            $calls[] = [
                'id' => ReturnAiText::clip((string) ($call['id'] ?? ''), 20),
                'at' => ReturnAiText::clip((string) ($call['at'] ?? ''), 25),
                'to' => in_array($call['to'] ?? '', ['customer', 'rider'], true) ? $call['to'] : 'other',
                'direction' => ReturnAiText::key($call['direction'] ?? 'outbound'),
                'status' => ReturnAiText::key($call['status'] ?? ''),
                'duration_seconds' => max(0, (int) ($call['duration_seconds'] ?? 0)),
                'note' => ReturnAiText::clip(ReturnAiText::maskPhones((string) ($call['note'] ?? '')), 300),
            ];
        }
        $context['calls'] = $calls;

        $customer = (array) ($context['customer'] ?? []);
        $courier = is_array($customer['courier_history'] ?? null) ? $customer['courier_history'] : null;
        $store = is_array($customer['store_history'] ?? null) ? $customer['store_history'] : null;
        $context['customer'] = [
            'courier_history' => $courier,
            'store_history' => $store,
            'summary' => $this->historySummary($courier, $store),
        ];

        $context['today'] = ReturnAiText::day((string) ($context['today'] ?? ''))?->format('Y-m-d')
            ?? now('Asia/Dhaka')->format('Y-m-d');
        $context['weekday'] = ReturnAiText::day($context['today'])?->format('l') ?? '';

        return $context;
    }

    /**
     * Lines the SteadFast portal writes on its own (routing, status, the
     * merchant's own resend/cancel requests). Facts, never a rider's reason.
     */
    public function isPortalLine(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return true;
        }

        foreach ([
            '/^Consignment created/i',
            '/^Consignment has been received at/i',
            '/received at .+WAREHOUSE/i',
            '/^Changes:\s*/i',
            '/By User\s+/i',
            '/By Sorter\s+/i',
            '/Sender\s*\(\s*API\s*\)/i',
            '/^Parcel (picked|delivered|returned)/i',
            '/^Status (updated|changed)/i',
        ] as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/^(?:Consignment\s+(?:sent\s+to|has\s+been\s+(?:resent|cancelled|returned|delivered|received|assigned|picked)|status\s+has\s+been\s+updated)'
            .'|Cancellation\s+Request\s+has\s+been\s+sent'
            .'|Sender\s+requested\s+to\s+(?:resend|return|cancel))'
            .'|Dispatch\s+ID\s*:/i',
            $text
        );
    }

    /**
     * Rider / courier lines a person wrote.
     *
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function riderNotes(array $context): array
    {
        return array_values(array_filter((array) ($context['notes'] ?? []), static function ($note): bool {
            return is_array($note)
                && empty($note['system_line'])
                && in_array((string) ($note['type'] ?? ''), ['rider_note', 'unknown', 'status', ''], true);
        }));
    }

    public function claimsUnreachable(string $text): bool
    {
        return (bool) preg_match(
            '/(rec(?:e|ie)ive?\s*kor[eo]?\s*na|recive|রিসিভ\s*কর[েো]?\s*না|ধর[েো]?\s*না|dhor[eo]?\s*na|phone\s*(?:off|bondho|bandh)|ফোন\s*বন্ধ|switch(?:ed)?\s*off|not\s*reachable|unreachable|no\s*answer|ফোনে\s*পাওয়া\s*যা(?:চ্ছে|য়)\s*না|phone\s*e\s*pawa\s*jay\s*na|call\s*(?:e\s*)?pai\s*na|number\s*(?:off|bondho)|নাম্বার\s*বন্ধ)/iu',
            $text
        );
    }

    public function claimsRefusal(string $text): bool
    {
        return (bool) preg_match(
            '/(nib[eo]?\s*na|niben?a|নিব[েো]?\s*না|নেব[েো]?\s*না|নিবেনা|নেবেনা|lagbe\s*na|লাগবে\s*না|cha(?:y|i)\s*na|চায়\s*না|চাই\s*না|order\s*kor[ei]\s*na|অর্ডার\s*কর[েি]\s*নাই|অর্ডার\s*করেনি|refus|cancel|ক্যান্সেল|ক্যানসেল|বাতিল)/iu',
            $text
        );
    }

    public function claimsShortPayment(string $text): bool
    {
        return (bool) (
            preg_match('/\d+\s*(?:taka|tk|টাকা|৳)?\s*(?:kom|কম|less|কমে|কমিয়ে|discount|ছাড়|ছাড়)/iu', $text)
            || preg_match('/(?:kom|কম)\s*(?:diye|দিয়ে|dibe|দিবে|dite|দিতে|nibe|নিবে|rakh|রাখ)/iu', $text)
            || preg_match('/(?:taka|টাকা)\s*(?:kom|কম)/iu', $text)
        );
    }

    public function claimsPartial(string $text): bool
    {
        return (bool) preg_match('/(partial|আংশিক|half\s*payment|অর্ধেক|ekta\s*nise|একটা\s*নিয়েছে)/iu', $text);
    }

    public function claimsAddress(string $text): bool
    {
        return (bool) preg_match(
            '/(address|ঠিকানা|location|লোকেশন|basha|bashay|বাসা)\s*(?:\S+\s*){0,2}(khuj[ei]|খুঁজে|খুজে|pai\s*ni|পাই\s*নি|pai\s*na|পাই\s*না|wrong|ভুল|incomplete|অসম্পূর্ণ)/iu',
            $text
        );
    }

    public function normalizeStatus(string $status): string
    {
        return ReturnAiText::key(preg_replace('/[\s-]+/', '_', (string) preg_replace('/^order\./', '', strtolower(trim($status)))));
    }

    /**
     * A courier status that means the parcel already reached the customer.
     */
    public function deliveredStatus(string $status): string
    {
        $raw = $this->normalizeStatus($status);
        $raw = [
            'paid' => 'delivered',
            'complete' => 'delivered',
            'completed' => 'delivered',
            'delivered_to_customer' => 'delivered',
            'partial_delivery' => 'partial_delivered',
            'partially_delivered' => 'partial_delivered',
        ][$raw] ?? $raw;

        return in_array($raw, ReturnAiCatalog::deliveredStatuses(), true) ? $raw : '';
    }

    /**
     * Suspicion the hub can prove from notes, calls and history alone.
     *
     * @param  array<string, mixed>  $context  Prepared case file.
     * @return list<array<string, mixed>>
     */
    public function detectFlags(array $context): array
    {
        $flags = [];
        $riderNotes = $this->riderNotes($context);
        $calls = (array) ($context['calls'] ?? []);
        $history = is_array($context['customer']['courier_history'] ?? null) ? $context['customer']['courier_history'] : null;
        $reason = (string) ($context['parcel']['return_reason'] ?? '');

        $texts = array_map(static fn ($n) => (string) $n['text'], $riderNotes);
        if ($reason !== '') {
            $texts[] = $reason;
        }

        $unreachableNotes = array_values(array_filter($riderNotes, fn ($n) => $this->claimsUnreachable((string) $n['text'])));
        $unreachable = $unreachableNotes !== [] || $this->claimsUnreachable($reason);
        $refusal = (bool) array_filter($texts, fn ($t) => $this->claimsRefusal($t));

        if ($unreachable) {
            $customerCalls = array_values(array_filter($calls, static fn ($c) => ($c['to'] ?? '') === 'customer'));
            $reached = array_values(array_filter($customerCalls, static function ($c): bool {
                return in_array((string) ($c['status'] ?? ''), self::REACHED, true) || (int) ($c['duration_seconds'] ?? 0) >= 15;
            }));

            $near = [];
            foreach ($reached as $call) {
                $callTs = ReturnAiText::ts($call['at'] ?? '');
                foreach ($unreachableNotes ?: [['at' => $context['parcel']['requested_at'] ?? '']] as $note) {
                    $noteTs = ReturnAiText::ts($note['at'] ?? '');
                    if ($callTs && $noteTs && abs($callTs - $noteTs) <= 3 * 86400) {
                        $near[] = (string) $call['id'];
                    }
                }
            }

            if ($near !== []) {
                $flags[] = ReturnAiCatalog::flag('merchant_reached_customer', 'rules', 'কল: '.implode(', ', array_unique($near)));
            } elseif ($customerCalls === []) {
                $flags[] = ReturnAiCatalog::flag('unreachable_claim_unverified');
            }
        }

        if (($unreachable || $refusal) && $history && (int) ($history['total'] ?? 0) >= 3 && (float) ($history['success_rate'] ?? 0) >= 80) {
            $flags[] = ReturnAiCatalog::flag(
                'good_history_contradicts_refusal',
                'rules',
                sprintf('%d অর্ডারে %d ডেলিভারড', (int) $history['total'], (int) ($history['delivered'] ?? 0))
            );
        }

        if (array_filter($texts, fn ($t) => $this->claimsShortPayment($t))) {
            $flags[] = ReturnAiCatalog::flag('cod_short_payment_claim');
        }
        if (array_filter($texts, fn ($t) => $this->claimsPartial($t))) {
            $flags[] = ReturnAiCatalog::flag('partial_delivery_claim');
        }
        if (array_filter($texts, fn ($t) => $this->claimsAddress($t))) {
            $flags[] = ReturnAiCatalog::flag('address_claim_unverified');
        }

        $seen = [];
        foreach ($riderNotes as $note) {
            $key = mb_strtolower((string) preg_replace('/[\s\p{P}]+/u', ' ', (string) $note['text']));
            if (mb_strlen(trim($key)) < 6) {
                continue;
            }
            $seen[$key][substr((string) $note['at'], 0, 10)] = true;
            if (count($seen[$key]) >= 2) {
                $flags[] = ReturnAiCatalog::flag('template_note_repeated');
                break;
            }
        }

        $delivered = $this->deliveredStatus((string) ($context['parcel']['courier_status'] ?? ''));
        if ($delivered !== '') {
            $flags[] = ReturnAiCatalog::flag('already_delivered', 'rules', str_replace('_', ' ', $delivered));
        }

        if ($riderNotes === [] && $reason === '' && $calls === []) {
            $flags[] = ReturnAiCatalog::flag('no_information');
        }

        return $flags;
    }

    /**
     * The calendar day a customer promised to take the parcel, from one line.
     * Relative words count from the day the line was written, so "kal nibo"
     * written on the 26th means the 27th.
     *
     * @return array{date: string, phrase: string}|null
     */
    public function parsePromiseDate(string $text, string $baseYmd): ?array
    {
        $base = ReturnAiText::day($baseYmd);
        if (! $base) {
            return null;
        }

        $t = mb_strtolower(ReturnAiText::latinDigits($text));

        // A date alone is not a promise; it needs taking / delivery intent.
        if (! preg_match('/(nib|nebo|nebe|neb|নিব|নেব|নিতে|nite|neya|নেয়া|নেওয়া|deliver|ডেলিভারি|dite|দিতে|diben|দিবেন|receive|রিসিভ|pathan|পাঠান|asben|আসবেন|thakbe|থাকবে|thakbo|থাকবো|available|bari|বাড়ি|basay|বাসায়|পাঠাতে|pathate)/u', $t)) {
            return null;
        }

        $nb = '(?<![\p{L}\p{M}])';
        $ne = '(?![\p{L}\p{M}])';

        if (preg_match('/(\d{1,2})\s*(?:din|দিন|days?)\s*(?:por|পর|pore|পরে|after|later)/u', $t, $m)) {
            $days = (int) $m[1];
            if ($days >= 1 && $days <= 45) {
                return ['date' => $base->modify("+{$days} days")->format('Y-m-d'), 'phrase' => $m[0]];
            }
        }

        if (preg_match('/(porshu|porsu|পরশু|day after tomorrow)/u', $t, $m)) {
            return ['date' => $base->modify('+2 days')->format('Y-m-d'), 'phrase' => $m[0]];
        }

        foreach ([
            'sunday' => '(?:রবিবার|রোববার|robibar|robi\s*bar|rabibar|robbar|sunday)',
            'monday' => '(?:সোমবার|sombar|shombar|som\s*bar|monday)',
            'tuesday' => '(?:মঙ্গলবার|mongolbar|mongol\s*bar|monglbar|tuesday)',
            'wednesday' => '(?:বুধবার|budhbar|budhobar|budh\s*bar|wednesday)',
            'thursday' => '(?:বৃহস্পতিবার|বিষ্যুদবার|brihospotibar|brihoshpotibar|bishudbar|bisshudbar|thursday)',
            'friday' => '(?:শুক্রবার|shukrobar|sukrobar|shukro\s*bar|friday)',
            'saturday' => '(?:শনিবার|shonibar|sonibar|shoni\s*bar|saturday)',
        ] as $day => $pattern) {
            if (preg_match('/'.$pattern.'/u', $t, $m)) {
                return ['date' => $base->modify('next '.$day)->format('Y-m-d'), 'phrase' => $m[0]];
            }
        }

        $nextMonth = '(?:agami|আগামী|samner|সামনের|next)\s*(?:maser|মাসের|month)';
        if (preg_match('/('.$nextMonth.'\s*(\d{1,2})|(\d{1,2})\s*(?:st|nd|rd|th)?\s*(?:tarikh|tarik|তারিখ|তারিখে|date)?\s*of\s*next\s*month)/u', $t, $m)) {
            $day = (int) ($m[2] !== '' ? $m[2] : ($m[3] ?? 0));
            if ($day >= 1 && $day <= 31) {
                $first = $base->modify('first day of next month');

                return ['date' => $first->setDate((int) $first->format('Y'), (int) $first->format('m'), min($day, (int) $first->format('t')))->format('Y-m-d'), 'phrase' => $m[0]];
            }
        }

        if (preg_match('/'.$nb.'(\d{1,2})\s*(?:tarikh|tarik|তারিখ|তারিখে|tarikhe|st|nd|rd|th)'.$ne.'/u', $t, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                $month = (int) $base->format('j') < $day ? $base->modify('first day of this month') : $base->modify('first day of next month');

                return ['date' => $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min($day, (int) $month->format('t')))->format('Y-m-d'), 'phrase' => $m[0]];
            }
        }

        if (preg_match('/(agamikal|agami\s*kal|আগামীকাল|আগামী\s*কাল|tomorrow|kalke|কালকে|'.$nb.'kal'.$ne.'|'.$nb.'কাল'.$ne.')/u', $t, $m)) {
            return ['date' => $base->modify('+1 day')->format('Y-m-d'), 'phrase' => $m[0]];
        }

        return null;
    }

    /**
     * The newest promise in rider notes, call notes or the cancel reason.
     *
     * @param  array<string, mixed>  $context
     * @return array{date: string, phrase: string, ref: string}|null
     */
    public function findPromise(array $context): ?array
    {
        $lines = [];
        foreach ($this->riderNotes($context) as $note) {
            $lines[] = ['ref' => $note['id'], 'at' => $note['at'], 'text' => $note['text']];
        }
        foreach ((array) ($context['calls'] ?? []) as $call) {
            if (($call['note'] ?? '') !== '') {
                $lines[] = ['ref' => $call['id'], 'at' => $call['at'], 'text' => $call['note']];
            }
        }
        if (($context['parcel']['return_reason'] ?? '') !== '') {
            $lines[] = [
                'ref' => 'return_reason',
                'at' => (string) (($context['parcel']['requested_at'] ?? '') ?: ($context['today'] ?? '')),
                'text' => (string) $context['parcel']['return_reason'],
            ];
        }

        usort($lines, static fn ($a, $b) => ReturnAiText::ts($b['at']) <=> ReturnAiText::ts($a['at']));

        foreach ($lines as $line) {
            $base = substr((string) $line['at'], 0, 10) ?: (string) ($context['today'] ?? '');
            $found = $this->parsePromiseDate((string) $line['text'], $base);
            if ($found) {
                return $found + ['ref' => (string) $line['ref']];
            }
        }

        return null;
    }

    public function normalizeQuote(string $text): string
    {
        $text = str_replace(['“', '”', '‘', '’', '"', "'", '…'], '', ReturnAiText::latinDigits($text));

        return trim(mb_strtolower((string) preg_replace('/\s+/u', ' ', $text)));
    }

    /**
     * Each evidence item is verified only when its quote is in the case file.
     *
     * @param  array<int, mixed>  $evidence
     * @param  array<string, mixed>  $context  Prepared case file.
     * @return list<array{source: string, ref: string, quote: string, verified: bool}>
     */
    public function verifyEvidence(array $evidence, array $context): array
    {
        $texts = [
            'return_reason' => (string) ($context['parcel']['return_reason'] ?? ''),
            'history' => (string) ($context['customer']['summary'] ?? ''),
        ];
        foreach ((array) ($context['notes'] ?? []) as $note) {
            $texts[(string) $note['id']] = (string) $note['text'];
        }
        foreach ((array) ($context['calls'] ?? []) as $call) {
            $texts[(string) $call['id']] = (string) $call['note'];
        }

        $out = [];
        foreach ($evidence as $item) {
            if (! is_array($item)) {
                continue;
            }
            $source = ReturnAiText::key($item['source'] ?? '');
            $ref = trim((string) ($item['ref'] ?? ''));
            if ($source === 'return_reason' || $source === 'history') {
                $ref = $source;
            }
            $quote = trim((string) ($item['quote'] ?? ''));
            $haystack = $texts[$ref] ?? '';
            $needle = $this->normalizeQuote($quote);

            $out[] = [
                'source' => in_array($source, ['note', 'call', 'return_reason', 'history'], true) ? $source : 'note',
                'ref' => $ref,
                'quote' => ReturnAiText::clip($quote, 200),
                'verified' => mb_strlen($needle) >= 3
                    && $haystack !== ''
                    && mb_strpos($this->normalizeQuote($haystack), $needle) !== false,
            ];
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * Keyword guess used when the model cannot run.
     *
     * @return array{reason_code: string, confidence: float, summary_bn: string}
     */
    public function guessReason(string $message): array
    {
        $text = mb_strtolower(trim($message));
        if ($text === '') {
            return ['reason_code' => 'unknown', 'confidence' => 0.0, 'summary_bn' => ''];
        }
        if ($this->isPortalLine($message)) {
            return ['reason_code' => 'other', 'confidence' => 0.92, 'summary_bn' => 'সিস্টেম/পোর্টাল আপডেট — ডেলিভারি ব্যর্থতার কারণ নয়'];
        }

        foreach ([
            'unreachable' => ['receive kore na', 'receive kora nai', 'ফোন ধরে না', 'ফোনে পাওয়া', 'unreachable', 'phone off', 'কল রিসিভ', 'নাম্বর বন্ধ'],
            'wrong_address' => ['ঠিকানা ভুল', 'address wrong', 'ঠিকানা পাই না', 'location পাই', 'বাসা খুঁজে'],
            'changed_mind' => ['নেবো না', 'নিব না', 'চাই না', 'cancel', 'ক্যান্সেল', 'মত পরিবর্তন', 'changed mind', 'আর লাগবে না'],
            'reschedule' => ['কাল নিব', 'পরে নিব', 'আজ না', 'reschedule', 'পরশু', 'সময় মিলছে না'],
            'price_dispute' => ['দাম', 'price', 'টাকা বেশি', 'cod বেশি', 'কম রাখ'],
            'payment_issue' => ['পেমেন্ট', 'payment', 'টাকা নেই', 'ক্যাশ নাই'],
            'product_issue' => ['প্রোডাক্ট', 'product', 'ভুল জিনিস', 'কোয়ালিটি'],
            'rider_issue' => ['রাইডার', 'rider', 'ডেলিভারিম্যান', 'ঘোরায়', 'time নষ্ট'],
        ] as $code => $needles) {
            foreach ($needles as $needle) {
                if (mb_strpos($text, mb_strtolower($needle)) !== false) {
                    return ['reason_code' => $code, 'confidence' => 0.62, 'summary_bn' => ReturnAiCatalog::reasonLabel($code)];
                }
            }
        }

        return ['reason_code' => 'unknown', 'confidence' => 0.35, 'summary_bn' => 'অস্পষ্ট নোট'];
    }

    /**
     * One quotable line for customer history evidence.
     *
     * @param  array<string, mixed>|null  $courier
     * @param  array<string, mixed>|null  $store
     */
    public function historySummary(?array $courier, ?array $store): string
    {
        $parts = [];
        if ($courier && (int) ($courier['total'] ?? 0) > 0) {
            $rate = $courier['success_rate'] ?? null;
            $parts[] = sprintf(
                'courier history: %d orders, %d delivered, %d cancelled, success %s%%',
                (int) $courier['total'],
                (int) ($courier['delivered'] ?? 0),
                (int) ($courier['cancel'] ?? 0),
                $rate === null ? '?' : rtrim(rtrim(number_format((float) $rate, 1, '.', ''), '0'), '.')
            );
        } else {
            $parts[] = 'courier history: none';
        }
        if ($store) {
            $parts[] = sprintf('this store: %d orders, %d completed', (int) ($store['orders'] ?? 0), (int) ($store['completed'] ?? 0));
        }

        return implode('; ', $parts);
    }
}
