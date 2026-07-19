<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Syncs Steadfast public pricing from their website calculator API.
 *
 * Source: https://www.steadfast.com.bd/pricing
 * Data:   https://www.steadfast.com.bd/welcome/get/pricing-data
 */
class SteadfastPublicPricingService
{
    public const CACHE_KEY = 'marketing.courier_rates.steadfast';

    public const PRICING_URL = 'https://www.steadfast.com.bd/welcome/get/pricing-data';

    public const SOURCE_PAGE = 'https://www.steadfast.com.bd/pricing';

    /**
     * @return array{success: bool, message: string, charges?: array<string, float|int>, synced_at?: string}
     */
    public function sync(): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json, text/plain, */*',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => self::SOURCE_PAGE,
                    'User-Agent' => 'WooEasyLifeCourierRateSync/1.0',
                ])
                ->get(self::PRICING_URL);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Steadfast pricing HTTP '.$response->status(),
                ];
            }

            $charges = $response->json('charges');
            if (! is_array($charges) || $charges === []) {
                return [
                    'success' => false,
                    'message' => 'Steadfast pricing payload missing charges.',
                ];
            }

            $normalized = [];
            foreach ($charges as $key => $value) {
                if (is_numeric($value)) {
                    $normalized[(string) $key] = (float) $value;
                }
            }

            if ($normalized === []) {
                return [
                    'success' => false,
                    'message' => 'Steadfast charges were empty after normalize.',
                ];
            }

            $payload = [
                'charges' => $normalized,
                'synced_at' => now()->toIso8601String(),
                'source' => 'steadfast_public',
                'source_url' => self::SOURCE_PAGE,
            ];

            Cache::put(self::CACHE_KEY, $payload, now()->addDays(3));

            return [
                'success' => true,
                'message' => 'Steadfast rates synced ('.count($normalized).' keys).',
                'charges' => $normalized,
                'synced_at' => $payload['synced_at'],
            ];
        } catch (\Throwable $e) {
            Log::warning('steadfast_public_pricing_sync_failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{charges: array<string, float>, synced_at: ?string, source: string, source_url: string}|null
     */
    public function cached(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) && ! empty($cached['charges']) ? $cached : null;
    }

    /**
     * Official parcel delivery charge from Dhaka (ISD) — mirrors steadfast.com.bd/pricing JS.
     *
     * @param  'dhaka'|'suburb'|'outside'  $zone
     * @param  array<string, float|int>  $charges
     */
    public function deliveryCharge(string $zone, float $weightKg, array $charges): float
    {
        $weight = max(0.0, $weightKg);
        $n = $this->billableWeight($zone, $weight);

        return match ($zone) {
            'dhaka' => $this->dhakaSameCity($n, $charges),
            'suburb' => $this->dhakaToSuburb($n, $charges),
            default => $this->dhakaToOutside($n, $charges),
        };
    }

    /**
     * @param  array<string, float|int>  $charges
     */
    private function dhakaSameCity(float $n, array $charges): float
    {
        if ($n <= 0.15) {
            return (float) ($charges['samecity_dhaka_150'] ?? 0);
        }

        if ($n <= 0.5) {
            return (float) ($charges['samecity_dhaka_500'] ?? 0);
        }

        $base = (float) ($charges['samecity_dhaka'] ?? 0);
        $extra = (float) ($charges['samecity_weight'] ?? 0);

        return $base + $extra * ($n - 1);
    }

    /**
     * @param  array<string, float|int>  $charges
     */
    private function dhakaToSuburb(float $n, array $charges): float
    {
        if ($n <= 0.5) {
            return (float) ($charges['isd_to_sub_500'] ?? 0);
        }

        $base = (float) ($charges['isd_to_sub'] ?? 0);
        $extra = (float) ($charges['isd_to_sub_weight'] ?? 0);

        return $base + $extra * ($n - 1);
    }

    /**
     * @param  array<string, float|int>  $charges
     */
    private function dhakaToOutside(float $n, array $charges): float
    {
        if ($n <= 0.5) {
            return (float) ($charges['isd_to_osd_500'] ?? 0);
        }

        $base = (float) ($charges['isd_to_osd'] ?? 0);
        $extra = (float) ($charges['isd_to_osd_weight'] ?? 0);

        return $base + $extra * ($n - 1);
    }

    private function billableWeight(string $zone, float $weight): float
    {
        if ($zone === 'dhaka') {
            if ($weight <= 0.15) {
                return 0.15;
            }
            if ($weight <= 0.5) {
                return 0.5;
            }

            return (float) max(1, (int) ceil($weight));
        }

        if ($weight <= 0.5) {
            return 0.5;
        }

        return (float) max(1, (int) ceil($weight));
    }
}
