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
        $couriers = $this->extractCourierReportsByTitle($report);

        $steadfast = $couriers['steadfast'];
        $pathao = $couriers['pathao'];
        $paperfly = $couriers['paperfly'];
        $redx = $couriers['redx'];
        $carrybee = $couriers['carrybee'];

        if (! empty($report['frauds']) && empty($steadfast['frauds'])) {
            $steadfast['frauds'] = $report['frauds'];
        }

        $this->fraudCheckIngestor->ingest(
            $context,
            $steadfast,
            $pathao,
            $paperfly,
            $redx,
            $carrybee,
        );
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
     * @return array{
     *     steadfast: array<string, mixed>,
     *     pathao: array<string, mixed>,
     *     paperfly: array<string, mixed>,
     *     redx: array<string, mixed>,
     *     carrybee: array<string, mixed>
     * }
     */
    private function extractCourierReportsByTitle(array $report): array
    {
        $mapped = [
            'steadfast' => [],
            'pathao' => [],
            'paperfly' => [],
            'redx' => [],
            'carrybee' => [],
        ];

        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry) || ! is_array($entry['report'] ?? null)) {
                continue;
            }

            $title = strtolower(trim((string) ($entry['title'] ?? '')));

            $key = match (true) {
                str_contains($title, 'stead') => 'steadfast',
                str_contains($title, 'pathao') => 'pathao',
                str_contains($title, 'paper') => 'paperfly',
                str_contains($title, 'redx') || str_contains($title, 'red x') => 'redx',
                str_contains($title, 'carry') => 'carrybee',
                default => null,
            };

            if ($key !== null) {
                $mapped[$key] = $entry['report'];
            }
        }

        return $mapped;
    }
}
