<?php

namespace App\WiseAi\Knowledge\Seed;

/**
 * Platform knowledge catalog — situation-aligned scripts (situations.md + BD commerce norms).
 *
 * Accuracy contract (Trust First):
 * - Never invent merchant prices, fees, phone numbers, % discounts, or return-day windows.
 * - Prefer clarify / handoff / process questions (industry practice: confirm area, order id, offer subject).
 * - Groundable kinds only; scope=platform; human may unpublish anytime.
 *
 * Industry context (process only — not store facts): BD Messenger/COD commerce commonly asks
 * price without product, delivery without area, status without order id, payment method without
 * published policy. Sources inform *which questions to clarify*, not numeric answers.
 *
 * @see .cursor/skills/wise-ai/situations.md
 * @see https://arxiv.org/abs/2311.11142 (dialect diversity — regional seeder, not prices)
 */
final class PlatformScriptCatalog
{
    public const SEEDER_KEY = 'platform_script_catalog';

    public const VERSION = '1.2.0';

    /**
     * @return list<string>
     */
    public static function sources(): array
    {
        return [
            'wise-ai/situations.md (S0–S9)',
            'wise-ai/SKILL.md (evidence rule; Experience≠Knowledge)',
            'DialogueScripts (parity with decide clarify acts)',
            'BD ecommerce support patterns: confirm offer/area/order-id before quoting (process)',
        ];
    }

