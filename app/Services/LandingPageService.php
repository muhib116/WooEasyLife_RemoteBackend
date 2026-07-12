<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Support\PackageCatalogFeatures;
use App\Support\PlanDisplayPresenter;
use App\Support\WhatsappLink;
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
            'plans' => $this->planResolver->mapPlansForDisplay($plans),
            'featuredPlan' => $featuredPayload
                ? PlanDisplayPresenter::enrich($featuredPayload)
                : null,
            'featureHighlights' => $featuredPayload
                ? collect(PlanDisplayPresenter::buildTopFeatures($featuredPayload, 6))
                    ->map(fn (array $item) => [...$item, 'icon' => 'check'])
                    ->all()
                : [],
            'featureGroups' => $featuredPayload
                ? $this->buildFeatureGroups($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [],
            'conversionFeatures' => $featuredPayload
                ? $this->buildConversionFeatures($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [],
            'heroBullets' => config('landing.hero_bullets', []),
            'heroTrustBadges' => config('landing.hero_trust_badges', []),
            'hero' => config('landing.hero', []),
            'integrations' => config('landing.integrations', []),
            'roiScenarios' => config('landing.roi_scenarios', []),
            'roiCalculator' => config('landing.roi_calculator', []),
            'howItWorks' => config('landing.how_it_works', []),
            'appShowcase' => config('landing.app_showcase', []),
            'featureShowcases' => $featuredPayload
                ? $this->buildFeatureShowcases($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [],
            'valuePillars' => $featuredPayload
                ? $this->buildValuePillars($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [],
            'stats' => config('landing.stats', []),
            'courierPerformance' => config('landing.courier_performance', []),
            'lossComparison' => config('landing.loss_comparison', []),
            'paymentMethods' => config('landing.payment_methods', []),
            'enterpriseCta' => config('landing.enterprise_cta', []),
            'whatsappUrl' => WhatsappLink::url($whatsappPhone),
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsappPhone,
                config('landing.whatsapp_default_message'),
            ),
            'appDownloadUrl' => env('WOOEASYLIFE_ANDROID_DOWNLOAD_URL'),
            'playStoreUrl' => env('WOOEASYLIFE_PLAY_STORE_URL'),
            'fraudCheck' => $this->publicFraudCheckService->meta($request?->ip()),
            'fraudBenefitCards' => config('landing.fraud_benefit_cards', []),
        ];
    }

    private function resolveFeaturedPlan($plans): ?PackageHub
    {
        return $plans->first(fn (PackageHub $plan) => $plan->is_special)
            ?? $plans->first(fn (PackageHub $plan) => $plan->package_duration !== 'free_trial')
            ?? $plans->first();
    }

    /**
     * @param  array<string, mixed>  $features
     * @return array<string, bool>
     */
    private function legacyFeatures(array $features): array
    {
        return PackageCatalogFeatures::expandForLegacyApi($features);
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

    /**
     * @param  array<string, bool>  $features
     * @return array<int, array<string, mixed>>
     */
    private function buildFeatureShowcases(array $features): array
    {
        $labels = config('landing.labels', []);
        $descriptions = config('landing.feature_descriptions', []);
        $detailCopy = config('landing.feature_detail_copy', []);

        return collect(config('landing.feature_showcases', []))
            ->map(function (array $showcase) use ($features, $labels, $descriptions, $detailCopy) {
                $items = collect($showcase['feature_keys'] ?? [])
                    ->filter(fn (string $key) => $features[$key] ?? false)
                    ->map(function (string $key) use ($labels, $descriptions, $detailCopy) {
                        $copy = $detailCopy[$key] ?? [];

                        return [
                            'key' => $key,
                            'label' => $labels[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                            'description' => $copy['summary'] ?? ($descriptions[$key] ?? ($labels[$key] ?? $key)),
                            'detail' => $copy['detail'] ?? ($descriptions[$key] ?? ''),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $showcase['id'],
                    'badge' => $showcase['badge'],
                    'headline' => $showcase['headline'],
                    'teaser' => $showcase['teaser'] ?? '',
                    'pain' => $showcase['pain'] ?? '',
                    'solution' => $showcase['solution'] ?? '',
                    'benefit' => $showcase['benefit'] ?? '',
                    'highlights' => $showcase['highlights'] ?? [],
                    'profit' => $showcase['profit'] ?? null,
                    'read_more' => $showcase['read_more'] ?? [],
                    'scenario' => $showcase['scenario'] ?? null,
                    'accent' => $showcase['accent'] ?? 'violet',
                    'features' => $items,
                    'enabled_count' => count($items),
                ];
            })
            ->filter(fn (array $showcase) => $showcase['enabled_count'] > 0)
            ->values()
            ->all();
    }
}
