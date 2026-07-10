<?php

namespace App\Services\FraudCheck;

use Throwable;

/**
 * Builds a reliable multi-courier fraud report using our own HTTP clients.
 */
class CourierPackageRepairService
{
    public function __construct(
        private SteadfastFraudChecker $steadfastFraudChecker,
        private PathaoFraudChecker $pathaoFraudChecker,
        private PaperflyFraudChecker $paperflyFraudChecker,
        private RedxFraudChecker $redxFraudChecker,
        private CarrybeeFraudChecker $carrybeeFraudChecker,
    ) {}

    /**
     * @return array{
     *     raw: array,
     *     repaired: array,
     *     repairs: array<int, array{courier: string, action: string, detail: string}>,
     *     analysis: array<int, string>
     * }
     */
    public function checkAndRepair(string $phone): array
    {
        $repairs = [];
        $analysis = [
            'Using internal checkers for all couriers.',
        ];

        $repaired = [
            'steadfast' => $this->mapSteadfast($phone, $repairs),
            'pathao' => $this->mapPathao($phone, $repairs),
            'paperfly' => $this->mapPaperfly($phone, $repairs),
            'redx' => $this->mapRedx($phone, $repairs, $analysis),
            'carrybee' => $this->mapCarrybee($phone, $repairs, $analysis),
        ];

        $repaired['aggregate'] = $this->rebuildAggregate($repaired);

        return [
            'raw' => [
                'note' => 'Internal checkers only — no third-party fraud package.',
            ],
            'repaired' => $repaired,
            'repairs' => $repairs,
            'analysis' => array_values(array_unique($analysis)),
        ];
    }

