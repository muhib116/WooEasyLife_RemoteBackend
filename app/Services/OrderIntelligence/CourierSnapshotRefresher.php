<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\PlatformCustomer;
use App\Services\FraudCheckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourierSnapshotRefresher
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private CourierIntelPersister $courierIntelPersister,
        private IntelligenceCache $cache,
        private PlatformSufficiencyChecker $sufficiencyChecker,
    ) {}

    /**
     * @param  list<string>|null  $onlyCouriers  When set, only these couriers are re-fetched.
     */
    public function refresh(string $phoneNormalized, ?int $accessTokenId = null, ?array $onlyCouriers = null): bool
    {
        if (! config('order_intelligence.enabled', true)) {
            return false;
        }

        $customer = PlatformCustomer::query()
            ->where('phone_normalized', $phoneNormalized)
            ->first();

        if (! $customer) {
            return false;
        }

        $targets = $this->resolveTargets($onlyCouriers);

        if ($targets === []) {
            return false;
        }

        $cooldownSeconds = max(60, (int) config('order_intelligence.fraud_check.refresh_unique_for_seconds', 900));
        $reports = [
            'steadfast' => [],
            'pathao' => [],
            'paperfly' => [],
            'redx' => [],
            'carrybee' => [],
        ];
        $attempted = [];

        foreach ($targets as $courier) {
            $cooldownKey = 'fraud_check_snapshot_refresh_cooldown:'.$phoneNormalized.':'.$courier;

            // Soft lock per courier so one partner refresh does not block the others.
            if (! Cache::add($cooldownKey, true, $cooldownSeconds)) {
                continue;
            }

            try {
                $reports[$courier] = $this->fraudCheckService->checkCourier($courier, $phoneNormalized);
                $attempted[] = $courier;
            } catch (\Throwable $exception) {
                Log::warning('Courier snapshot refresh failed for partner.', [
                    'phone' => $phoneNormalized,
                    'courier' => $courier,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($attempted === []) {
            return false;
        }

        try {
            $this->courierIntelPersister->persistFromFraudCheckReports(
                customer: $customer,
                phoneNormalized: $phoneNormalized,
                steadfastReport: $reports['steadfast'],
                pathaoReport: $reports['pathao'],
                paperflyReport: $reports['paperfly'],
                redxReport: $reports['redx'],
                carrybeeReport: $reports['carrybee'],
                sourceAccessTokenId: $accessTokenId,
            );

            $this->cache->forget($phoneNormalized, $accessTokenId);

            Log::info('Courier snapshot refresh completed.', [
                'phone' => $phoneNormalized,
                'couriers' => $attempted,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Courier snapshot refresh persist failed.', [
                'phone' => $phoneNormalized,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  list<string>|null  $onlyCouriers
     * @return list<string>
     */
    private function resolveTargets(?array $onlyCouriers): array
    {
        $configured = $this->sufficiencyChecker->configuredCouriers();

        if (! config('order_intelligence.fraud_check.partial_refresh', true) || $onlyCouriers === null) {
            return $configured;
        }

        $targets = [];

        foreach ($onlyCouriers as $courier) {
            $courier = strtolower(trim((string) $courier));

            if (in_array($courier, $configured, true)) {
                $targets[] = $courier;
            }
        }

        return array_values(array_unique($targets));
    }
}
