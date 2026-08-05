<?php

namespace App\WiseAi;

/**
 * Wise AI perception + reasoning seed (pattern layer).
 *
 * Social intents may reply without merchant knowledge.
 * Business intents must be grounded by KnowledgeResolver (evidence rule).
 *
 * Sprint 1: score all pattern hits; business beats social on mixed utterances.
 */
class DecideEngine
{
    public const BRAIN_VERSION = '0.6.4';

    /** Casual hi/hello — never wa-alaikum. */
    public const REPLY_CASUAL_GREETING = 'হ্যালো! কীভাবে সাহায্য করতে পারি?';

    /** Response to salam / assalamu alaikum only. */
    public const REPLY_SALAM_GREETING = 'ওয়ালাইকুম আসসালাম! কীভাবে সাহায্য করতে পারি?';

    /** @var list<string> */
    public const SOCIAL_INTENTS = ['greeting', 'thanks', 'ack'];

    /** @var list<string> */
    private const SALAM_GREETING_PATTERNS = [
        'আসসালামু', 'সালাম', 'assalamu', 'salam', 'assalamualaikum',
    ];

    /** @var list<string> */
    public const BUSINESS_INTENTS = [
        'price',
        'delivery',
        'order_status',
        'complaint',
        'payment',
        'cod',
        'stock',
    ];

    /**
     * @var array<string, array{patterns: list<string>, confidence: int, reply: ?string, kind: string}>
     */
    private const INTENTS = [
        'greeting' => [
            // Casual vs salam replies are chosen in classify() — never one salam line for "hi".
            'patterns' => [
                'আসসালামু', 'সালাম', 'হ্যালো', 'হাই',
                'assalamu', 'salam', 'hello', 'hlw',
            ],
            'confidence' => 85,
            'reply' => self::REPLY_CASUAL_GREETING,
            'kind' => 'social',
        ],
        'price' => [
            'patterns' => [
                'দাম', 'কত টাকা', 'প্রাইস', 'মূল্য',
                'dam koto', 'price koto', 'etar dam', 'koto niben',
                'koto taka', 'price', 'cost', 'rate', 'mullo', 'koto?', 'kotu',
            ],
            'confidence' => 80,
            'reply' => null,
            'kind' => 'business',
        ],
        'delivery' => [
            'patterns' => [
                'ডেলিভারি', 'কুরিয়ার', 'কতদিনে', 'চার্জ', 'পাঠাবেন',
                'delivery', 'courier', 'charge', 'kotodin', 'shipping',
            ],
            'confidence' => 78,
            'reply' => null,
            'kind' => 'business',
        ],
        'order_status' => [
            'patterns' => [
                'অর্ডার কই', 'অর্ডারের খবর', 'ট্র্যাক', 'কবে পাবো', 'পার্সেল',
                'order koi', 'track', 'kobe pabo', 'parcel', 'order status',
            ],
            'confidence' => 75,
            'reply' => null,
            'kind' => 'business',
        ],
        'thanks' => [
            'patterns' => [
                'ধন্যবাদ', 'থ্যাংক', 'শুকরিয়া',
                'thanks', 'thank you', 'tnx', 'dhonnobad',
            ],
            'confidence' => 85,
            'reply' => 'আপনাকেও ধন্যবাদ! আর কিছু জানার থাকলে বলবেন।',
            'kind' => 'social',
        ],
        'ack' => [
            'patterns' => [
                'okay', 'ok', 'thik', 'ঠিক আছে',
                'alright', 'got it',
            ],
            'confidence' => 80,
            'reply' => 'ঠিক আছে! আর কিছু জানার থাকলে বলবেন।',
            'kind' => 'social',
        ],
        'complaint' => [
            'patterns' => [
                'ভাঙা', 'নষ্ট', 'খারাপ', 'রিটার্ন', 'ফেরত', 'অভিযোগ',
                'broken', 'damaged', 'return', 'refund', 'complain',
            ],
            'confidence' => 80,
            'reply' => null,
            'kind' => 'business',
        ],
        'payment' => [
            'patterns' => [
                'পেমেন্ট', 'পেমেন্ট মেথড', 'কীভাবে পেমেন্ট', 'কিভাবে পেমেন্ট',
                'bkash', 'bKash', 'nagad', 'rocket', 'বিকাশ', 'নগদ', 'রকেট',
                'payment', 'pay how', 'payment method', 'কিভাবে দিব',
            ],
            'confidence' => 78,
            'reply' => null,
            'kind' => 'business',
        ],
        'cod' => [
            'patterns' => [
                'ক্যাশ অন ডেলিভারি', 'ক্যাশঅন', 'ক্যাশ অন',
                'cod', 'cash on delivery', 'cash on',
            ],
            'confidence' => 82,
            'reply' => null,
            'kind' => 'business',
        ],
        'stock' => [
            'patterns' => [
                'স্টক', 'আছে কি', 'আছে?', 'কয়টা আছে', 'কতগুলো আছে',
                'stock', 'available', 'in stock', 'ache?', 'ache ki', 'koita ache',
                'সাইজ আছে', 'size ache', 'out of stock',
            ],
            'confidence' => 76,
            'reply' => null,
            'kind' => 'business',
        ],
    ];

