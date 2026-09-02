<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Support\PackageCatalogFeatures;
use App\Support\PlanDisplayPresenter;
use App\Support\WhatsappLink;
use App\Services\PublicFraudCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LandingPageService
{
    public const ACTIVE_PLANS_CACHE_KEY = 'landing.active_plans.v1';

    private const ACTIVE_PLANS_CACHE_SECONDS = 300;

    public function __construct(
        protected PackagePlanResolver $planResolver,
        protected PublicFraudCheckService $publicFraudCheckService,
        protected LandingSettingsService $landingSettings,
        protected SubscriptionPaymentConfigService $paymentConfig,
    ) {
    }

    public static function forgetActivePlansCache(): void
    {
        Cache::forget(self::ACTIVE_PLANS_CACHE_KEY);
    }

    /**
     * Lightweight props for SEO tool/intent pages (fraud widget + WhatsApp only).
     *
     * @return array{whatsappUrl: ?string, whatsappContactUrl: ?string, fraudCheck: array<string, mixed>}
     */
    public function marketingShell(?Request $request = null, string $locale = 'bn'): array
    {
        $whatsappPhone = $this->landingSettings->adminWhatsapp();

        return [
            'whatsappUrl' => WhatsappLink::url($whatsappPhone),
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsappPhone,
                $locale === 'en'
                    ? 'Hi, I want a WooEasyLife subscription.'
                    : config('landing.whatsapp_default_message'),
            ),
            'fraudCheck' => $this->publicFraudCheckService->meta($request?->ip(), $locale),
        ];
    }

    /**
     * Calculator landing pages — shell + only the config blobs they render.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function calculatorProps(array $keys, ?Request $request = null, string $locale = 'bn'): array
    {
        $props = $this->marketingShell($request, $locale);

        foreach ($keys as $key) {
            $props[$key] = match ($key) {
                'courierChargeCalculator' => app(\App\Services\Marketing\CourierPublicRatesService::class)
                    ->calculatorConfig($locale),
                'roiCalculator' => $locale === 'en'
                    ? config('landing.roi_calculator_en', config('landing.roi_calculator', []))
                    : config('landing.roi_calculator', []),
                'roiScenarios' => $locale === 'en'
                    ? config('landing.roi_scenarios_en', config('landing.roi_scenarios', []))
                    : config('landing.roi_scenarios', []),
                'adsRoasCalculator' => $locale === 'en'
                    ? config('landing.ads_roas_calculator_en', [])
                    : config('landing.ads_roas_calculator', []),
                default => [],
            };
        }

        return $props;
    }

    /**
     * @param  array{slim?: bool}  $options  slim=true omits homepage-unused blobs (TTFB/HTML weight)
     * @return array<string, mixed>
     */
    public function payload(?Request $request = null, string $locale = 'bn', array $options = []): array
    {
        $slim = (bool) ($options['slim'] ?? false);
        $plans = $this->activePlans();

        $planPayloads = $this->planResolver->mapPlansPayload($plans);
        $featured = $this->resolveFeaturedPlan($plans);
        $featuredPayload = $featured
            ? $this->planResolver->toPlanPayload($featured)
            : ($planPayloads[0] ?? null);

        $whatsappPhone = $this->landingSettings->adminWhatsapp();
        $paymentMethods = $this->paymentConfig->forApi();
        $fraudMeta = $this->publicFraudCheckService->meta($request?->ip(), $locale);

        $payload = [
            'plans' => $this->planResolver->mapPlansForDisplay($plans),
            'featuredPlan' => $featuredPayload
                ? PlanDisplayPresenter::enrich($featuredPayload)
                : null,
            'heroBullets' => config('landing.hero_bullets', []),
            'heroTrustBadges' => collect(config('landing.hero_trust_badges', []))
                ->map(function ($badge) use ($paymentMethods) {
                    if ($badge !== 'payment_methods') {
                        return $badge;
                    }

                    $labels = collect($paymentMethods)->pluck('payment_partner')->filter()->implode(' · ');

                    return $labels !== '' ? $labels : null;
                })
                ->filter()
                ->values()
                ->all(),
            'hero' => config('landing.hero', []),
            'integrations' => config('landing.integrations', []),
            'roiScenarios' => config('landing.roi_scenarios', []),
            'roiCalculator' => config('landing.roi_calculator', []),
            'howItWorks' => config('landing.how_it_works', []),
            'appShowcase' => config('landing.app_showcase', []),
            'featureShowcases' => $featuredPayload
                ? $this->buildFeatureShowcases($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [],
            'stats' => config('landing.stats', []),
            'courierPerformance' => config('landing.courier_performance', []),
            'lossComparison' => config('landing.loss_comparison', []),
            'paymentMethods' => collect($paymentMethods)
                ->pluck('payment_partner')
                ->filter()
                ->values()
                ->all(),
            'subscriptionPaymentMethods' => $paymentMethods,
            'enterpriseCta' => config('landing.enterprise_cta', []),
            'whatsappUrl' => WhatsappLink::url($whatsappPhone),
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsappPhone,
                $locale === 'en'
                    ? 'Hi, I want a WooEasyLife subscription.'
                    : config('landing.whatsapp_default_message'),
            ),
            'whatsappDisplayPhone' => $this->displayPhone($whatsappPhone),
            'appDownloadUrl' => $this->landingSettings->appDownloadUrl(),
            'playStoreUrl' => $this->landingSettings->playStoreUrl(),
            'pluginDownloadUrl' => $this->landingSettings->pluginDownloadUrl(),
            'fraudCheck' => $fraudMeta,
            'fraudBenefitCards' => config('landing.fraud_benefit_cards', []),
            'caseStudies' => config('landing.case_studies', []),
            'locale' => $locale,
        ];

        if (! $slim) {
            $payload['featureHighlights'] = $featuredPayload
                ? collect(PlanDisplayPresenter::buildTopFeatures($featuredPayload, 6))
                    ->map(fn (array $item) => [...$item, 'icon' => 'check'])
                    ->all()
                : [];
            $payload['featureGroups'] = $featuredPayload
                ? $this->buildFeatureGroups($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [];
            $payload['conversionFeatures'] = $featuredPayload
                ? $this->buildConversionFeatures($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [];
            $payload['valuePillars'] = $featuredPayload
                ? $this->buildValuePillars($this->legacyFeatures($featuredPayload['features'] ?? []))
                : [];
            $payload['courierChargeCalculator'] = app(\App\Services\Marketing\CourierPublicRatesService::class)
                ->calculatorConfig($locale);
            $payload['adsRoasCalculator'] = config('landing.ads_roas_calculator', []);
            $payload['adsRoasCalculatorEn'] = config('landing.ads_roas_calculator_en', []);
            $payload['adminEmail'] = $this->landingSettings->adminEmail();
            $payload['adminPhone'] = $this->landingSettings->adminPhone();
            $payload['paymentNumbers'] = [
                'bkash' => $this->landingSettings->bkashNumber(),
                'rocket' => $this->landingSettings->rocketNumber(),
                'nagad' => $this->landingSettings->nagadNumber(),
            ];
        }

        if ($locale === 'en') {
            return $this->applyEnglishLandingOverlays($payload, $paymentMethods);
        }

        return $payload;
    }

    /**
     * @return Collection<int, PackageHub>
     */
    private function activePlans(): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(self::ACTIVE_PLANS_CACHE_KEY, self::ACTIVE_PLANS_CACHE_SECONDS, function () {
            return PackageHub::query()
                ->where('is_active', true)
                ->whereNotNull('package_duration')
                ->orderBy('index')
                ->orderBy('id')
                ->get()
                ->map(fn (PackageHub $plan) => $plan->getAttributes())
                ->values()
                ->all();
        });

        return PackageHub::hydrate($rows);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $paymentMethods
     * @return array<string, mixed>
     */
    private function applyEnglishLandingOverlays(array $payload, array $paymentMethods): array
    {
        $en = config('landing_en', []);

        $payload['hero'] = $en['hero'] ?? $payload['hero'];
        $payload['heroBullets'] = $en['hero_bullets'] ?? $payload['heroBullets'];
        $payload['heroTrustBadges'] = collect($en['hero_trust_badges'] ?? [])
            ->map(function ($badge) use ($paymentMethods) {
                if ($badge !== 'payment_methods') {
                    return $badge;
                }

                $labels = collect($paymentMethods)->pluck('payment_partner')->filter()->implode(' · ');

                return $labels !== '' ? $labels : null;
            })
            ->filter()
            ->values()
            ->all();
        $payload['howItWorks'] = $en['how_it_works'] ?? $payload['howItWorks'];
        $payload['appShowcase'] = $en['app_showcase'] ?? $payload['appShowcase'];
        $payload['fraudBenefitCards'] = $en['fraud_benefit_cards'] ?? $payload['fraudBenefitCards'];
        $payload['caseStudies'] = $en['case_studies'] ?? $payload['caseStudies'];
        $payload['stats'] = $en['stats'] ?? $payload['stats'];
        $payload['courierPerformance'] = $en['courier_performance'] ?? $payload['courierPerformance'];
        $payload['lossComparison'] = $en['loss_comparison'] ?? $payload['lossComparison'];
        $payload['enterpriseCta'] = $en['enterprise_cta'] ?? $payload['enterpriseCta'];
        $payload['integrations'] = $en['integrations'] ?? $payload['integrations'];
        $payload['roiCalculator'] = config('landing.roi_calculator_en', $payload['roiCalculator']);
        $payload['roiScenarios'] = config('landing.roi_scenarios_en', $payload['roiScenarios']);

        if (isset($en['fraud_check_demo']) && is_array($payload['fraudCheck'] ?? null)) {
            $payload['fraudCheck']['demo'] = $en['fraud_check_demo'];
        }

        return $payload;
    }

    private function displayPhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            return '0'.substr($digits, 2);
        }

        return $phone;
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
                $alwaysShow = (bool) ($showcase['always_show'] ?? false);
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

                // Beta / hub features (e.g. Funnels) may ship without package feature_keys.
                if ($alwaysShow && $items === []) {
                    $items = collect($showcase['highlights'] ?? [])
                        ->take(6)
                        ->values()
                        ->map(fn (string $label, int $i) => [
                            'key' => 'highlight_'.$i,
                            'label' => $label,
                            'description' => $label,
                            'detail' => '',
                        ])
                        ->all();
                }

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
                    'always_show' => $alwaysShow,
                ];
            })
            ->filter(fn (array $showcase) => ($showcase['always_show'] ?? false) || $showcase['enabled_count'] > 0)
            ->values()
            ->all();
    }
}
