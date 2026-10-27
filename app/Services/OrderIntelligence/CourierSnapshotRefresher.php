<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\PlatformCustomer;
use App\Services\FraudCheck\CourierCallContext;
use App\Services\FraudCheck\CourierTransport;
use App\Services\FraudCheck\MerchantBulkDailySync;
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
        private PlatformIntelligenceReader $platformIntelligenceReader,
        private LegacyReportFormatter $legacyReportFormatter,
        private MerchantBulkDailySync $merchantBulkDailySync,
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
                // A background refresh never takes the slot kept for live checks.
                $report = app(CourierCallContext::class)->asBulk(
                    fn () => $this->fraudCheckService->checkCourier($courier, $phoneNormalized),
                );

                // Not asked because the courier had no room: try again on a later request.
                if (! empty($report['deferred'])) {
                    Cache::forget($cooldownKey);

                    continue;
                }

                $reports[$courier] = $report;
                $attempted[] = $courier;
            } catch (\Throwable $exception) {
                Log::warning('Courier snapshot refresh failed for partner.', [
                    'phone' => CourierTransport::maskPhone($phoneNormalized),
                    'courier' => $courier,
                    'message' => CourierTransport::safeMessage($exception),
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
            $this->syncBulkRow($phoneNormalized, $accessTokenId);

            Log::info('Courier snapshot refresh completed.', [
                'phone' => CourierTransport::maskPhone($phoneNormalized),
                'couriers' => $attempted,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Courier snapshot refresh persist failed.', [
                'phone' => CourierTransport::maskPhone($phoneNormalized),
                'message' => CourierTransport::safeMessage($exception),
            ]);

            return false;
        }
    }

    private function syncBulkRow(string $phoneNormalized, ?int $accessTokenId): void
    {
        try {
            $platform = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessTokenId);

            if (! is_array($platform)) {
                return;
            }

            $this->merchantBulkDailySync->syncIfPresent(
                $phoneNormalized,
                $this->legacyReportFormatter->format($platform),
            );
        } catch (\Throwable $exception) {
            Log::warning('Courier snapshot refresh did not update the bulk row.', [
                'phone' => CourierTransport::maskPhone($phoneNormalized),
                'message' => CourierTransport::safeMessage($exception),
            ]);
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
