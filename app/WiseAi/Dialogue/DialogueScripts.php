<?php

namespace App\WiseAi\Dialogue;

/**
 * Dialogue v1 — thin BD scripts on sealed acts.
 * May improve clarify wording; never invents prices/stock; never overrides knowledge answers.
 */
final class DialogueScripts
{
    /**
     * @param  array{
     *     product_title?: string|null,
     *     offer_kind?: string|null
     * }  $ctx
     * @return array{id: string, text: string, apply: string}|null
     */
    public function resolve(string $patternId, array $ctx = []): ?array
    {
        $title = trim((string) ($ctx['product_title'] ?? ''));
        $kind = strtolower(trim((string) ($ctx['offer_kind'] ?? '')));

        return match ($patternId) {
            'ask_price_bare' => [
                'id' => 'ask_price_bare.clarify',
                'apply' => 'clarify',
                'text' => $this->barePriceClarify($kind),
            ],
            'followup_carry' => [
                'id' => 'followup_carry.clarify',
                'apply' => 'clarify',
                'text' => $title !== ''
                    ? "এই “{$title}” এর জন্যই জানতে চাচ্ছেন? নিশ্চিত করলে সঠিক তথ্য বলে দিচ্ছি।"
                    : 'আগের প্রোডাক্ট/সার্ভিসটির জন্যই জানতে চাচ্ছেন? নিশ্চিত করলে বলে দিচ্ছি।',
            ],
            'soft_clarify_unknown' => [
                'id' => 'soft_clarify_unknown.clarify',
                'apply' => 'clarify',
                'text' => 'দুঃখিত, একটু পরিষ্কার করে বলবেন? কোন প্রোডাক্ট/সার্ভিস, দাম, ডেলিভারি, নাকি অর্ডার স্ট্যাটাস জানতে চান?',
            ],
            'ask_delivery' => [
                'id' => 'ask_delivery.clarify',
                'apply' => 'clarify',
                'text' => 'ডেলিভারি/কুরিয়ার চার্জ জানাতে এলাকা (জেলা/উপজেলা) একটু বলবেন? প্রোডাক্ট জানা থাকলে আরও সঠিক হবে।',
            ],
            'ask_order_status' => [
                'id' => 'ask_order_status.clarify',
                'apply' => 'clarify',
                'text' => 'অর্ডার স্ট্যাটাস জানতে অর্ডার নম্বর বা যে নম্বর দিয়ে অর্ডার করেছেন সেটা একটু পাঠাবেন?',
            ],
            'ask_price_on_offer' => $title !== ''
                ? [
                    'id' => 'ask_price_on_offer.assist_hint',
                    'apply' => 'assist_hint',
                    'text' => "Offer subject “{$title}” — reply only from published knowledge; never invent a price.",
                ]
                : [
                    'id' => 'ask_price_on_offer.assist_hint',
                    'apply' => 'assist_hint',
                    'text' => 'Offer subject present — reply only from published knowledge; never invent a price.',
                ],
            'raise_complaint' => [
                'id' => 'raise_complaint.assist_hint',
                'apply' => 'assist_hint',
                'text' => 'Complaint turn — keep empathy; escalate to human if refund/return facts are missing.',
            ],
            default => null,
        };
    }

    /**
     * Attach script to decision.dialogue; apply clarify text only when Judge already clarified
     * without a knowledge answer.
     *
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function enrich(array $decision, array $ctx = []): array
    {
        $dialogue = is_array($decision['dialogue'] ?? null) ? $decision['dialogue'] : [];
        $patternId = (string) ($dialogue['id'] ?? '');
        $preferredScript = trim((string) ($ctx['preferred_script'] ?? ''));

        // Experience may prefer another clarify script (pattern prefix before first ".").
        if (
            $preferredScript !== ''
            && ($decision['action'] ?? '') === 'clarify'
            && ($decision['source'] ?? '') !== 'knowledge'
            && ($decision['source'] ?? '') !== 'shortlist'
            && ($decision['source'] ?? '') !== 'grounded_assist'
            && ($decision['source'] ?? '') !== 'grounded_assist_clarify'
            && empty($decision['shortlist'])
        ) {
            $preferredPattern = explode('.', $preferredScript, 2)[0] ?? '';
            if ($preferredPattern !== '') {
                $preferred = $this->resolve($preferredPattern, $ctx);
                if (
                    is_array($preferred)
                    && ($preferred['apply'] ?? '') === 'clarify'
                    && ($preferred['id'] ?? '') === $preferredScript
                ) {
                    $patternId = $preferredPattern;
                    $dialogue['id'] = $preferredPattern;
                    $dialogue['experience_preferred_script'] = $preferredScript;
                }
            }
        }

        if ($patternId === '') {
            return $decision;
        }

        $script = $this->resolve($patternId, $ctx);
        if ($script === null) {
            return $decision;
        }

        $dialogue['script'] = [
            'id' => $script['id'],
            'text' => $script['text'],
            'apply' => $script['apply'],
        ];
        $dialogue['script_applied'] = false;

        if (
            $script['apply'] === 'clarify'
            && ($decision['action'] ?? '') === 'clarify'
            && ($decision['source'] ?? '') !== 'knowledge'
            && ($decision['source'] ?? '') !== 'shortlist'
            && ($decision['source'] ?? '') !== 'grounded_assist'
            && ($decision['source'] ?? '') !== 'grounded_assist_clarify'
            && empty($decision['shortlist'])
            && trim($script['text']) !== ''
        ) {
            $decision['suggested_reply'] = $script['text'];
            $dialogue['script_applied'] = true;
        }

        $decision['dialogue'] = $dialogue;

        return $decision;
    }

    private function barePriceClarify(?string $offerKind): string
    {
        return match ($offerKind) {
            'digital' => 'অবশ্যই। কোন ডিজিটাল প্রোডাক্ট/কোর্সের দাম জানতে চাচ্ছেন? নাম লিখুন বা স্ক্রিনশট পাঠান।',
            'service' => 'অবশ্যই। কোন সার্ভিস/প্যাকেজের চার্জ জানতে চাচ্ছেন? নাম লিখুন বা প্যাকেজের ছবি/স্ক্রিনশট পাঠান।',
            'subscription' => 'অবশ্যই। কোন প্ল্যান/সাবস্ক্রিপশনের দাম জানতে চাচ্ছেন? প্ল্যানের নাম লিখুন বা ছবি পাঠান।',
            'physical' => 'অবশ্যই জানাচ্ছি। কোন প্রোডাক্টের দাম জানতে চাচ্ছেন? নাম লিখুন অথবা ছবি পাঠালে সঠিক দাম বলে দিচ্ছি।',
            default => 'অবশ্যই জানাচ্ছি। কোন প্রোডাক্ট বা সার্ভিসের দাম জানতে চাচ্ছেন? নাম লিখুন, অথবা ছবি পাঠালে সঠিক দাম জানিয়ে দিচ্ছি।',
        };
    }
}
