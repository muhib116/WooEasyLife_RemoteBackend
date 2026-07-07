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

        $totals = CourierReportFormatter::aggregateTotals($steadfast, $pathao, $paperfly);
        $totalOrder = (int) ceil($totals['total_order']);
        $confirmOrder = (int) ceil($totals['confirmed']);
        $cancelOrder = (int) ceil($totals['cancel']);

        if ($totalOrder === 0) {
            [$totalOrder, $confirmOrder, $cancelOrder] = $this->totalsFromPlatformCounts(
                $platformData['platform_intelligence']['counts'] ?? [],
            );
        }

        $frauds = $this->formatFrauds($platformData['courier_fraud_notes'] ?? []);
        $successRate = $this->resolveSuccessRate(
            $totalOrder,
            $confirmOrder,
            $platformData['platform_intelligence']['rates'] ?? [],
            $steadfast,
            $pathao,
            $paperfly,
        );

        return [
            'total_order' => $totalOrder,
            'confirmed' => $confirmOrder,
            'frauds' => $frauds,
            'cancel' => $cancelOrder,
            'success_rate' => $successRate,
            'courier' => [
                ['title' => 'Stead Fast', 'report' => $this->withPlatformCourierMeta($steadfast, 'Steadfast')],
                ['title' => 'Pathao', 'report' => $this->withPlatformCourierMeta($pathao, 'Pathao')],
                ['title' => 'Paper Fly', 'report' => $this->withPlatformCourierMeta($paperfly, 'Paperfly')],
            ],
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

        return CourierReportFormatter::fromCounts(
            (int) ($snapshot['confirmed'] ?? 0),
            (int) ($snapshot['cancel'] ?? 0),
            array_filter([
                'total_order' => (int) ($snapshot['total_order'] ?? 0),
                'success_rate' => $snapshot['success_rate'] ?? null,
                'customer_rating' => $snapshot['customer_rating'] ?? null,
                'source' => 'platform_cache',
                'fetched_at' => $snapshot['fetched_at'] ?? null,
            ], fn ($value) => $value !== null),
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
            ],
            $notes,
        );
    }

    /**
     * @param  array<string, string>  $rates
     * @param  array<string, mixed>  $steadfast
     * @param  array<string, mixed>  $pathao
     * @param  array<string, mixed>  $paperfly
     */
    private function resolveSuccessRate(
        int $totalOrder,
        int $confirmOrder,
        array $rates,
        array $steadfast,
        array $pathao,
        array $paperfly,
    ): string {
        if ($totalOrder > 0) {
            return ceil(($confirmOrder / $totalOrder) * 100) . '%';
        }

        if (! empty($rates['delivery_rate'])) {
            return (string) $rates['delivery_rate'];
        }

        foreach ([$steadfast, $pathao, $paperfly] as $report) {
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
        if (($report['total_order'] ?? 0) > 0 || ! empty($report['frauds'])) {
            $report['status'] = 'ok';

            return $report;
        }

        if (($report['data_type'] ?? 'delivery') === 'rating' || ! empty($report['customer_rating'])) {
            $report['status'] = 'rating_only';

            return $report;
        }

        $rate = $report['success_rate'] ?? '';

        if ($rate !== '' && $rate !== 'No order history found!') {
            $report['status'] = 'ok';

            return $report;
        }

        $report['status'] = 'ok';
        $report['message'] = match ($courier) {
            'Steadfast' => 'No delivery history found on Steadfast (platform cache).',
            'Paperfly' => 'No delivery records found on Paperfly (platform cache).',
            default => 'No delivery history found (platform cache).',
        };

        return $report;
    }
}
