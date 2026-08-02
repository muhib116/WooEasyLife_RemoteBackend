<?php

namespace App\WiseAi\Training;

/**
 * Manual training pack JSON contract (human import → drafts / experience / language reviews only).
 */
final class TrainingSchema
{
    public const VERSION = 'wise-train-1.0';

    /**
     * Example pack shown in Train UI + prompt (merchant key target).
     *
     * @return array<string, mixed>
     */
    public static function examplePack(): array
    {
        return [
            'version' => self::VERSION,
            'merchant' => 'Example Fashion BD',
            'notes' => 'Review every row before Publish/Promote. Never invent prices.',
            'items' => [
                [
                    'lane' => 'knowledge',
                    'type' => 'faq',
                    'title' => 'Delivery charge Dhaka',
                    'question' => 'ঢাকায় ডেলিভারি চার্জ কত?',
                    'answer' => 'ঢাকা সিটির ভিতরে ডেলিভারি চার্জ ৬০ টাকা। ঢাকার বাইরে এলাকা বলে নিশ্চিত করে জানাব।',
                    'keywords' => ['delivery', 'ডেলিভারি', 'charge', 'ঢাকা'],
                    'scope' => 'merchant',
                ],
                [
                    'lane' => 'knowledge',
                    'type' => 'policy',
                    'title' => 'Return window',
                    'question' => 'রিটার্ন করা যাবে?',
                    'answer' => 'পণ্য হাতে পাওয়ার ৩ দিনের মধ্যে অপরিবর্তিত অবস্থায় রিটার্ন করা যায়। ইনভয়েস রাখুন।',
                    'keywords' => ['return', 'রিটার্ন', 'ফেরত'],
                    'scope' => 'merchant',
                ],
                [
                    'lane' => 'knowledge',
                    'type' => 'faq',
                    'title' => 'Payment methods',
                    'question' => 'পেমেন্ট কীভাবে করব?',
                    'answer' => 'ক্যাশ অন ডেলিভারি চলে। বিকাশ/নগদ চাইলে অর্ডার কনফার্মের সময় বলুন।',
                    'keywords' => ['payment', 'বিকাশ', 'ক্যাশ', 'নগদ'],
                    'scope' => 'merchant',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'plz',
                    'to' => 'please',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'tnx',
                    'to' => 'thank you',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'tmr',
                    'to' => 'tomorrow',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'tumar',
                    'to' => 'তোমার',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'apnar',
                    'to' => 'আপনার',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'experience',
                    'signal_type' => 'external',
                    'intent' => 'price',
                    'action' => 'clarify',
                    'weight' => 1.5,
                    'pattern_key' => 'script:ask_price_bare.clarify',
                    'note' => 'When customer says only “dam?”, clarify product name first.',
                ],
            ],
        ];
    }

    /**
     * Platform-global example — shared Knowledge drafts + Language reviews (no Experience).
     *
     * @return array<string, mixed>
     */
    public static function examplePlatformPack(): array
    {
        return [
            'version' => self::VERSION,
            'merchant' => 'Wise Platform',
            'notes' => 'Platform target: shared across all API keys. Publish/Promote carefully. No experience lane.',
            'items' => [
                [
                    'lane' => 'knowledge',
                    'type' => 'faq',
                    'scope' => 'platform',
                    'title' => 'Ask product before quoting price',
                    'question' => 'দাম কত?',
                    'answer' => 'কোন প্রোডাক্টের দাম জানতে চান? নাম বা ছবি পাঠালে নির্দিষ্ট দাম বলতে পারব।',
                    'keywords' => ['দাম', 'price', 'dam'],
                ],
                [
                    'lane' => 'knowledge',
                    'type' => 'script',
                    'scope' => 'platform',
                    'title' => 'Greeting tone BD commerce',
                    'question' => 'আসসালামু আলাইকুম',
                    'answer' => 'ওয়ালাইকুম সালাম! কীভাবে সাহায্য করতে পারি?',
                    'keywords' => ['salam', 'hello', 'হাই'],
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'plz',
                    'to' => 'please',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'tnx',
                    'to' => 'thank you',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'tumar',
                    'to' => 'তোমার',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'apnar',
                    'to' => 'আপনার',
                    'pack_slug' => 'core-bd',
                ],
            ],
        ];
    }
}