    /**
     * @return array{intent: string, confidence: int, kind: string, social_reply: ?string}
     */
    public function classify(string $text): array
    {
        $normalized = $this->normalize($text);
        /** @var list<array{intent: string, confidence: int, kind: string, social_reply: ?string, score: int}> $hits */
        $hits = [];

        // Word-boundary "hi" (avoid matching inside words like "this").
        if ($normalized === 'hi' || preg_match('/(?:^|\\s)hi(?:\\s|$|[!?.,])/u', $normalized) === 1) {
            $hits[] = $this->hit('greeting', 85, 'social', self::REPLY_CASUAL_GREETING, 85);
        }

        // Bare "dam" (not "damaged") — common Banglish price ask.
        if ($normalized === 'dam' || preg_match('/(?:^|\\s)dam(?:\\s|$|[!?.,])/u', $normalized) === 1) {
            $hits[] = $this->hit('price', 78, 'business', null, 90);
        }

        // Bare okay/ok after Language emoji→text (👍). Never substring-match "ok" inside tokens.
        if (
            $normalized === 'okay'
            || $normalized === 'ok'
            || preg_match('/(?:^|\\s)okay(?:\\s|$|[!?.,])/u', $normalized) === 1
            || preg_match('/(?:^|\\s)ok(?:\\s|$|[!?.,])/u', $normalized) === 1
        ) {
            $hits[] = $this->hit('ack', 82, 'social', self::INTENTS['ack']['reply'], 82);
        }

        foreach (self::INTENTS as $intent => $config) {
            foreach ($config['patterns'] as $pattern) {
                $p = $this->normalize($pattern);
                if ($p === '') {
                    continue;
                }
                // Skip short "ok" here — handled with word boundaries above.
                if ($intent === 'ack' && ($p === 'ok' || $p === 'okay')) {
                    continue;
                }
                if (! str_contains($normalized, $p)) {
                    continue;
                }
                $specificity = min(20, mb_strlen($p));
                $score = (int) $config['confidence'] + $specificity;
                $reply = $config['reply'];
                if ($intent === 'greeting') {
                    $reply = $this->isSalamGreetingPattern($p)
                        ? self::REPLY_SALAM_GREETING
                        : self::REPLY_CASUAL_GREETING;
                    // Prefer salam reply flavor when both casual + salam match (e.g. "salam hi").
                    if ($this->isSalamGreetingPattern($p)) {
                        $score += 5;
                    }
                }
                $hits[] = $this->hit(
                    $intent,
                    (int) $config['confidence'],
                    (string) $config['kind'],
                    $reply,
                    $score,
                );
            }
        }

        if ($hits === []) {
            return [
                'intent' => 'unknown',
                'confidence' => 0,
                'kind' => 'business',
                'social_reply' => null,
            ];
        }

        // Deduplicate by intent — keep highest score per intent.
        $byIntent = [];
        foreach ($hits as $hit) {
            $key = $hit['intent'];
            if (! isset($byIntent[$key]) || $hit['score'] > $byIntent[$key]['score']) {
                $byIntent[$key] = $hit;
            }
        }
        $unique = array_values($byIntent);

        $business = array_values(array_filter($unique, static fn ($h) => $h['kind'] === 'business'));
        $pool = $business !== [] ? $business : $unique;

        usort($pool, function (array $a, array $b): int {
            // Higher composite score first.
            $byScore = $b['score'] <=> $a['score'];
            if ($byScore !== 0) {
                return $byScore;
            }
            // Equal score → higher base confidence (cod 82 beats stock 76).
            $byConf = $b['confidence'] <=> $a['confidence'];
            if ($byConf !== 0) {
                return $byConf;
            }

            return $this->intentOrder($a['intent'], $a['kind'])
                <=> $this->intentOrder($b['intent'], $b['kind']);
        });
        $best = $pool[0];

        return [
            'intent' => $best['intent'],
            'confidence' => $best['confidence'],
            'kind' => $best['kind'],
            'social_reply' => $best['social_reply'],
        ];
    }

    /** Stable rank for equal score+confidence (lower wins). */
    private function intentOrder(string $intent, string $kind): int
    {
        $list = $kind === 'social' ? self::SOCIAL_INTENTS : self::BUSINESS_INTENTS;
        $idx = array_search($intent, $list, true);

        return $idx === false ? 1000 + ord($intent[0] ?? 'z') : (int) $idx;
    }

    private function isSalamGreetingPattern(string $normalizedPattern): bool
    {
        foreach (self::SALAM_GREETING_PATTERNS as $salam) {
            if ($normalizedPattern === $this->normalize($salam)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{intent: string, confidence: int, kind: string, social_reply: ?string, score: int}
     */
    private function hit(string $intent, int $confidence, string $kind, ?string $socialReply, int $score): array
    {
        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'kind' => $kind,
            'social_reply' => $socialReply,
            'score' => $score,
        ];
    }

    /**
     * Backward-compatible helper used by tests/callers that only need a decision-shaped array
     * without knowledge grounding (prefer TurnRunner for production).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function decide(string $text, array $context = []): array
    {
        $classified = $this->classify($text);

        if ($classified['kind'] === 'social' && $classified['social_reply'] !== null) {
            return [
                'intent' => $classified['intent'],
                'confidence' => $classified['confidence'],
                'action' => 'suggest_reply',
                'suggested_reply' => $classified['social_reply'],
                'source' => 'pattern',
                'brain_version' => self::BRAIN_VERSION,
                'gap' => false,
            ];
        }

        // Without knowledge grounding, business facts must not be invented.
        return [
            'intent' => $classified['intent'],
            'confidence' => $classified['confidence'],
            'action' => 'needs_human',
            'suggested_reply' => null,
            'source' => 'pattern',
            'brain_version' => self::BRAIN_VERSION,
            'gap' => true,
        ];
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return (string) preg_replace('/\s+/u', ' ', $text);
    }
}
