<?php

namespace App\Services\OrderIntelligence;

use App\Services\FraudCheck\CourierReportFormatter;

class LegacyReportFormatter
{
    /**
     * @param  array<string, mixed>  $platformData
     * @return array<string, mixed>
     */
    public function format(array $platformData): array
    {
        $courierStats = $platformData['courier_stats'] ?? [];
        $steadfast = $this->snapshotToReport($this->findSnapshot($courierStats, 'steadfast'));
        $pathao = $this->snapshotToReport($this->findSnapshot($courierStats, 'pathao'));
        $paperfly = $this->snapshotToReport($this->findSnapshot($courierStats, 'paperfly'));
        $redx = $this->snapshotToReport($this->findSnapshot($courierStats, 'redx'));
        $carrybee = $this->snapshotToReport($this->findSnapshot($courierStats, 'carrybee'));

        $aggregateReports = [$steadfast, $pathao, $paperfly];
        if (config('fraud_check.include_redx', true) && config('fraud_check.aggregate_redx', true)) {
            $aggregateReports[] = $redx;
        }
        if (config('fraud_check.include_carrybee', true) && config('fraud_check.aggregate_carrybee', true)) {
            $aggregateReports[] = $carrybee;
        }

        $totals = CourierReportFormatter::aggregateTotals(...$aggregateReports);
        $totalOrder = (int) ceil($totals['total_order']);
        $confirmOrder = (int) ceil($totals['confirmed']);
        $cancelOrder = (int) ceil($totals['cancel']);

        if ($totalOrder === 0) {
            [$totalOrder, $confirmOrder, $cancelOrder] = $this->totalsFromPlatformCounts(
                $platformData['platform_intelligence']['counts'] ?? [],
            );
        }

        $frauds = $this->formatFrauds($platformData['courier_fraud_notes'] ?? []);
        $carrybeeFraudsCount = (int) ($carrybee['frauds_count'] ?? 0);
        $successRate = $this->resolveSuccessRate(
            $totalOrder,
            $confirmOrder,
            $platformData['platform_intelligence']['rates'] ?? [],
            $steadfast,
            $pathao,
            $paperfly,
            $redx,
            $carrybee,
        );

        $courier = [
            ['title' => 'Stead Fast', 'report' => $this->withPlatformCourierMeta($steadfast, 'Steadfast')],
            ['title' => 'Pathao', 'report' => $this->withPlatformCourierMeta($pathao, 'Pathao')],
            ['title' => 'Paper Fly', 'report' => $this->withPlatformCourierMeta($paperfly, 'Paperfly')],
        ];

        if (config('fraud_check.include_redx', true)) {
            $courier[] = ['title' => 'RedX', 'report' => $this->withPlatformCourierMeta($redx, 'RedX')];
        }

        if (config('fraud_check.include_carrybee', true)) {
            $courier[] = ['title' => 'Carrybee', 'report' => $this->withPlatformCourierMeta($carrybee, 'Carrybee')];
        }

        return [
            'total_order' => $totalOrder,
            'confirmed' => $confirmOrder,
            'frauds' => $frauds,
            'cancel' => $cancelOrder,
            'success_rate' => $successRate,
            'carrybee_frauds_count' => $carrybeeFraudsCount,
            'courier' => $courier,
            'platform_intelligence' => $platformData['platform_intelligence'] ?? null,
            'your_store' => $platformData['your_store'] ?? null,
            'courier_fraud_notes' => $platformData['courier_fraud_notes'] ?? [],
            'source' => 'platform',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshots
     * @return array<string, mixed>
     */
    private function findSnapshot(array $snapshots, string $courier): array
    {
        foreach ($snapshots as $snapshot) {
            if (is_array($snapshot) && ($snapshot['courier'] ?? '') === $courier) {
                return $snapshot;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function snapshotToReport(array $snapshot): array
    {
        if ($snapshot === []) {
            return CourierReportFormatter::emptyReport(['source' => 'platform_cache']);
        }

        $courier = (string) ($snapshot['courier'] ?? '');
        $fraudsCount = (int) ($snapshot['frauds_count'] ?? 0);

        if ($courier === 'carrybee' && (int) ($snapshot['total_order'] ?? 0) === 0 && $fraudsCount > 0) {
            return CourierReportFormatter::emptyReport([
                'data_type' => 'fraud_reports',
                'frauds_count' => $fraudsCount,
                'api_success' => true,
                'success_rate' => "{$fraudsCount} fraud report(s)",
                'source' => 'platform_cache',
                'fetched_at' => $snapshot['fetched_at'] ?? null,
            ]);
        }

        $extra = array_filter([
            'total_order' => (int) ($snapshot['total_order'] ?? 0),
            'success_rate' => $snapshot['success_rate'] ?? null,
            'customer_rating' => $snapshot['customer_rating'] ?? null,
            'frauds_count' => $fraudsCount > 0 ? $fraudsCount : null,
            'source' => 'platform_cache',
            'fetched_at' => $snapshot['fetched_at'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($snapshot['customer_rating']) && (int) ($snapshot['total_order'] ?? 0) === 0) {
            $extra['data_type'] = 'rating';
        }

        return CourierReportFormatter::fromCounts(
            (int) ($snapshot['confirmed'] ?? 0),
            (int) ($snapshot['cancel'] ?? 0),
            $extra,
        );
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{0: int, 1: int, 2: int}
     */
    private function totalsFromPlatformCounts(array $counts): array
    {
        $newOrder = (int) ($counts['new_order'] ?? 0);
        $courierEntry = (int) ($counts['courier_entry'] ?? 0)
            + (int) ($counts['courier_handover'] ?? 0)
            + (int) ($counts['delivered'] ?? 0)
            + (int) ($counts['partially_delivered'] ?? 0)
            + (int) ($counts['returned'] ?? 0);
        $delivered = (int) ($counts['delivered'] ?? 0) + (int) ($counts['partially_delivered'] ?? 0);
        $returned = (int) ($counts['returned'] ?? 0);
        $canceled = (int) ($counts['canceled'] ?? 0);

        $total = max($newOrder, array_sum($counts));
        $confirmed = max($courierEntry, $delivered);
        $cancel = $returned + $canceled;

        return [$total, $confirmed, $cancel];
    }

    /**
     * @param  array<int, array<string, mixed>>  $notes
     * @return array<int, array<string, mixed>>
     */
    private function formatFrauds(array $notes): array
    {
        return array_map(
            fn (array $note) => [
                'name' => $note['name'] ?? null,
                'details' => $note['details'] ?? '',
                'consignment_id' => $note['consignment_id'] ?? null,
                'created_at' => $note['created_at'] ?? null,
                'courier' => $note['courier'] ?? null,
            ],
            $notes,
        );
    }

    /**
     * @param  array<string, string>  $rates
     * @param  array<string, mixed>  $steadfast
     * @param  array<string, mixed>  $pathao
     * @param  array<string, mixed>  $paperfly
     * @param  array<string, mixed>  $redx
     * @param  array<string, mixed>  $carrybee
     */
    private function resolveSuccessRate(
        int $totalOrder,
        int $confirmOrder,
        array $rates,
        array $steadfast,
        array $pathao,
        array $paperfly,
        array $redx = [],
        array $carrybee = [],
    ): string {
        if ($totalOrder > 0) {
            return ceil(($confirmOrder / $totalOrder) * 100) . '%';
        }

        if (! empty($rates['delivery_rate'])) {
            return (string) $rates['delivery_rate'];
        }

        foreach ([$steadfast, $pathao, $paperfly, $redx, $carrybee] as $report) {
            if (($report['data_type'] ?? 'delivery') === 'fraud_reports') {
                continue;
            }

            if (! empty($report['customer_rating'])) {
                return CourierReportFormatter::formatRating((string) $report['customer_rating']);
            }

            $rate = $report['success_rate'] ?? '';

            if ($rate !== '' && $rate !== 'No order history found!') {
                return $rate;
            }
        }

        return 'No order history found!';
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function withPlatformCourierMeta(array $report, string $courier): array
    {
        $report['source'] = $report['source'] ?? 'platform_cache';
        $report['from_cache'] = true;

        if (
            ($report['total_order'] ?? 0) > 0
            || ! empty($report['frauds'])
            || (int) ($report['frauds_count'] ?? 0) > 0
        ) {
            $report['status'] = 'ok';
            $report['cache_label'] = 'platform cache';

            return $report;
        }

        if (($report['data_type'] ?? 'delivery') === 'fraud_reports') {
            $report['status'] = 'ok';
            if ((int) ($report['frauds_count'] ?? 0) === 0) {
                $report['message'] = 'No fraud reports found on Carrybee (platform cache).';
            }
            $report['cache_label'] = 'platform cache';

            return $report;
        }

        if (($report['data_type'] ?? 'delivery') === 'rating' || ! empty($report['customer_rating'])) {
            $report['status'] = 'rating_only';
            $report['cache_label'] = 'platform cache';

            return $report;
        }

        $rate = $report['success_rate'] ?? '';

        if ($rate !== '' && $rate !== 'No order history found!') {
            $report['status'] = 'ok';
            $report['cache_label'] = 'platform cache';

            return $report;
        }

        $report['status'] = 'ok';
        $report['cache_label'] = 'platform cache';
        $report['message'] = match ($courier) {
            'Steadfast' => 'No delivery history found on Steadfast (platform cache).',
            'Paperfly' => 'No delivery records found on Paperfly (platform cache).',
            'RedX' => 'No delivery history found on RedX (platform cache).',
            'Carrybee' => 'No delivery history found on Carrybee (platform cache).',
            default => 'No delivery history found (platform cache).',
        };

        return $report;
    }
}