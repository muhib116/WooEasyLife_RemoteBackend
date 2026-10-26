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

        if ($totalOrder === 0 && ! $this->hasCheckedCourierSnapshots($courierStats)) {
            [$totalOrder, $confirmOrder, $cancelOrder] = $this->totalsFromPlatformCounts(
                $platformData['platform_intelligence']['counts'] ?? [],
            );
        }

        $frauds = $this->formatFrauds($platformData['courier_fraud_notes'] ?? []);
        $steadfast = $this->attachCourierFrauds($steadfast, $frauds, 'steadfast');
        $carrybee = $this->attachCourierFrauds($carrybee, $frauds, 'carrybee');
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
            return CourierReportFormatter::emptyReport([
                'source' => 'platform_cache',
                'unavailable' => true,
                'not_checked' => true,
            ]);
        }

        $courier = (string) ($snapshot['courier'] ?? '');
        $fraudsCount = (int) ($snapshot['frauds_count'] ?? 0);

        // A courier that did not answer last time has no history to show.
        // Saved counts from an earlier good answer are still shown.
        if (! empty($snapshot['fetch_failed']) && ! $this->snapshotHasHistory($snapshot)) {
            return CourierReportFormatter::emptyReport(array_filter([
                'source' => 'platform_cache',
                'unavailable' => true,
                'retryable' => ($snapshot['retryable'] ?? true) !== false,
                'rate_limited' => ! empty($snapshot['rate_limited']) ? true : null,
                'fetched_at' => $snapshot['fetched_at'] ?? null,
            ], fn ($value) => $value !== null));
        }

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

        $partial = (int) ($snapshot['partial'] ?? 0);
        $rating = isset($snapshot['customer_rating']) && filled($snapshot['customer_rating'])
            ? (string) $snapshot['customer_rating']
            : null;
        $estimatedRate = $snapshot['estimated_success_rate'] ?? null;
        if ($estimatedRate === null && $rating !== null) {
            $estimatedPercent = CourierReportFormatter::estimatedSuccessPercent($rating);
            $estimatedRate = $estimatedPercent !== null ? $estimatedPercent.'%' : null;
        }

        $extra = array_filter([
            'total_order' => (int) ($snapshot['total_order'] ?? 0),
            'partial' => $partial > 0 ? $partial : null,
            'success_rate' => $snapshot['success_rate'] ?? ($rating !== null ? CourierReportFormatter::formatRating($rating) : null),
            'customer_rating' => $rating,
            'estimated_success_rate' => $estimatedRate,
            'delivery_ratio' => $snapshot['delivery_ratio'] ?? null,
            'cancellation_ratio' => $snapshot['cancellation_ratio'] ?? null,
            'counts_hidden' => ! empty($snapshot['counts_hidden']) ? true : null,
            'history_unknown' => ! empty($snapshot['history_unknown']) ? true : null,
            'api_success' => ! empty($snapshot['api_success']) ? true : null,
            'frauds' => ! empty($snapshot['frauds']) && is_array($snapshot['frauds']) ? $snapshot['frauds'] : null,
            'frauds_count' => $fraudsCount > 0 ? $fraudsCount : null,
            'source' => 'platform_cache',
            'fetched_at' => $snapshot['fetched_at'] ?? null,
        ], fn ($value) => $value !== null);

        if ($rating !== null && (int) ($snapshot['total_order'] ?? 0) === 0) {
            $extra['data_type'] = 'rating';
        }

        return CourierReportFormatter::fromCounts(
            (int) ($snapshot['confirmed'] ?? 0),
            (int) ($snapshot['cancel'] ?? 0),
            $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotHasHistory(array $snapshot): bool
    {
        return (int) ($snapshot['total_order'] ?? 0) > 0
            || (int) ($snapshot['confirmed'] ?? 0) > 0
            || (int) ($snapshot['cancel'] ?? 0) > 0
            || (int) ($snapshot['frauds_count'] ?? 0) > 0
            || filled($snapshot['customer_rating'] ?? null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshots
     */
    private function hasCheckedCourierSnapshots(array $snapshots): bool
    {
        foreach ($snapshots as $snapshot) {
            if (is_array($snapshot) && empty($snapshot['fetch_failed']) && filled($snapshot['courier'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<int, array<string, mixed>>  $frauds
     * @return array<string, mixed>
     */
    private function attachCourierFrauds(array $report, array $frauds, string $courier): array
    {
        $matched = array_values(array_filter(
            $frauds,
            static fn (array $fraud): bool => strtolower((string) ($fraud['courier'] ?? 'steadfast')) === $courier,
        ));

        if ($matched === []) {
            return $report;
        }

        $existing = is_array($report['frauds'] ?? null) ? $report['frauds'] : [];
        $report['frauds'] = $existing !== [] ? $existing : $matched;

        return $report;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{0: int, 1: int, 2: int}
     */
    private function totalsFromPlatformCounts(array $counts): array
    {
        $delivered = (int) ($counts['delivered'] ?? 0) + (int) ($counts['partially_delivered'] ?? 0);
        $returned = (int) ($counts['returned'] ?? 0);
        $canceled = (int) ($counts['canceled'] ?? 0);
        $confirmed = $delivered;
        $cancel = $returned + $canceled;

        // A freshly created order (new_order) that has not finished delivery or return
        // is not completed courier history and must not fabricate a 0% delivery rate.
        if (($confirmed + $cancel) === 0) {
            return [0, 0, 0];
        }

        $total = $confirmed + $cancel;

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

        if (! empty($report['unavailable'])) {
            $report['status'] = 'unavailable';
            $report['cache_label'] = 'platform cache';
            $report['message'] = match (true) {
                ! empty($report['not_checked']) => $courier.' has not been checked for this number yet. It is checked automatically.',
                ($report['retryable'] ?? true) === false => $courier.' did not return delivery history for this number.',
                default => $courier.' did not answer last time. This number is checked again automatically.',
            };

            return $report;
        }

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