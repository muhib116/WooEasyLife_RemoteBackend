<?php

namespace App\WiseAi;

/**
 * Intent → Required Context contract (brain config, not per-knowledge-row).
 *
 * Missing required context → clarify (ask human for context).
 * Missing knowledge after context is present → gap / needs_human.
 */
class IntentContract
{
    /**
     * requires_offer = needs a catalog subject (physical / digital / service / …).
     * Key `requires_product` kept as alias for older traces.
     *
     * @var array<string, array{
     *     requires_offer: bool,
     *     requires_product: bool,
     *     requires_variant: bool,
     *     requires_customer: bool,
     *     clarify_reply: ?string
     * }>
     */
    private const CONTRACTS = [
        'price' => [
            'requires_offer' => true,
            'requires_product' => true,
            'requires_variant' => false,
            'requires_customer' => false,
            'clarify_reply' => 'অবশ্যই জানাচ্ছি। কোন প্রোডাক্ট বা সার্ভিসের দাম জানতে চাচ্ছেন? প্রোডাক্টের নাম লিখে পাঠান, অথবা প্রোডাক্টের ছবি পাঠালে সঠিক দাম জানিয়ে দিচ্ছি।',
        ],
        'delivery' => [
            'requires_offer' => false,
            'requires_product' => false,
            'requires_variant' => false,
            'requires_customer' => false,
            'clarify_reply' => null,
        ],
        'order_status' => [
            'requires_offer' => false,
            'requires_product' => false,
            'requires_variant' => false,
            // Customer identity not wired yet — keep false so we don't clarify-loop.
            'requires_customer' => false,
            'clarify_reply' => 'অর্ডার স্ট্যাটাস জানতে আপনার অর্ডার নম্বর বা ফোন নম্বর একটু পাঠাবেন?',
        ],
        'complaint' => [
            'requires_offer' => false,
            'requires_product' => false,
            'requires_variant' => false,
            'requires_customer' => false,
            'clarify_reply' => 'দুঃখিত শুনে। সমস্যাটা একটু বিস্তারিত লিখবেন? মার্চেন্ট নিশ্চিত করে জানাবে।',
        ],
        'payment' => [
            'requires_offer' => false,
            'requires_product' => false,
            'requires_variant' => false,
            'requires_customer' => false,
            'clarify_reply' => 'পেমেন্ট কোন মাধ্যমে করতে চান—বিকাশ, নগদ, রকেট, নাকি অন্য কিছু? মার্চেন্ট নিশ্চিত করে জানাবে।',
        ],
        'cod' => [
            'requires_offer' => false,
            'requires_product' => false,
            'requires_variant' => false,
            'requires_customer' => false,
            'clarify_reply' => 'ক্যাশ অন ডেলিভারি (COD) চান তাই তো? এলাকা/ঠিকানা একটু লিখলে মার্চেন্ট নিশ্চিত করে জানাবে।',
        ],
        'stock' => [
            'requires_offer' => true,
            'requires_product' => true,
            'requires_variant' => false,
            'requires_customer' => false,
            'clarify_reply' => 'স্টক জানাতে কোন প্রোডাক্ট বা ভ্যারিয়েন্ট বলবেন? নাম বা ছবি পাঠালে মার্চেন্ট নিশ্চিত করে জানাবে।',
        ],
        'unknown' => [
            'requires_offer' => false,
            'requires_product' => false,
            'requires_variant' => false,
            'requires_customer' => false,
            'clarify_reply' => 'দুঃখিত, আপনার প্রশ্নটি বুঝতে পারিনি। অনুগ্রহ করে পরিষ্কার করে লিখুন, অথবা কোন প্রোডাক্ট/সার্ভিস নিয়ে জানতে চান সেটা বলুন।',
        ],
    ];

