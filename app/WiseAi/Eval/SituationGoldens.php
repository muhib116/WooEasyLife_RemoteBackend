<?php

namespace App\WiseAi\Eval;

/**
 * Locked S0–S9 goldens from situations.md — regression safety for brain changes.
 */
final class SituationGoldens
{
    public const VERSION = '1.0-s0-s9';

    /**
     * @return list<GoldenCase>
     */
    public static function all(): array
    {
        $offerExt = 'eval-offer-s4';
        $namedTitle = 'Eval Jelly Box';

        return [
            new GoldenCase(
                id: 'S0',
                name: 'Unknown / gibberish soft clarify',
                text: 'bjyulok',
                expect: [
                    'intent' => 'unknown',
                    'action' => 'clarify',
                    'gap' => false,
                    'missing_context' => 'utterance',
                ],
            ),
            new GoldenCase(
                id: 'S1',
                name: 'Bare price ask → clarify offer',
                text: 'price koto?',
                expect: [
                    'intent' => 'price',
                    'action' => 'clarify',
                    'gap' => false,
                    'missing_context' => 'offer',
                    'reply_contains' => ['ছবি', 'photo', 'নাম', 'name'],
                ],
            ),
            new GoldenCase(
                id: 'S2',
                name: 'Named offer in text → knowledge',
                text: $namedTitle.' er dam?',
                seeds: [[
                    'type' => 'product',
                    'title' => $namedTitle,
                    'question' => $namedTitle.' price',
                    'answer' => 'Eval Jelly Box = ৳450',
                    'keywords' => ['jelly', 'box', 'eval'],
                    'external_id' => 'eval-jelly',
                    'meta' => ['offer_kind' => 'physical'],
                ]],
                expect: [
                    'intent' => 'price',
                    'action' => 'suggest_reply',
                    'gap' => false,
                    'source' => 'knowledge',
                    'reply_contains' => ['450'],
                ],
            ),
            new GoldenCase(
                id: 'S3',
                name: 'Prior offer in memory + bare price',
                text: 'price?',
                seeds: [[
                    'type' => 'product',
                    'title' => 'Eval Pilates Pack',
                    'question' => 'pilates price',
                    'answer' => 'Pilates pack ৳1200',
                    'keywords' => ['pilates', 'pack'],
                    'external_id' => 'eval-pilates',
                    'meta' => ['offer_kind' => 'service'],
                ]],
                prior: [[
                    'text' => 'Eval Pilates Pack details',
                    'context' => ['product_id' => 'eval-pilates'],
                ]],
                expect: [
                    'intent' => 'price',
                    'action' => 'suggest_reply',
                    'gap' => false,
                    'source' => 'knowledge',
                    'memory_used' => true,
                    'reply_contains' => ['1200'],
                ],
            ),
            new GoldenCase(
                id: 'S4',
                name: 'Channel asserts product_id → knowledge',
                text: 'dam koto?',
                context: ['product_id' => $offerExt],
                seeds: [[
                    'type' => 'product',
                    'title' => 'Eval Asserted Offer',
                    'question' => 'asserted offer price',
                    'answer' => 'Asserted offer ৳999',
                    'keywords' => ['asserted'],
                    'external_id' => $offerExt,
                    'meta' => ['offer_kind' => 'physical'],
                ]],
                expect: [
                    'intent' => 'price',
                    'action' => 'suggest_reply',
                    'gap' => false,
                    'source' => 'knowledge',
                    'reply_contains' => ['999'],
                ],
            ),
            new GoldenCase(
                id: 'S5',
                name: 'Many weak matches → shortlist clarify',
                text: 'box dam?',
                seeds: [
                    [
                        'type' => 'product',
                        'title' => 'Snack Box Classic',
                        'question' => 'snack box price',
                        'answer' => 'Snack Box Classic ৳450 — do-not-pick',
                        'keywords' => ['box', 'snack'],
                        'external_id' => 'eval-box-1',
                        'meta' => ['offer_kind' => 'physical'],
                    ],
                    [
                        'type' => 'product',
                        'title' => 'Gift Box Premium',
                        'question' => 'gift box price',
                        'answer' => 'Gift Box Premium ৳900 — do-not-pick',
                        'keywords' => ['box', 'gift'],
                        'external_id' => 'eval-box-2',
                        'meta' => ['offer_kind' => 'physical'],
                    ],
                    [
                        'type' => 'product',
                        'title' => 'Lunch Box Steel',
                        'question' => 'lunch box price',
                        'answer' => 'Lunch Box Steel ৳650 — do-not-pick',
                        'keywords' => ['box', 'lunch'],
                        'external_id' => 'eval-box-3',
                        'meta' => ['offer_kind' => 'physical'],
                    ],
                    [
                        'type' => 'product',
                        'title' => 'Beauty Box Mini',
                        'question' => 'beauty box price',
                        'answer' => 'Beauty Box Mini ৳1200 — do-not-pick',
                        'keywords' => ['box', 'beauty'],
                        'external_id' => 'eval-box-4',
                        'meta' => ['offer_kind' => 'physical'],
                    ],
                    [
                        'type' => 'product',
                        'title' => 'Toy Box Bundle',
                        'question' => 'toy box price',
                        'answer' => 'Toy Box Bundle ৳800 — do-not-pick',
                        'keywords' => ['box', 'toy'],
                        'external_id' => 'eval-box-5',
                        'meta' => ['offer_kind' => 'physical'],
                    ],
                    [
                        'type' => 'product',
                        'title' => 'Storage Box Large',
                        'question' => 'storage box price',
                        'answer' => 'Storage Box Large ৳550 — do-not-pick',
                        'keywords' => ['box', 'storage'],
                        'external_id' => 'eval-box-6',
                        'meta' => ['offer_kind' => 'physical'],
                    ],
                ],
                expect: [
                    'intent' => 'price',
                    'action' => 'clarify',
                    'gap' => false,
                    'source' => 'shortlist',
                    'missing_context' => 'offer',
                    'reply_contains_any_group' => [
                        ['Snack Box', 'Gift Box', 'Lunch Box', 'Beauty Box', 'Toy Box', 'Storage Box'],
                        ['ছবি', 'photo', 'নাম', 'name'],
                    ],
                ],
            ),
            new GoldenCase(
                id: 'S6',
                name: 'product_id known, no published knowledge → gap',
                text: 'price koto?',
                context: ['product_id' => 'missing-eval-offer-404'],
                expect: [
                    'intent' => 'price',
                    'action' => 'needs_human',
                    'gap' => true,
                ],
            ),
            new GoldenCase(
                id: 'S7',
                name: 'Bare price clarify invites name or photo',
                text: 'দাম কত?',
                expect: [
                    'intent' => 'price',
                    'action' => 'clarify',
                    'gap' => false,
                    'missing_context' => 'offer',
                    // Photo ask is required; name is OR across BN/EN.
                    'reply_contains_any_group' => [
                        ['ছবি', 'photo'],
                        ['নাম', 'name'],
                    ],
                ],
            ),
            new GoldenCase(
                id: 'S8',
                name: 'offer_kind hint only → kind-aware clarify',
                text: 'price koto?',
                context: ['offer_kind' => 'service'],
                expect: [
                    'intent' => 'price',
                    'action' => 'clarify',
                    'gap' => false,
                    'missing_context' => 'offer',
                    'reply_contains' => ['সার্ভিস', 'service', 'নাম', 'name'],
                ],
            ),
            new GoldenCase(
                id: 'S9',
                name: 'Pricing menu FAQ opt-in answers bare price',
                text: 'price koto?',
                seeds: [[
                    'type' => 'faq',
                    'title' => 'Eval SaaS plans',
                    'question' => 'plan pricing menu',
                    'answer' => 'Basic ৳500 · Pro ৳1500 · eval-menu',
                    'keywords' => ['price', 'plan', 'dam'],
                    'meta' => ['pricing_menu' => true],
                ]],
                expect: [
                    'intent' => 'price',
                    'action' => 'suggest_reply',
                    'gap' => false,
                    'source' => 'knowledge',
                    'pricing_menu' => true,
                    'reply_contains' => ['eval-menu'],
                ],
            ),
        ];
    }
}
