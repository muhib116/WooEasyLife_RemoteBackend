<?php

namespace App\WiseAi;

/**
 * Wise AI perception + reasoning seed (pattern layer).
 *
 * Social intents may reply without merchant knowledge.
 * Business intents must be grounded by KnowledgeResolver (evidence rule).
 */
class DecideEngine
{
    public const BRAIN_VERSION = '0.6.0';

    /** @var list<string> */
    public const SOCIAL_INTENTS = ['greeting', 'thanks', 'ack'];

    /** @var list<string> */
    public const BUSINESS_INTENTS = ['price', 'delivery', 'order_status', 'complaint'];

    /**
     * @var array<string, array{patterns: list<string>, confidence: int, reply: ?string, kind: string}>
     */
    private const INTENTS = [
        'greeting' => [
            'patterns' => [
                'আসসালামু', 'সালাম', 'হ্যালো', 'হাই',
                'assalamu', 'salam', 'hello', 'hlw',
            ],
            'confidence' => 85,
            'reply' => 'আসসালামু আলাইকুম! কীভাবে সাহায্য করতে পারি?',
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
    ];

    /**
     * @return array{intent: string, confidence: int, kind: string, social_reply: ?string}
     */
    public function classify(string $text): array
    {
        $normalized = $this->normalize($text);

        // Word-boundary "hi" (avoid matching inside words like "this").
        if ($normalized === 'hi' || preg_match('/(?:^|\\s)hi(?:\\s|$|[!?.,])/u', $normalized) === 1) {
            return [
                'intent' => 'greeting',
                'confidence' => 85,
                'kind' => 'social',
                'social_reply' => self::INTENTS['greeting']['reply'],
            ];
        }

        // Bare "dam" (not "damaged") — common Banglish price ask.
        if ($normalized === 'dam' || preg_match('/(?:^|\\s)dam(?:\\s|$|[!?.,])/u', $normalized) === 1) {
            return [
                'intent' => 'price',
                'confidence' => 78,
                'kind' => 'business',
                'social_reply' => null,
            ];
        }

        // Bare okay/ok after Language emoji→text (👍). Never substring-match "ok" inside tokens (bjyulok).
        if (
            $normalized === 'okay'
            || $normalized === 'ok'
            || preg_match('/(?:^|\\s)okay(?:\\s|$|[!?.,])/u', $normalized) === 1
            || preg_match('/(?:^|\\s)ok(?:\\s|$|[!?.,])/u', $normalized) === 1
        ) {
            return [
                'intent' => 'ack',
                'confidence' => 82,
                'kind' => 'social',
                'social_reply' => self::INTENTS['ack']['reply'],
            ];
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
                if (str_contains($normalized, $p)) {
                    return [
                        'intent' => $intent,
                        'confidence' => $config['confidence'],
                        'kind' => $config['kind'],
                        'social_reply' => $config['reply'],
                    ];
                }
            }
        }

        return [
            'intent' => 'unknown',
            'confidence' => 0,
            'kind' => 'business',
            'social_reply' => null,
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