    /** Soft assist when knowledge is missing — never invents fees; always non-empty. */
    private const GAP_ASSIST_FALLBACKS = [
        'delivery' => 'ডেলিভারি চার্জ/সময় মার্চেন্ট নিশ্চিত করে জানাবে। এলাকা বা প্রোডাক্টের নাম একটু লিখবেন?',
        'order_status' => 'অর্ডার স্ট্যাটাস জানতে অর্ডার নম্বর বা ফোন নম্বর একটু পাঠাবেন? মার্চেন্ট নিশ্চিত করে জানাবে।',
        'complaint' => 'দুঃখিত শুনে। সমস্যাটা একটু বিস্তারিত লিখবেন? মার্চেন্ট নিশ্চিত করে জানাবে।',
        'payment' => 'পেমেন্ট কোন মাধ্যমে করতে চান? মার্চেন্ট নিশ্চিত করে জানাবে।',
        'cod' => 'COD নিয়ে মার্চেন্ট নিশ্চিত করে জানাবে। এলাকা একটু লিখবেন?',
        'stock' => 'স্টক জানাতে কোন প্রোডাক্ট বলবেন? মার্চেন্ট নিশ্চিত করে জানাবে।',
        'price' => 'দাম জানাতে কোন প্রোডাক্টের নাম বা ছবি পাঠাবেন? মার্চেন্ট নিশ্চিত করে জানাবে।',
    ];

    /**
     * @return array{
     *     requires_offer: bool,
     *     requires_product: bool,
     *     requires_variant: bool,
     *     requires_customer: bool,
     *     clarify_reply: ?string
     * }
     */
    public function for(string $intent): array
    {
        return self::CONTRACTS[$intent] ?? self::CONTRACTS['unknown'];
    }

    /** Catalog offer subject required (physical, digital, service, …). */
    public function requiresProduct(string $intent): bool
    {
        $c = $this->for($intent);

        return (bool) ($c['requires_offer'] ?? $c['requires_product'] ?? false);
    }

    /**
     * @param  ?string  $offerKind  physical|digital|service|subscription|other
     */
    public function clarifyReply(string $intent, ?string $offerKind = null): ?string
    {
        if ($intent === 'price') {
            return match ($offerKind) {
                'digital' => 'অবশ্যই। কোন ডিজিটাল প্রোডাক্ট বা কোর্সের দাম জানতে চাচ্ছেন? নাম লিখে পাঠান, অথবা স্ক্রিনশট/ছবি পাঠালে বলে দিচ্ছি।',
                'service' => 'অবশ্যই। কোন সার্ভিস বা প্যাকেজের চার্জ জানতে চাচ্ছেন? নাম লিখে পাঠান, অথবা প্যাকেজের ছবি/স্ক্রিনশট পাঠালে সঠিক রেট জানাচ্ছি।',
                'subscription' => 'অবশ্যই। কোন প্ল্যান বা সাবস্ক্রিপশনের দাম জানতে চাচ্ছেন? প্ল্যানের নাম লিখে পাঠান, অথবা প্ল্যানের ছবি/স্ক্রিনশট পাঠালে বলে দিচ্ছি।',
                'physical' => 'অবশ্যই জানাচ্ছি। কোন প্রোডাক্টের দাম জানতে চাচ্ছেন? প্রোডাক্টের নাম লিখে পাঠান, অথবা প্রোডাক্টের ছবি পাঠালে সঠিক দাম জানিয়ে দিচ্ছি।',
                default => $this->for('price')['clarify_reply'],
            };
        }

        $reply = $this->for($intent)['clarify_reply'] ?? null;

        return is_string($reply) && $reply !== '' ? $reply : null;
    }

    /**
     * Non-empty Bangla assist on knowledge miss (gap stays true; no invented fees).
     *
     * @param  ?string  $offerKind  physical|digital|service|subscription|other
     */
    public function gapAssistReply(string $intent, ?string $offerKind = null): string
    {
        $fromClarify = $this->clarifyReply($intent, $offerKind);
        if (is_string($fromClarify) && $fromClarify !== '') {
            return $fromClarify;
        }

        return self::GAP_ASSIST_FALLBACKS[$intent]
            ?? 'একটু অপেক্ষা করুন—মার্চেন্ট নিশ্চিত করে জানাবে। আরও বিস্তারিত লিখলে সাহায্য করতে পারি।';
    }
}
