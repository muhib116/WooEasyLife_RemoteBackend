<?php

namespace App\Support;

use Illuminate\Support\Str;

class PlanDisplayPresenter
{
    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public static function enrich(array $plan, int $topFeatureLimit = 5): array
    {
        $isCatalog = self::isCatalogPlan($plan);
        $topFeatures = $isCatalog
            ? self::buildTopFeatures($plan, $topFeatureLimit)
            : [];
        $allFeatures = $isCatalog
            ? self::buildAllFeatures($plan)
            : [];
        $catalogFeatures = $isCatalog
            ? self::buildCatalogFeatures($plan)
            : [];
        $enabledCount = count($allFeatures);

        $enriched = array_merge($plan, [
            'plain_description' => self::plainDescription($plan['description'] ?? ''),
            'enabled_feature_count' => $enabledCount,
            'top_features' => $topFeatures,
            'all_features' => $allFeatures,
            'catalog_features' => $catalogFeatures,
            'more_features_count' => max(0, $enabledCount - count($topFeatures)),
            'more_features_label' => self::moreFeaturesLabel(max(0, $enabledCount - count($topFeatures))),
            'features_heading' => self::config('features_heading_bn', 'প্ল্যান ফিচার'),
            'badge_label' => self::badgeLabel($plan),
            'feature_lines' => array_column($allFeatures, 'label'),
            'summary_lines' => self::buildSummaryLines($plan),
        ]);

        if ($isCatalog) {
            return array_merge($enriched, [
                'duration_label' => self::durationLabel(
                    $plan['package_duration'] ?? null,
                    isset($plan['trial_days']) ? (int) $plan['trial_days'] : null,
                ),
                'price_label' => self::priceLabel((float) ($plan['package_price'] ?? 0)),
                'token_label' => self::tokenLabel((int) ($plan['order_rate_token'] ?? 0)),
                'website_label' => self::websiteLabel($plan['total_website_connect'] ?? null, (bool) ($plan['app_connect'] ?? false)),
                'app_connect_label' => ($plan['app_connect'] ?? false)
                    ? self::config('app_connect_label_bn', 'মোবাইল অ্যাপ অন্তর্ভুক্ত')
                    : null,
            ]);
        }

        return array_merge($enriched, [
            'duration_label' => null,
            'price_label' => self::legacyRateLabel((float) ($plan['per_order_rate'] ?? 0)),
            'token_label' => null,
            'website_label' => null,
            'app_connect_label' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public static function isCatalogPlan(array $plan): bool
    {
        $planType = $plan['plan_type'] ?? null;

        if ($planType === 'catalog') {
            return true;
        }

        if ($planType === 'legacy') {
            return false;
        }

        return ($plan['package_duration'] ?? null) !== null
            || ($plan['order_rate_token'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array{key: string, label: string}>
     */
    public static function buildTopFeatures(array $plan, int $limit = 5): array
    {
        $highlights = [];

        foreach (self::enabledFeatureEntries($plan) as $entry) {
            $highlights[] = $entry;

            if (count($highlights) >= $limit) {
                break;
            }
        }

        return $highlights;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array{key: string, label: string}>
     */
    public static function buildAllFeatures(array $plan): array
    {
        return self::enabledFeatureEntries($plan);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array{key: string, label: string, enabled: bool}>
     */
    public static function buildCatalogFeatures(array $plan): array
    {
        if (! self::isCatalogPlan($plan)) {
            return [];
        }

        $power = PackageCatalogFeatures::collapseToPower($plan['features'] ?? []);
        $labels = PackageCatalogFeatures::powerLabelsBn();
        $entries = [];

        foreach (PackageCatalogFeatures::powerKeys() as $key) {
            $entries[] = [
                'key' => $key,
                'label' => $labels[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                'enabled' => (bool) ($power[$key] ?? false),
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array{key: string, label: string}>
     */
    private static function enabledFeatureEntries(array $plan): array
    {
        return collect(self::buildCatalogFeatures($plan))
            ->filter(fn (array $entry) => $entry['enabled'])
            ->map(fn (array $entry) => [
                'key' => $entry['key'],
                'label' => $entry['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    public static function buildSummaryLines(array $plan): array
    {
        if (! self::isCatalogPlan($plan)) {
            return [];
        }

        $lines = [];

        if ((bool) ($plan['app_connect'] ?? false)) {
            $lines[] = self::websiteLabel(
                $plan['total_website_connect'] ?? null,
                true,
            );
        }

        $duration = self::durationLabel(
            $plan['package_duration'] ?? null,
            isset($plan['trial_days']) ? (int) $plan['trial_days'] : null,
        );
        if ($duration !== 'প্ল্যান') {
            $lines[] = $duration;
        }

        $tokenLabel = self::tokenLabel((int) ($plan['order_rate_token'] ?? 0));
        if ($tokenLabel !== '—') {
            $lines[] = $tokenLabel;
        }

        if (! (bool) ($plan['app_connect'] ?? false)) {
            $websiteLabel = self::websiteLabel($plan['total_website_connect'] ?? null, false);
            if ($websiteLabel !== '') {
                $lines[] = $websiteLabel;
            }
        }

        return array_values(array_filter($lines));
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public static function badgeLabel(array $plan): ?string
    {
        if (($plan['package_duration'] ?? null) === 'free_trial') {
            return self::config('badge_free_trial_bn', 'বিনামূল্যে শুরু');
        }

        if ((bool) ($plan['is_special'] ?? false)) {
            return self::config('badge_special_bn', 'সবচেয়ে জনপ্রিয়');
        }

        return null;
    }

    public static function durationLabel(?string $duration, ?int $trialDays = null): string
    {
        return match ($duration) {
            'free_trial' => str_replace(
                ':days',
                (string) max(1, $trialDays ?? 14),
                self::config('duration_free_trial_bn', ':days দিন ফ্রি ট্রায়াল'),
            ),
            '1_month' => self::config('duration_1_month_bn', 'মাসিক প্ল্যান'),
            '5_month' => self::config('duration_5_month_bn', '৫ মাসের প্ল্যান'),
            '1_year' => self::config('duration_1_year_bn', 'বার্ষিক প্ল্যান'),
            default => self::config('duration_default_bn', 'প্ল্যান'),
        };
    }

    public static function priceLabel(float $price): string
    {
        if ($price <= 0) {
            return '৳০';
        }

        return '৳'.number_format($price, 0, '.', ',');
    }

    public static function legacyRateLabel(float $rate): string
    {
        $template = self::config('legacy_per_order_rate_bn', 'অর্ডার প্রতি :rate টাকা');

        return str_replace(':rate', rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.'), $template);
    }

    public static function tokenLabel(int $tokens): string
    {
        if ($tokens <= 0) {
            return '—';
        }

        return number_format($tokens).' টোকেন';
    }

    public static function websiteLabel(mixed $limit, bool $appConnect): string
    {
        if ($appConnect) {
            if ($limit === null || $limit === '') {
                return self::config('website_unlimited_bn', 'আনলিমিটেড ওয়েবসাইট');
            }

            return (string) $limit.'টি ওয়েবসাইট';
        }

        if ($limit === null || $limit === '') {
            return self::config('website_unlimited_bn', 'আনলিমিটেড ওয়েবসাইট');
        }

        return (string) $limit.'টি ওয়েবসাইট';
    }

    public static function plainDescription(?string $html): string
    {
        return trim(strip_tags((string) $html));
    }

    public static function moreFeaturesLabel(int $count): ?string
    {
        if ($count <= 0) {
            return null;
        }

        $template = self::config('more_features_label_bn', '+ আরও :count ফিচার');

        return str_replace(':count', (string) $count, $template);
    }

    private static function config(string $key, string $default): string
    {
        return (string) config("package_catalog.plugin_display.{$key}", $default);
    }
}