    /**
     * @return list<array{
     *   slug: string,
     *   type: string,
     *   title: string,
     *   question: string,
     *   answer: string,
     *   keywords: list<string>,
     *   situation?: string,
     *   meta?: array<string, mixed>
     * }>
     */
    public static function items(): array
    {
        return [
            [
                'slug' => 'delivery-ask-area',
                'type' => 'script',
                'situation' => 'delivery',
                'title' => 'Delivery — ask area before fee',
                'question' => 'ডেলিভারি চার্জ কত?',
                'answer' => 'কোন এলাকা (জেলা/উপজেলা) এবং কোন প্রোডাক্টে ডেলিভারি লাগবে বলবেন? দেখে চার্জ জানাই। অনুমান করে চার্জ বলব না।',
                'keywords' => ['ডেলিভারি', 'delivery', 'কুরিয়ার', 'courier', 'shipping', 'charge'],
            ],
            [
                'slug' => 'delivery-eta-clarify',
                'type' => 'script',
                'situation' => 'timeline',
                'title' => 'ETA — need area + offer',
                'question' => 'কতদিনে পাব?',
                'answer' => 'কোন আইটেম আর কোন এলাকায় লাগবে বলবেন? দেখে ডেলিভারির সময়টা জানাই। দিন-তারিখ আন্দাজ করে বলব না।',
                'keywords' => ['কতদিন', 'kobe pabo', 'কবে পাব', 'eta', 'সময়'],
            ],
            [
                'slug' => 'order-status-ask-id',
                'type' => 'script',
                'situation' => 'order_status',
                'title' => 'Order status — ask id/phone',
                'question' => 'অর্ডারের খবর কী?',
                'answer' => 'অর্ডার স্ট্যাটাস জানতে অর্ডার নম্বর বা যে নম্বর দিয়ে অর্ডার করেছেন সেটা একটু পাঠাবেন?',
                'keywords' => ['অর্ডার', 'order', 'track', 'ট্র্যাক', 'স্ট্যাটাস', 'status'],
            ],
            [
                'slug' => 'return-policy-handoff',
                'type' => 'script',
                'situation' => 'return',
                'title' => 'Return — no invented window',
                'question' => 'রিটার্ন করা যাবে?',
                'answer' => 'কোন অর্ডার বা প্রোডাক্ট নিয়ে জানতে চান বলুন। রিটার্ন বা এক্সচেঞ্জের নিয়ম দেখে জানাই; নির্দিষ্ট দিন বা শর্ত আন্দাজ করে বলব না। দরকার হলে টিম দেখবে।',
                'keywords' => ['রিটার্ন', 'return', 'ফেরত', 'exchange', 'এক্সচেঞ্জ'],
            ],
            [
                'slug' => 'angry-handoff',
                'type' => 'script',
                'situation' => 'complaint',
                'title' => 'Angry / complaint handoff',
                'question' => 'খারাপ সার্ভিস',
                'answer' => 'এমন অভিজ্ঞতা হওয়ায় দুঃখিত। সমস্যাটা একটু লিখুন বা অর্ডার নম্বর দিন—টিমকে জানিয়ে দ্রুত সমাধানের চেষ্টা করছি।',
                'keywords' => ['কমপ্লেইন', 'complaint', 'রাগ', 'angry', 'খারাপ', 'অভিযোগ'],
            ],
            [
                'slug' => 'refund-handoff',
                'type' => 'script',
                'situation' => 'refund',
                'title' => 'Refund — human + policy',
                'question' => 'টাকা ফেরত চাই',
                'answer' => 'রিফান্ডের জন্য অর্ডার নম্বর দিন। টিম অর্ডার ও নিয়ম দেখে জানাবে; আমি নিজে থেকে রিফান্ড নিশ্চিত বা টাকার অঙ্ক বলব না।',
                'keywords' => ['রিফান্ড', 'refund', 'টাকা ফেরত'],
            ],
            [
                'slug' => 'payment-ask-method',
                'type' => 'script',
                'situation' => 'payment',
                'title' => 'Payment — ask which method',
                'question' => 'bkash e payment kora jabe?',
                'answer' => 'কোন মেথডে পেমেন্ট করতে চান—বিকাশ, নগদ, রকেট নাকি অন্য কিছু—বলবেন? দোকানের নিয়ম দেখে জানাই; নির্দিষ্ট নম্বর বা চার্জ আন্দাজ করে বলব না।',
                'keywords' => ['পেমেন্ট', 'payment', 'bkash', 'বিকাশ', 'nagad', 'নগদ'],
            ],
            [
                'slug' => 'payment-policy-handoff',
                'type' => 'script',
                'situation' => 'payment',
                'title' => 'Payment — methods preference',
                'question' => 'পেমেন্ট মেথড কী কী?',
                'answer' => 'আপনি কোন মাধ্যমে পেমেন্ট করতে চান বলুন। দোকানের পেমেন্ট নিয়ম দেখে জানাই; ওয়ালেট নম্বর বা চার্জ অনুমান করে বলব না।',
                'keywords' => ['পেমেন্ট মেথড', 'payment method', 'পেমেন্ট', 'payment'],
            ],
            [
                'slug' => 'payment-confirm-handoff',
                'type' => 'script',
                'situation' => 'payment',
                'title' => 'Payment — confirm wallet via shop rules',
                'question' => 'nagad accept করেন?',
                'answer' => 'নগদ বা অন্য কোন মেথড চান সেটা বলবেন? নিয়ম দেখে জানাই; আন্দাজ করে হ্যাঁ/না বা নম্বর বলব না।',
                'keywords' => ['nagad', 'নগদ', 'accept', 'পেমেন্ট', 'payment'],
            ],
            [
                'slug' => 'cod-confirm-area',
                'type' => 'script',
                'situation' => 'payment',
                'title' => 'COD — ask area before promising',
                'question' => 'cod available?',
                'answer' => 'ক্যাশ অন ডেলিভারি কোন এলাকায় লাগবে বলবেন? দোকানের নিয়ম দেখে জানাই; আন্দাজ করে হ্যাঁ/না বলব না।',
                'keywords' => ['cod', 'ক্যাশ অন', 'cash on delivery', 'available'],
            ],
            [
                'slug' => 'cod-cash-on-clarify',
                'type' => 'script',
                'situation' => 'payment',
                'title' => 'COD — clarify area and rules',
                'question' => 'cash on delivery ache?',
                'answer' => 'ক্যাশ অন ডেলিভারি চাইলে এলাকা একটু লিখবেন? নিয়ম দেখে জানাই; চার্জ বা নিশ্চয়তা আন্দাজ করে বলব না।',
                'keywords' => ['cash on delivery', 'cash on', 'cod', 'ক্যাশ অন ডেলিভারি'],
            ],
            [
                'slug' => 'cod-handoff',
                'type' => 'script',
                'situation' => 'payment',
                'title' => 'COD — handoff to shop rules',
                'question' => 'ক্যাশ অন ডেলিভারি হবে?',
                'answer' => 'কোন এলাকায় ক্যাশ অন ডেলিভারি লাগবে বলুন। দোকানের নিয়ম দেখে জানাই; অনুমান করে নিশ্চিত বলব না।',
                'keywords' => ['ক্যাশ অন ডেলিভারি', 'ক্যাশ অন', 'cod', 'cash on delivery'],
            ],
            [
                'slug' => 'stock-ask-product',
                'type' => 'script',
                'situation' => 'stock',
                'title' => 'Stock — need product first',
                'question' => 'stock ache?',
                'answer' => 'কোন প্রোডাক্ট বা সাইজের স্টক জানতে চান বলুন বা ছবি পাঠাবেন? দেখে জানাই; আন্দাজ করে স্টক বলব না।',
                'keywords' => ['স্টক', 'stock', 'আছে কি', 'available', 'size'],
            ],
            [
                'slug' => 'stock-size-clarify',
                'type' => 'script',
                'situation' => 'stock',
                'title' => 'Stock — which size or product',
                'question' => 'size ache ki',
                'answer' => 'কোন প্রোডাক্টের কোন সাইজ জানতে চান বলবেন? দেখে জানাই; আন্দাজ করে সাইজ বা স্টক বলব না।',
                'keywords' => ['সাইজ', 'size', 'স্টক', 'stock', 'আছে'],
            ],
            [
                'slug' => 'stock-handoff',
                'type' => 'script',
                'situation' => 'stock',
                'title' => 'Stock — no invented quantity',
                'question' => 'কয়টা আছে?',
                'answer' => 'কোন প্রোডাক্টের কয়টা আছে জানতে চান নাম বা ছবি পাঠাবেন? দেখে জানাই; সংখ্যা আন্দাজ করে বলব না।',
                'keywords' => ['কয়টা', 'স্টক', 'stock', 'কতগুলো', 'আছে'],
            ],
        ];
    }
}
