<?php

namespace App\Services\OrderIntelligence;

use Carbon\Carbon;

class PlatformSufficiencyChecker
{
    /**
     * @param  array<string, mixed>|null  $platformData
     */
    public function shouldUsePlatform(?array $platformData): bool
    {
        if (! config('order_intelligence.enabled', true)) {
            return false;
        }

        $mode = (string) config('order_intelligence.fraud_check.mode', 'hybrid');

        if ($mode === 'external_only' || $platformData === null) {
            return false;
        }

        if ($mode === 'platform_first') {
            return $this->hasAnyPlatformSignal($platformData);
        }

        return $this->hasFreshCourierSnapshots($platformData)
            || $this->hasFreshPlatformStats($platformData)
            || (! empty($platformData['courier_fraud_notes']) && $this->hasFreshCourierSnapshots($platformData, strict: false));
    }

    /**
     * @param  array<string, mixed>|null  $platformData
     */
    public function shouldRefreshSnapshots(?array $platformData): bool
    {
        if ($platformData === null) {
            return true;
        }

        $courierStats = $platformData['courier_stats'] ?? [];

        if ($courierStats === []) {
            return true;
        }

        return ! $this->hasFreshCourierSnapshots($platformData);
    }

    /**
     * @param  array<string, mixed>  $platformData
     */
    private function hasAnyPlatformSignal(array $platformData): bool
    {
        return $this->platformOrderCount($platformData) > 0
            || ($platformData['courier_stats'] ?? []) !== []
            || ($platformData['courier_fraud_notes'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $platformData
     */
    private function hasFreshPlatformStats(array $platformData): bool
    {
        $intel = $platformData['platform_intelligence'] ?? [];
        $totalOrders = (int) ($intel['total_orders'] ?? 0);
        $minOrders = (int) config('order_intelligence.fraud_check.min_platform_orders', 1);

        if ($totalOrders < $minOrders) {
            return false;
        }

        $freshness = $intel['data_freshness'] ?? null;

        if ($freshness === null) {
            return false;
        }

        return $this->isWithinHours(
            $freshness,
            (int) config('order_intelligence.fraud_check.max_stats_staleness_hours', 72),
        );
    }

    /**
     * @param  array<string, mixed>  $platformData
     */
    private function hasFreshCourierSnapshots(array $platformData, bool $strict = true): bool
    {
        $snapshots = $platformData['courier_stats'] ?? [];

        if ($snapshots === []) {
            return false;
        }

        $maxHours = (int) config('order_intelligence.fraud_check.max_snapshot_staleness_hours', 24);
        $hasSignal = false;

        foreach ($snapshots as $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }

            $hasData = ((int) ($snapshot['total_order'] ?? 0)) > 0
                || ((int) ($snapshot['confirmed'] ?? 0)) > 0
                || filled($snapshot['customer_rating'] ?? null);

            if ($hasData) {
                $hasSignal = true;
            }

            $fetchedAt = $snapshot['fetched_at'] ?? null;

            if ($fetchedAt === null) {
                if ($strict) {
                    return false;
                }

                continue;
            }

            if (! $this->isWithinHours($fetchedAt, $maxHours)) {
                return false;
            }
        }

        return $hasSignal;
    }

    /**
     * @param  array<string, mixed>  $platformData
     */
    private function platformOrderCount(array $platformData): int
    {
        return (int) ($platformData['platform_intelligence']['total_orders'] ?? 0);
    }

    private function isWithinHours(string $timestamp, int $hours): bool
    {
        return Carbon::parse($timestamp)->greaterThanOrEqualTo(now()->subHours($hours));
    }
}
