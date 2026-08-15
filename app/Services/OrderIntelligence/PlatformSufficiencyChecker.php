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

        // hybrid: prefer cache to avoid hammering courier partners.
        if (config('order_intelligence.fraud_check.stale_while_revalidate', true)) {
            // Serve cache whenever we have at least one successful courier snapshot.
            // A single failed partner (e.g. Carrybee) must not force a full live re-check.
            if ($this->hasUsefulSuccessfulSnapshots($platformData)) {
                return true;
            }

            return $this->hasAnyPlatformSignal($platformData)
                && ! $this->hasFailedSnapshots($platformData);
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
        return $this->couriersNeedingRefresh($platformData) !== [];
    }

    /**
     * Couriers that should be re-fetched in the background.
     *
     * @param  array<string, mixed>|null  $platformData
     * @return list<string>
     */
    public function couriersNeedingRefresh(?array $platformData): array
    {
        $required = $this->configuredCouriers();

        if ($platformData === null) {
            return $required;
        }

        $snapshots = $platformData['courier_stats'] ?? [];

        if ($snapshots === []) {
            return $required;
        }

        $byCourier = [];
        foreach ($snapshots as $snapshot) {
            if (is_array($snapshot) && filled($snapshot['courier'] ?? null)) {
                $byCourier[(string) $snapshot['courier']] = $snapshot;
            }
        }

        $maxHours = (int) config('order_intelligence.fraud_check.max_snapshot_staleness_hours', 10);
        $need = [];

        foreach ($required as $courier) {
            if (! isset($byCourier[$courier])) {
                $need[] = $courier;

                continue;
            }

            $snapshot = $byCourier[$courier];

            if (! empty($snapshot['fetch_failed'])) {
                $need[] = $courier;

                continue;
            }

            // Pathao rating-only snapshots hide confirm/cancel — keep trying for counts.
            if ($courier === 'pathao' && $this->snapshotIsRatingOnly($snapshot)) {
                $need[] = $courier;

                continue;
            }

            $fetchedAt = $snapshot['fetched_at'] ?? null;

            if ($fetchedAt === null || ! $this->isWithinHours((string) $fetchedAt, $maxHours)) {
                $need[] = $courier;
            }
        }

        return $need;
    }

    /**
     * @return list<string>
     */
    public function configuredCouriers(): array
    {
        $couriers = ['steadfast', 'pathao', 'paperfly'];

        if (config('fraud_check.include_redx', true)) {
            $couriers[] = 'redx';
        }

        if (config('fraud_check.include_carrybee', true)) {
            $couriers[] = 'carrybee';
        }

        return $couriers;
    }

    /**
     * @param  array<string, mixed>  $platformData
     */
    private function hasFailedSnapshots(array $platformData): bool
    {
        foreach ($platformData['courier_stats'] ?? [] as $snapshot) {
            if (is_array($snapshot) && ! empty($snapshot['fetch_failed'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $platformData
     */
    private function hasUsefulSuccessfulSnapshots(array $platformData): bool
    {
        foreach ($platformData['courier_stats'] ?? [] as $snapshot) {
            if (! is_array($snapshot) || ! empty($snapshot['fetch_failed'])) {
                continue;
            }

            if (
                (int) ($snapshot['total_order'] ?? 0) > 0
                || (int) ($snapshot['confirmed'] ?? 0) > 0
                || (int) ($snapshot['cancel'] ?? 0) > 0
                || (int) ($snapshot['frauds_count'] ?? 0) > 0
                || filled($snapshot['customer_rating'] ?? null)
            ) {
                return true;
            }
        }

        return false;
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
     * Snapshots are fresh when every non-failed configured courier row
     * was fetched within the TTL window.
     *
     * @param  array<string, mixed>  $platformData
     */
    private function hasFreshCourierSnapshots(array $platformData, bool $strict = true): bool
    {
        return $this->couriersNeedingRefresh($platformData) === [];
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

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotIsRatingOnly(array $snapshot): bool
    {
        if (
            (int) ($snapshot['total_order'] ?? 0) > 0
            || (int) ($snapshot['confirmed'] ?? 0) > 0
            || (int) ($snapshot['cancel'] ?? 0) > 0
        ) {
            return false;
        }

        return filled($snapshot['customer_rating'] ?? null)
            || ($snapshot['data_type'] ?? '') === 'rating';
    }
}
