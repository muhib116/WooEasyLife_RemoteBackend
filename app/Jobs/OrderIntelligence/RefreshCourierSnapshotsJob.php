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
        $couriers = $this->extractCourierReportsByTitle($report);

        $courierIntelPersister->persistFromFraudCheckReports(
            customer: $customer,
            phoneNormalized: $this->phoneNormalized,
            steadfastReport: $couriers['steadfast'],
            pathaoReport: $couriers['pathao'],
            paperflyReport: $couriers['paperfly'],
            redxReport: $couriers['redx'],
            carrybeeReport: $couriers['carrybee'],
            sourceAccessTokenId: $this->accessTokenId,
        );

        $cache->forget($this->phoneNormalized, $this->accessTokenId);
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
