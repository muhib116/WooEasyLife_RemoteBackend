<?php

namespace App\Support;

use App\Models\PackageHub;
use App\Services\PackagePlanResolver;

/**
 * Public pricing cards and crawler copy share one plan list.
 * Prices come from package rows, never from hardcoded marketing numbers.
 */
class PublicPricingFacts
{
    /**
     * Same plans as the public pricing cards: active, selected for the website, with a duration.
     * Trial plans are listed first, matching the Vue card order.
     *
     * @return list<array<string, mixed>>
     */
    public static function displayPlans(): array
    {
        $plans = PackageHub::query()
            ->forPublicPricing()
            ->orderBy('index')
            ->orderBy('id')
            ->get();
        $enriched = app(PackagePlanResolver::class)->mapPlansForDisplay($plans);

        $trial = [];
        $paid = [];
        foreach ($enriched as $plan) {
            if (($plan['package_duration'] ?? null) === 'free_trial') {
                $trial[] = $plan;
            } else {
                $paid[] = $plan;
            }
        }

        return array_values([...$trial, ...$paid]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function trialPlan(): ?array
    {
        foreach (self::displayPlans() as $plan) {
            if (($plan['package_duration'] ?? null) === 'free_trial') {
                return $plan;
            }
        }

        return null;
    }

    public static function trialDays(): ?int
    {
        $trial = self::trialPlan();
        if ($trial === null) {
            return null;
        }

        $days = (int) ($trial['trial_days'] ?? 0);

        return $days > 0 ? $days : null;
    }

    /**
     * Cheapest paid plan whose duration field is exactly one month.
     *
     * @return array<string, mixed>|null
     */
    public static function cheapestMonthlyPaidPlan(): ?array
    {
        $cheapest = null;
        foreach (self::displayPlans() as $plan) {
            if (($plan['package_duration'] ?? null) !== '1_month') {
                continue;
            }

            $price = (float) ($plan['package_price'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            if ($cheapest === null || $price < (float) $cheapest['package_price']) {
                $cheapest = $plan;
            }
        }

        return $cheapest;
    }

    public static function cheapestMonthlyPriceLabel(): ?string
    {
        $plan = self::cheapestMonthlyPaidPlan();
        if ($plan === null) {
            return null;
        }

        $label = trim((string) ($plan['price_label'] ?? ''));

        return $label !== '' ? $label : null;
    }

    /**
     * @return array{priceCurrency: string, lowPrice: string, highPrice: string, offerCount: int}|null
     */
    public static function aggregateOffer(): ?array
    {
        $prices = [];
        foreach (self::displayPlans() as $plan) {
            if (($plan['package_duration'] ?? null) === 'free_trial') {
                continue;
            }

            $price = (float) ($plan['package_price'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $prices[] = $price;
        }

        if ($prices === []) {
            return null;
        }

        return [
            'priceCurrency' => 'BDT',
            'lowPrice' => self::schemaPrice(min($prices)),
            'highPrice' => self::schemaPrice(max($prices)),
            'offerCount' => count($prices),
        ];
    }

    /**
     * Duration from the plan field, not from the card label.
     */
    public static function factualDuration(array $plan, string $locale = 'bn'): string
    {
        $duration = (string) ($plan['package_duration'] ?? '');
        $days = (int) ($plan['trial_days'] ?? 0);

        if ($locale === 'en') {
            return match ($duration) {
                'free_trial' => $days > 0 ? $days.'-day free trial' : 'Free trial',
                '1_month' => '1 month',
                '5_month' => '5 months',
                '1_year' => '1 year',
                default => $duration !== '' ? $duration : 'Plan',
            };
        }

        return match ($duration) {
            'free_trial' => $days > 0 ? self::bnDigits((string) $days).' দিনের ফ্রি ট্রায়াল' : 'ফ্রি ট্রায়াল',
            '1_month' => '১ মাস',
            '5_month' => '৫ মাস',
            '1_year' => '১ বছর',
            default => $duration !== '' ? $duration : 'প্ল্যান',
        };
    }

    /**
     * Card labels whose month/year count disagrees with the duration field.
     *
     * @return list<string>
     */
    public static function durationLabelWarnings(): array
    {
        $warnings = [];
        foreach (self::displayPlans() as $plan) {
            $duration = (string) ($plan['package_duration'] ?? '');
            if (! in_array($duration, ['1_month', '5_month', '1_year'], true)) {
                continue;
            }

            $label = trim((string) ($plan['duration_label'] ?? ''));
            $expected = match ($duration) {
                '1_month' => 1,
                '5_month' => 5,
                '1_year' => 12,
            };
            $stated = self::statedMonths($label);
            if ($stated !== null && $stated !== $expected) {
                $warnings[] = ($plan['title'] ?? 'Plan').' ('.$duration.'): card label "'.$label.'"';
            }
        }

        return $warnings;
    }

    public static function nextStepSentence(string $locale): string
    {
        $price = self::cheapestMonthlyPriceLabel();

        if ($locale === 'en') {
            $sentence = 'Free checks are limited per day and need no account. For checkout OTP, duplicate-order blocking, blacklist and courier auto-entry inside WooCommerce, start a free trial from /pricing.';
            if ($price !== null) {
                $sentence .= ' Paid plans start from '.$price.'/month.';
            }

            return $sentence;
        }

        $sentence = 'ফ্রি চেক দিনে সীমিত, অ্যাকাউন্ট লাগে না। WooCommerce-এ চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক, ব্ল্যাকলিস্ট ও কুরিয়ার অটো এন্ট্রির জন্য /pricing থেকে ফ্রি ট্রায়াল শুরু করুন।';
        if ($price !== null) {
            $sentence .= ' পেইড প্ল্যান শুরু '.$price.'/মাস থেকে।';
        }

        return $sentence;
    }

    public static function trialFaqAnswer(string $locale): string
    {
        $days = self::trialDays();

        if ($locale === 'en') {
            if ($days === null) {
                return 'The free-trial length is the day count on the trial plan card. Test the main features, then upgrade.';
            }

            return 'The free trial is '.$days.' days, the same length shown on the trial plan card. Test the main features, then upgrade.';
        }

        if ($days === null) {
            return 'ফ্রি ট্রায়ালের দিনসংখ্যা ট্রায়াল প্ল্যান কার্ডে যেটা দেখানো হয় সেটাই। ট্রায়ালে মূল ফিচার টেস্ট করে তারপর আপগ্রেড করতে পারেন।';
        }

        $bn = self::bnDigits((string) $days);

        return 'ফ্রি ট্রায়াল '.$bn.' দিন — প্ল্যান কার্ডেও এই দিনসংখ্যা দেখানো হয়। ট্রায়ালে মূল ফিচার টেস্ট করে তারপর আপগ্রেড করতে পারেন।';
    }

    public static function expand(string $text): string
    {
        if (! str_contains($text, '{trial_') && ! str_contains($text, '{pricing_next_')) {
            return $text;
        }

        $days = self::trialDays();

        return strtr($text, [
            '{trial_faq_bn}' => self::trialFaqAnswer('bn'),
            '{trial_faq_en}' => self::trialFaqAnswer('en'),
            '{trial_length_bn}' => $days !== null ? self::bnDigits((string) $days).' দিন' : 'ফ্রি ট্রায়াল',
            '{trial_length_en}' => $days !== null ? $days.' days' : 'a free trial',
            '{pricing_next_bn}' => self::nextStepSentence('bn'),
            '{pricing_next_en}' => self::nextStepSentence('en'),
        ]);
    }

    public static function expandValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::expand($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::expandValue($item);
        }

        return $value;
    }

    private static function schemaPrice(float $price): string
    {
        if (abs($price - round($price)) < 0.001) {
            return (string) (int) round($price);
        }

        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    private static function statedMonths(string $label): ?int
    {
        $normalized = strtr($label, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);

        if (preg_match('/(\d+)\s*(?:মাস|month)/u', $normalized, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/(?:বার্ষিক|yearly|1\s*year|১\s*বছর)/u', $label) === 1) {
            return 12;
        }

        if (preg_match('/(?:মাসিক|monthly)/u', $label) === 1) {
            return 1;
        }

        return null;
    }

    private static function bnDigits(string $value): string
    {
        return strtr($value, [
            '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
            '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
        ]);
    }
}
