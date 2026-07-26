<?php

namespace App\Services\Messenger;

/**
 * Optional anonymized intent / guard packs for store agents.
 * Packs never include PII, PSIDs, phones, addresses, or media.
 */
class MessengerAnonymizedIntentPacks
{
    /**
     * @return array{version: string, intents: array<int, array<string, mixed>>, guards: array<int, array<string, mixed>>}
     */
    public function packs(): array
    {
        return [
            'version' => '2026-07-26',
            'intents' => [
                [
                    'id' => 'price_ask',
                    'labels' => ['price', 'cost', 'koto', 'দাম', 'কত'],
                    'examples' => [
                        'price koto',
                        'dam koto',
                        'এটার দাম কত',
                        'offer ase?',
                    ],
                ],
                [
                    'id' => 'availability',
                    'labels' => ['stock', 'ase', 'available', 'পাওয়া'],
                    'examples' => [
                        'stock ase?',
                        'eta pabo?',
                        'available?',
                    ],
                ],
                [
                    'id' => 'order_intent',
                    'labels' => ['order', 'nibo', 'kinbo', 'অর্ডার'],
                    'examples' => [
                        'ami nibo',
                        'order korte chai',
                        'একটা অর্ডার দিব',
                    ],
                ],
                [
                    'id' => 'force_negotiate',
                    'labels' => ['discount', 'kom', 'beshi na', 'force'],
                    'examples' => [
                        'er kom dile nibo',
                        '500 e dibo',
                        'er beshi dibo na',
                    ],
                ],
                [
                    'id' => 'lead_phone',
                    'labels' => ['phone', 'number', 'মোবাইল'],
                    'examples' => [
                        'amar number',
                        '017xxxxxxxx',
                    ],
                ],
            ],
            'guards' => [
                [
                    'id' => 'fear_selling',
                    'deny' => ['fear', 'scare', 'die', 'cancer claim', 'ভয়'],
                ],
                [
                    'id' => 'prompt_injection',
                    'deny' => ['ignore previous', 'system prompt', 'reveal token'],
                ],
                [
                    'id' => 'off_topic',
                    'deny' => ['politics', 'crypto giveaway'],
                ],
            ],
        ];
    }
}
