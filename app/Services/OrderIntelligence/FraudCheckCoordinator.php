<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Jobs\OrderIntelligence\ProjectCustomerStatsJob;
use App\Jobs\OrderIntelligence\RefreshCourierSnapshotsJob;
use App\Models\AccessToken;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Services\Courier\CourierAccountService;
use App\Services\FraudCheckService;
use Illuminate\Http\Request;

class FraudCheckCoordinator
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private FraudCheckIngestor $fraudCheckIngestor,
        private PlatformIntelligenceReader $platformIntelligenceReader,
        private PlatformSufficiencyChecker $sufficiencyChecker,
        private LegacyReportFormatter $legacyReportFormatter,
        private CourierAccountService $courierAccountService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkSingle(Request $request, array $payload): array
    {
        $phone = (string) ($payload['phone'] ?? '');
        $accessToken = $this->courierAccountService->resolveAccessToken($request);
        $phoneNormalized = $this->fraudCheckService->normalizePhone($phone);
        $context = $this->fraudCheckIngestor->resolveContextFromToken($accessToken, $payload);

        $platformData = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessToken?->id);

        if ($this->sufficiencyChecker->shouldUsePlatform($platformData)) {
            $report = $this->legacyReportFormatter->format($platformData);
            $this->fraudCheckIngestor->ingestOrderContext($context);

            if ($this->sufficiencyChecker->shouldRefreshSnapshots($platformData)) {
                RefreshCourierSnapshotsJob::dispatch($phoneNormalized, $accessToken?->id);
            }

            return $report;
        }

        $report = $this->fraudCheckService->getReport($phone);
        $report['source'] = $platformData ? 'hybrid' : 'external';

        $this->ingestExternalReport($accessToken, $payload, $report);

        return $this->attachPlatformData($report, $phoneNormalized, $accessToken?->id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function checkMultiple(Request $request, array $items): array
    {
        $accessToken = $this->courierAccountService->resolveAccessToken($request);
        $users = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['phone'])) {
                continue;
            }

            $itemPayload = [...$item, 'phone' => (string) $item['phone']];
            $users[] = [
                ...$item,
                'report' => $this->checkSingle($request, $itemPayload),
            ];
        }

        return $users;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $report
     */
    private function ingestExternalReport(?AccessToken $accessToken, array $payload, array $report): void
    {
        $context = $this->fraudCheckIngestor->resolveContextFromToken($accessToken, $payload);
        [$steadfast, $pathao, $paperfly] = $this->extractCourierReports($report);

        if (! empty($report['frauds']) && empty($steadfast['frauds'])) {
            $steadfast['frauds'] = $report['frauds'];
        }

        $this->fraudCheckIngestor->ingest($context, $steadfast, $pathao, $paperfly);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function attachPlatformData(array $report, string $phoneNormalized, ?int $accessTokenId): array
    {
        try {
            $platformData = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessTokenId);

            if ($platformData === null) {
                return $report;
            }

            $report['platform_intelligence'] = $platformData['platform_intelligence'] ?? null;
            $report['your_store'] = $platformData['your_store'] ?? null;
            $report['courier_fraud_notes'] = $platformData['courier_fraud_notes'] ?? [];

            if (! empty($platformData['courier_fraud_notes'])) {
                $report['frauds'] = array_map(
                    fn (array $note) => [
                        'name' => $note['name'] ?? null,
                        'details' => $note['details'] ?? '',
                        'consignment_id' => $note['consignment_id'] ?? null,
                        'created_at' => $note['created_at'] ?? null,
                    ],
                    $platformData['courier_fraud_notes'],
                );
            }
        } catch (\Throwable) {
            // Keep external report if platform enrichment fails.
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function extractCourierReports(array $report): array
    {
        $couriers = $report['courier'] ?? [];

        return [
            is_array($couriers[0]['report'] ?? null) ? $couriers[0]['report'] : [],
            is_array($couriers[1]['report'] ?? null) ? $couriers[1]['report'] : [],
            is_array($couriers[2]['report'] ?? null) ? $couriers[2]['report'] : [],
        ];
    }
}
