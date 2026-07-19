<?php

namespace App\Services\Marketing;

use App\Models\CourierConfiguration;
use App\Services\PathaoCourierService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds the public courier-charge calculator config with live rates when available.
 *
 * - Steadfast: public pricing API (no login) — https://www.steadfast.com.bd/pricing
 * - Pathao: merchant price-plan API (requires configured CourierConfiguration user)
 * - RedX: static fallback (merchant charge API needs per-merchant auth)
 */
class CourierPublicRatesService
{
    public const PATHAO_CACHE_KEY = 'marketing.courier_rates.pathao';

    public function __construct(
        protected SteadfastPublicPricingService $steadfast,
        protected PathaoCourierService $pathao,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function calculatorConfig(): array
    {
        $base = config('landing.courier_charge_calculator', []);
        $couriers = is_array($base['couriers'] ?? null) ? $base['couriers'] : [];

        $steadfastLive = $this->steadfast->cached();
        if ($steadfastLive) {
            $couriers['steadfast'] = array_merge($couriers['steadfast'] ?? [], [
                'label' => $couriers['steadfast']['label'] ?? 'Steadfast',
                'pricing_mode' => 'steadfast_live',
                'charges' => $steadfastLive['charges'],
                'synced_at' => $steadfastLive['synced_at'] ?? null,
                'source' => 'live',
                'source_url' => SteadfastPublicPricingService::SOURCE_PAGE,
                'cod_percent' => (float) ($couriers['steadfast']['cod_percent'] ?? 1.0),
            ]);
        } else {
            $couriers['steadfast'] = array_merge($couriers['steadfast'] ?? [], [
                'pricing_mode' => 'zone_table',
                'source' => 'fallback',
                'source_url' => SteadfastPublicPricingService::SOURCE_PAGE,
            ]);
        }

        $pathaoLive = $this->pathaoCached();
        if ($pathaoLive) {
            $couriers['pathao'] = array_merge($couriers['pathao'] ?? [], [
                'label' => $couriers['pathao']['label'] ?? 'Pathao',
                'pricing_mode' => 'zone_table',
                'zones' => $pathaoLive['zones'],
                'synced_at' => $pathaoLive['synced_at'] ?? null,
                'source' => 'live',
                'source_url' => 'https://merchant.pathao.com/login',
                'cod_percent' => (float) ($couriers['pathao']['cod_percent'] ?? 1.0),
            ]);
        } else {
            $couriers['pathao'] = array_merge($couriers['pathao'] ?? [], [
                'pricing_mode' => 'zone_table',
                'source' => 'fallback',
                'source_url' => 'https://merchant.pathao.com/login',
            ]);
        }

        $couriers['redx'] = array_merge($couriers['redx'] ?? [], [
            'pricing_mode' => 'zone_table',
            'source' => 'fallback',
            'source_url' => null,
        ]);

        $liveCount = collect($couriers)->where('source', 'live')->count();

        $base['couriers'] = $couriers;
        $base['headline'] = 'Pathao · Steadfast · RedX — ডেলিভারি চার্জ হিসাব';
        $base['subtitle'] = $liveCount > 0
            ? 'Steadfast রেট প্রতিদিন অফিসিয়াল প্রাইসিং API থেকে আপডেট হয়। Pathao লাইভ সাইনক কনফিগ থাকলে চালু।'
            : 'জোন ও ওজন দিন — তুলনা দেখুন। লাইভ সিঙ্ক চালু না থাকলে আনুমানিক রেট দেখায়।';
        $base['note'] = '* Steadfast: অফিসিয়াল পাবলিক প্রাইসিং (steadfast.com.bd/pricing)। Pathao: মার্চেন্ট লগইন/API ছাড়া পাবলিক রেট নেই — কনফিগ থাকলে দৈনিক স্যাম্পল। RedX: আনুমানিক। COD ফি আলাদা অনুমান।';
        $base['official_links'] = [
            ['label' => 'Steadfast Price Calculator', 'url' => 'https://www.steadfast.com.bd/pricing'],
            ['label' => 'Pathao Merchant', 'url' => 'https://merchant.pathao.com/login'],
        ];
        $base['last_synced_at'] = collect([
            $steadfastLive['synced_at'] ?? null,
            $pathaoLive['synced_at'] ?? null,
        ])->filter()->sortDesc()->first();

        return $base;
    }

    /**
     * @return array{steadfast: array<string, mixed>, pathao: array<string, mixed>}
     */
    public function syncAll(): array
    {
        return [
            'steadfast' => $this->steadfast->sync(),
            'pathao' => $this->syncPathaoSamples(),
        ];
    }

    /**
     * Sample Pathao merchant price-plan for configured city/zone pairs.
     *
     * @return array{success: bool, message: string, zones?: array<string, array{base: float, per_kg_extra: float}>, synced_at?: string}
     */
    public function syncPathaoSamples(): array
    {
        $userId = (int) config('services.courier_public_rates.pathao_user_id', 0);
        $samples = config('services.courier_public_rates.pathao_samples', []);

        if ($userId < 1 || ! is_array($samples) || $samples === []) {
            return [
                'success' => false,
                'message' => 'Pathao public-rate sync skipped (set COURIER_PUBLIC_RATES_PATHAO_USER_ID + samples).',
            ];
        }

        $config = CourierConfiguration::query()
            ->where('user_id', $userId)
            ->where('slug', 'pathao')
            ->first();

        if (! $config) {
            return [
                'success' => false,
                'message' => "No Pathao CourierConfiguration for user {$userId}.",
            ];
        }

        $zones = [];
        foreach ($samples as $zoneKey => $sample) {
            if (! is_array($sample)) {
                continue;
            }

            $city = (int) ($sample['recipient_city'] ?? 0);
            $zone = (int) ($sample['recipient_zone'] ?? 0);
            if ($city < 1 || $zone < 1) {
                continue;
            }

            $light = $this->pathao->calculatePrice($config, array_merge($sample, [
                'item_weight' => (float) ($sample['weight_kg'] ?? 0.5),
                'recipient_city' => $city,
                'recipient_zone' => $zone,
            ]));
            $heavy = $this->pathao->calculatePrice($config, array_merge($sample, [
                'item_weight' => (float) ($sample['heavy_weight_kg'] ?? 2.0),
                'recipient_city' => $city,
                'recipient_zone' => $zone,
            ]));

            if (! ($light['success'] ?? false) || ! ($heavy['success'] ?? false)) {
                Log::warning('pathao_public_rate_sample_failed', [
                    'zone' => $zoneKey,
                    'light' => $light['message'] ?? null,
                    'heavy' => $heavy['message'] ?? null,
                ]);

                continue;
            }

            $lightCharge = $this->extractPathaoFinalCharge($light['data'] ?? null);
            $heavyCharge = $this->extractPathaoFinalCharge($heavy['data'] ?? null);
            if ($lightCharge === null || $heavyCharge === null) {
                continue;
            }

            $lightW = (float) ($sample['weight_kg'] ?? 0.5);
            $heavyW = (float) ($sample['heavy_weight_kg'] ?? 2.0);
            $deltaW = max(0.5, $heavyW - max(1.0, $lightW));
            $perKg = max(0, ($heavyCharge - $lightCharge) / $deltaW);

            $zones[(string) $zoneKey] = [
                'base' => round($lightCharge, 0),
                'per_kg_extra' => round($perKg, 0),
            ];
        }

        if ($zones === []) {
            return [
                'success' => false,
                'message' => 'Pathao samples returned no usable prices.',
            ];
        }

        $payload = [
            'zones' => $zones,
            'synced_at' => now()->toIso8601String(),
            'source' => 'pathao_merchant_api',
            'source_url' => 'https://merchant.pathao.com/login',
        ];

        Cache::put(self::PATHAO_CACHE_KEY, $payload, now()->addDays(3));

        return [
            'success' => true,
            'message' => 'Pathao zone samples synced ('.count($zones).').',
            'zones' => $zones,
            'synced_at' => $payload['synced_at'],
        ];
    }

    /**
     * @return array{zones: array<string, array{base: float, per_kg_extra: float}>, synced_at: ?string}|null
     */
    private function pathaoCached(): ?array
    {
        $cached = Cache::get(self::PATHAO_CACHE_KEY);

        return is_array($cached) && ! empty($cached['zones']) ? $cached : null;
    }

    private function extractPathaoFinalCharge(mixed $data): ?float
    {
        if (! is_array($data)) {
            return null;
        }

        foreach (['final_price', 'price', 'delivery_cost', 'total_price', 'charge'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (float) $data[$key];
            }
        }

        if (isset($data['price_plan']) && is_array($data['price_plan'])) {
            return $this->extractPathaoFinalCharge($data['price_plan']);
        }

        return null;
    }
}
