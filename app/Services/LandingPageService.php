<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Services\PublicFraudCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LandingPageService
{
    public function __construct(
        protected PackagePlanResolver $planResolver,
        protected PublicFraudCheckService $publicFraudCheckService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(?Request $request = null): array
    {
        $plans = PackageHub::query()
            ->where('is_active', true)
            ->whereNotNull('package_duration')
            ->orderBy('index')
            ->orderBy('id')
            ->get();

        $planPayloads = $this->planResolver->mapPlansPayload($plans);
        $featured = $this->resolveFeaturedPlan($plans);
        $featuredPayload = $featured
            ? $this->planResolver->toPlanPayload($featured)
            : ($planPayloads[0] ?? null);

        $whatsappPhone = config('landing.whatsapp_phone');

        return [
            'plans' => array_map(fn (array $plan) => $this->enrichPlanForLanding($plan), $planPayloads),
            'featuredPlan' => $featuredPayload
                ? $this->enrichPlanForLanding($featuredPayload)
                : null,
            'featureHighlights' => $featuredPayload
                ? $this->buildFeatureHighlights($featuredPayload['features'] ?? [])
                : [],
            'featureGroups' => $featuredPayload
                ? $this->buildFeatureGroups($featuredPayload['features'] ?? [])
                : [],
            'conversionFeatures' => $featuredPayload
                ? $this->buildConversionFeatures($featuredPayload['features'] ?? [])
                : [],
            'heroBullets' => config('landing.hero_bullets', []),
            'valuePillars' => $featuredPayload
                ? $this->buildValuePillars($featuredPayload['features'] ?? [])
                : [],
            'stats' => config('landing.stats', []),
            'lossComparison' => config('landing.loss_comparison', []),
            'paymentMethods' => config('landing.payment_methods', []),
            'whatsappUrl' => $whatsappPhone
                ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $whatsappPhone)
                : null,
            'appDownloadUrl' => env('WOOEASYLIFE_ANDROID_DOWNLOAD_URL'),
            'playStoreUrl' => env('WOOEASYLIFE_PLAY_STORE_URL'),
            'fraudCheck' => $this->publicFraudCheckService->meta($request?->ip()),
        ];
    }

    private function resolveFeaturedPlan($plans): ?PackageHub
    {
        return $plans->first(fn (PackageHub $plan) => $plan->is_special)
            ?? $plans->first(fn (PackageHub $plan) => $plan->package_duration !== 'free_trial')
            ?? $plans->first();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function enrichPlanForLanding(array $plan): array
    {
        $enabledCount = collect($plan['features'] ?? [])
            ->filter(fn ($enabled) => (bool) $enabled)
            ->count();

        return array_merge($plan, [
            'duration_label' => $this->durationLabel($plan['package_duration'] ?? null),
            'price_label' => $this->priceLabel((float) ($plan['package_price'] ?? 0)),
            'token_label' => $this->tokenLabel((int) ($plan['order_rate_token'] ?? 0)),
            'website_label' => $this->websiteLabel($plan['total_website_connect'] ?? null),
            'plain_description' => $this->plainDescription($plan['description'] ?? ''),
            'enabled_feature_count' => $enabledCount,
            'top_features' => $this->buildFeatureHighlights($plan['features'] ?? [], limit: 5),
        ]);
    }

    private function durationLabel(?string $duration): string
    {
        return match ($duration) {
            'free_trial' => '১৪ দিন ফ্রি ট্রায়াল',
            '1_month' => 'মাসিক প্ল্যান',
            '5_month' => '৫ মাসের প্ল্যান',
            '1_year' => 'বার্ষিক প্ল্যান',
            default => 'প্ল্যান',
        };
    }

    private function priceLabel(float $price): string
    {
        if ($price <= 0) {
            return '৳০';
        }

        return '৳'.number_format($price, 0, '.', ',');
    }

    private function tokenLabel(int $tokens): string
    {
        if ($tokens <= 0) {
            return '—';
        }

        return number_format($tokens).' টোকেন';
    }

    private function websiteLabel(mixed $limit): string
    {
        if ($limit === null || $limit === '') {
            return 'আনলিমিটেড ওয়েবসাইট';
        }

        return (string) $limit.'টি ওয়েবসাইট';
    }

    private function plainDescription(?string $html): string
    {
        return trim(strip_tags((string) $html));
    }

    /**
     * @param  array<string, bool>  $features
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    private function buildFeatureHighlights(array $features, int $limit = 6): array
    {
        $order = config('landing.feature_highlight_order', []);
        $labels = config('landing.labels', []);
        $icons = config('landing.feature_icons', []);
        $highlights = [];

        foreach ($order as $key) {
            if (! ($features[$key] ?? false)) {
                continue;
            }

            $highlights[] = [
                'key' => $key,
                'label' => $labels[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                'icon' => $icons[$key] ?? 'check',
            ];

            if (count($highlights) >= $limit) {
                break;
            }
        }

        if (count($highlights) < $limit) {
            foreach ($features as $key => $enabled) {
                if (! $enabled || collect($highlights)->contains('key', $key)) {
                    continue;
                }

                $highlights[] = [
                    'key' => $key,
                    'label' => $labels[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                    'icon' => $icons[$key] ?? 'check',
                ];

                if (count($highlights) >= $limit) {
                    break;
                }
            }
        }

        return $highlights;
    }

    /**
     * @param  array<string, bool>  $features
     * @return array<int, array{group: string, items: array<int, string>}>
     */
    private function buildFeatureGroups(array $features): array
    {
        $labels = config('landing.labels', []);
        $groupTitles = config('landing.groups', []);
        $pluginGroups = config('landing.plugin_feature_groups', []);
        $appKeys = config('package_catalog.app_feature_keys', []);
        $grouped = [];

        foreach ($features as $key => $enabled) {
            if (! $enabled) {
                continue;
            }

            $groupKey = in_array($key, $appKeys, true)
                ? 'App'
                : ($pluginGroups[$key] ?? 'Tools');

            $grouped[$groupKey][] = $labels[$key] ?? Str::headline(str_replace('_', ' ', $key));
        }

        return collect($grouped)
            ->map(fn (array $items, string $groupKey) => [
                'group' => $groupTitles[$groupKey] ?? $groupKey,
                'items' => array_values($items),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $features
     * @return array<int, array{key: string, label: string, description: string, icon: string, color: string}>
     */
    private function buildConversionFeatures(array $features, int $limit = 8): array
    {
        $highlights = $this->buildFeatureHighlights($features, $limit);
        $descriptions = config('landing.feature_descriptions', []);
        $colors = config('landing.feature_card_colors', ['violet']);

        return collect($highlights)
            ->values()
            ->map(fn (array $item, int $index) => [
                ...$item,
                'description' => $descriptions[$item['key']] ?? $item['label'],
                'color' => $colors[$index % count($colors)],
            ])
            ->all();
    }

    /**
     * @param  array<string, bool>  $features
     * @return array<int, array{id: string, badge: string, headline: string, subheadline: string, accent: string, features: array<int, array{key: string, label: string, description: string}>, enabled_count: int}>
     */
    private function buildValuePillars(array $features): array
    {
        $labels = config('landing.labels', []);
        $descriptions = config('landing.feature_descriptions', []);

        return collect(config('landing.value_pillars', []))
            ->map(function (array $pillar) use ($features, $labels, $descriptions) {
                $items = collect($pillar['feature_keys'] ?? [])
                    ->filter(fn (string $key) => $features[$key] ?? false)
                    ->map(fn (string $key) => [
                        'key' => $key,
                        'label' => $labels[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                        'description' => $descriptions[$key] ?? ($labels[$key] ?? $key),
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $pillar['id'],
                    'badge' => $pillar['badge'],
                    'headline' => $pillar['headline'],
                    'subheadline' => $pillar['subheadline'],
                    'accent' => $pillar['accent'] ?? 'violet',
                    'features' => $items,
                    'enabled_count' => count($items),
                ];
            })
            ->filter(fn (array $pillar) => $pillar['enabled_count'] > 0)
            ->values()
            ->all();
    }
}
