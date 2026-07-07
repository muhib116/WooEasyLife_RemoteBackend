<?php

namespace App\Jobs\OrderIntelligence;

use App\Models\OrderIntelligence\PlatformCustomer;
use App\Services\FraudCheckService;
use App\Services\OrderIntelligence\CourierIntelPersister;
use App\Services\OrderIntelligence\IntelligenceCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshCourierSnapshotsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $phoneNormalized,
        public ?int $accessTokenId = null,
    ) {}

    public function handle(
        FraudCheckService $fraudCheckService,
        CourierIntelPersister $courierIntelPersister,
        IntelligenceCache $cache,
    ): void {
        if (! config('order_intelligence.enabled', true)) {
            return;
        }

        $customer = PlatformCustomer::query()
            ->where('phone_normalized', $this->phoneNormalized)
            ->first();

        if (! $customer) {
            return;
        }

        $report = $fraudCheckService->getReport($this->phoneNormalized);
        $couriers = $report['courier'] ?? [];

        $courierIntelPersister->persistFromFraudCheckReports(
            customer: $customer,
            phoneNormalized: $this->phoneNormalized,
            steadfastReport: is_array($couriers[0]['report'] ?? null) ? $couriers[0]['report'] : [],
            pathaoReport: is_array($couriers[1]['report'] ?? null) ? $couriers[1]['report'] : [],
            paperflyReport: is_array($couriers[2]['report'] ?? null) ? $couriers[2]['report'] : [],
            sourceAccessTokenId: $this->accessTokenId,
        );

        $cache->forget($this->phoneNormalized, $this->accessTokenId);
    }
}