    /**
     * @param  array<int, array{courier: string, action: string, detail: string}>  $repairs
     * @return array<string, mixed>
     */
    private function mapSteadfast(string $phone, array &$repairs): array
    {
        $attempts = 3;
        $lastDetail = 'Unknown Steadfast failure';

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                if ($i > 1) {
                    usleep(700000 * ($i - 1));
                    $this->steadfastFraudChecker->expireSession();
                }

                $report = $this->steadfastFraudChecker->check($phone);
                if ($this->hasUsableDeliveryCounts($report)) {
                    $repairs[] = [
                        'courier' => 'steadfast',
                        'action' => 'internal_ok',
                        'detail' => "Steadfast OK on attempt {$i}/{$attempts}.",
                    ];

                    return $this->fromInternalDeliveryReport($report);
                }

                $lastDetail = "Attempt {$i}/{$attempts}: empty response.";
            } catch (Throwable $e) {
                $lastDetail = "Attempt {$i}/{$attempts}: ".$e->getMessage();
            }
        }

        $repairs[] = [
            'courier' => 'steadfast',
            'action' => 'failed',
            'detail' => $lastDetail,
        ];

        return $this->unavailable('internal', $lastDetail);
    }

    /**
     * @param  array<int, array{courier: string, action: string, detail: string}>  $repairs
     * @return array<string, mixed>
     */
    private function mapPathao(string $phone, array &$repairs): array
    {
        try {
            $report = $this->pathaoFraudChecker->check($phone);
            $mapped = $this->fromInternalPathaoReport($report);
            if ($mapped !== null) {
                $repairs[] = [
                    'courier' => 'pathao',
                    'action' => 'internal_ok',
                    'detail' => ($report['data_type'] ?? '') === 'rating'
                        ? 'Pathao rating-only API (show_count=false).'
                        : 'Pathao delivery counts loaded.',
                ];

                return $mapped;
            }
        } catch (Throwable $e) {
            $repairs[] = ['courier' => 'pathao', 'action' => 'failed', 'detail' => $e->getMessage()];

            return $this->unavailable('internal', $e->getMessage());
        }

        $repairs[] = ['courier' => 'pathao', 'action' => 'empty', 'detail' => 'No Pathao data.'];

        return [
            'success' => 0,
            'cancel' => 0,
            'total' => 0,
            'success_ratio' => 0,
            'source' => 'internal',
        ];
    }

    /**
     * @param  array<int, array{courier: string, action: string, detail: string}>  $repairs
     * @return array<string, mixed>
     */
    private function mapPaperfly(string $phone, array &$repairs): array
    {
        try {
            $report = $this->paperflyFraudChecker->check($phone);
            $repairs[] = ['courier' => 'paperfly', 'action' => 'internal_ok', 'detail' => 'Paperfly smart-check.'];

            return $this->fromInternalDeliveryReport($report);
        } catch (Throwable $e) {
            $repairs[] = ['courier' => 'paperfly', 'action' => 'failed', 'detail' => $e->getMessage()];

            return $this->unavailable('internal', $e->getMessage());
        }
    }

    /**
     * @param  array<int, array{courier: string, action: string, detail: string}>  $repairs
     * @param  array<int, string>  $analysis
     * @return array<string, mixed>
     */
    private function mapRedx(string $phone, array &$repairs, array &$analysis): array
    {
        if (! $this->redxFraudChecker->isConfigured()) {
            $repairs[] = ['courier' => 'redx', 'action' => 'skipped', 'detail' => 'Missing REDX credentials.'];
            $analysis[] = 'RedX skipped — set REDX_PHONE / REDX_PASSWORD.';

            return $this->unavailable('internal', 'Missing REDX credentials');
        }

        try {
            $report = $this->redxFraudChecker->check($phone);
            if (! empty($report['unavailable'])) {
                $repairs[] = ['courier' => 'redx', 'action' => 'failed', 'detail' => (string) ($report['message'] ?? 'RedX unavailable')];
                $analysis[] = 'RedX request failed after credential login.';

                return $this->unavailable('internal', (string) ($report['message'] ?? 'RedX unavailable'));
            }

            $repairs[] = ['courier' => 'redx', 'action' => 'internal_ok', 'detail' => 'RedX login + customer success rate OK.'];

            return array_merge($this->fromInternalDeliveryReport($report), [
                'customer_segment' => $report['customer_segment'] ?? null,
            ]);
        } catch (Throwable $e) {
            $repairs[] = ['courier' => 'redx', 'action' => 'failed', 'detail' => $e->getMessage()];

            return $this->unavailable('internal', $e->getMessage());
        }
    }

    /**
     * @param  array<int, array{courier: string, action: string, detail: string}>  $repairs
     * @param  array<int, string>  $analysis
     * @return array<string, mixed>
     */
    private function mapCarrybee(string $phone, array &$repairs, array &$analysis): array
    {
        if (! $this->carrybeeFraudChecker->isConfigured()) {
            $repairs[] = ['courier' => 'carrybee', 'action' => 'skipped', 'detail' => 'Missing Carrybee credentials.'];
            $analysis[] = 'Carrybee skipped — set CARRYBEE_PHONE / CARRYBEE_PASSWORD.';

            return $this->unavailable('internal', 'Missing Carrybee credentials');
        }

        try {
            $report = $this->carrybeeFraudChecker->check($phone);
            if (! empty($report['unavailable'])) {
                $repairs[] = ['courier' => 'carrybee', 'action' => 'failed', 'detail' => (string) ($report['message'] ?? 'Carrybee unavailable')];

                return $this->unavailable('internal', (string) ($report['message'] ?? 'Carrybee unavailable'));
            }

            $count = (int) ($report['frauds_count'] ?? 0);
            $repairs[] = [
                'courier' => 'carrybee',
                'action' => 'internal_ok',
                'detail' => "Carrybee fraud-report count: {$count} (API does not expose delivery totals).",
            ];
            $analysis[] = 'Carrybee API returns fraud-report count only (not delivered/cancel totals).';

            return [
                'success' => 0,
                'cancel' => 0,
                'total' => 0,
                'success_ratio' => 0,
                'frauds_count' => $count,
                'data_type' => 'fraud_reports',
                'success_rate' => $report['success_rate'] ?? null,
                'source' => 'internal',
            ];
        } catch (Throwable $e) {
            $repairs[] = ['courier' => 'carrybee', 'action' => 'failed', 'detail' => $e->getMessage()];

            return $this->unavailable('internal', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $source, string $message): array
    {
        return [
            'success' => 0,
            'cancel' => 0,
            'total' => 0,
            'success_ratio' => 0,
            'source' => $source,
            'unavailable' => true,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function hasUsableDeliveryCounts(array $report): bool
    {
        return (int) ($report['total_order'] ?? 0) > 0
            || (int) ($report['confirmed'] ?? 0) > 0
            || (int) ($report['cancel'] ?? 0) > 0
            || ! empty($report['api_success']);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function fromInternalDeliveryReport(array $report): array
    {
        $success = (int) ($report['confirmed'] ?? 0);
        $cancel = (int) ($report['cancel'] ?? 0);
        $total = (int) ($report['total_order'] ?? ($success + $cancel));

        if ($total === 0 && ($success > 0 || $cancel > 0)) {
            $total = $success + $cancel;
        }

        return [
            'success' => $success,
            'cancel' => $cancel,
            'total' => $total,
            'success_ratio' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'source' => 'internal',
            'customer_rating' => $report['customer_rating'] ?? null,
            'frauds' => $report['frauds'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>|null
     */
    private function fromInternalPathaoReport(array $report): ?array
    {
        if (($report['data_type'] ?? '') === 'rating' || ! empty($report['customer_rating'])) {
            $estimated = CourierReportFormatter::estimatedSuccessPercent(
                isset($report['customer_rating']) ? (string) $report['customer_rating'] : null
            );

            return [
                'success' => (int) ($report['confirmed'] ?? 0),
                'cancel' => (int) ($report['cancel'] ?? 0),
                'total' => (int) ($report['total_order'] ?? 0),
                'success_ratio' => $estimated ?? 0,
                'customer_rating' => $report['customer_rating'] ?? null,
                'estimated_success_rate' => $report['estimated_success_rate'] ?? ($estimated !== null ? $estimated.'%' : null),
                'data_type' => 'rating',
                'source' => 'internal',
            ];
        }

        if ($this->hasUsableDeliveryCounts($report)) {
            return $this->fromInternalDeliveryReport($report);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float|int>
     */
    private function rebuildAggregate(array $payload): array
    {
        $success = 0;
        $cancel = 0;

        foreach (['steadfast', 'pathao', 'redx', 'paperfly', 'carrybee'] as $courier) {
            $row = $payload[$courier] ?? null;
            if (! is_array($row) || ! empty($row['unavailable'])) {
                continue;
            }

            if (($row['data_type'] ?? '') === 'rating' && (int) ($row['total'] ?? 0) === 0) {
                continue;
            }

            if (($row['data_type'] ?? '') === 'fraud_reports') {
                continue;
            }

            if (! is_numeric($row['success'] ?? null) || ! is_numeric($row['cancel'] ?? null)) {
                continue;
            }

            $success += (int) $row['success'];
            $cancel += (int) $row['cancel'];
        }

        $total = $success + $cancel;

        return [
            'total_success' => $success,
            'total_cancel' => $cancel,
            'total_deliveries' => $total,
            'success_ratio' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'cancel_ratio' => $total > 0 ? round(($cancel / $total) * 100, 2) : 0,
        ];
    }
}
