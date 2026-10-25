<?php

namespace App\Services\FraudCheck;

use Illuminate\Support\Arr;

class CourierReportFormatter
{
    public static function emptyReport(array $extra = []): array
    {
        return array_merge([
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
            'data_type' => 'delivery',
        ], $extra);
    }

    public static function fromCounts(int $confirmed, int $cancel, array $extra = []): array
    {
        $total = $confirmed + $cancel;

        return array_merge([
            'total_order' => $total,
            'confirmed' => $confirmed,
            'cancel' => $cancel,
            'success_rate' => $total === 0
                ? 'No order history found!'
                : ceil(($confirmed / $total) * 100) . '%',
            'data_type' => 'delivery',
        ], $extra);
    }

    public static function fromSteadfast(array $payload): array
    {
        if (self::usesSteadfastScoreShape($payload)) {
            $normalized = $payload;
            if (! array_key_exists('total_reports', $normalized) && array_key_exists('fraud_reports', $payload)) {
                $normalized['total_reports'] = $payload['fraud_reports'];
            }

            $report = self::fromSteadfastScore($normalized);

            if (isset($payload['frauds']) && is_array($payload['frauds']) && array_is_list($payload['frauds'])) {
                $report['frauds'] = $payload['frauds'];
            }

            return $report;
        }

        $confirmed = (int) ($payload['total_delivered'] ?? $payload['delivered'] ?? 0);
        $cancel = (int) ($payload['total_cancelled'] ?? $payload['cancelled'] ?? $payload['cancel'] ?? 0);
        $total = (int) ($payload['total_order'] ?? $payload['total'] ?? 0);

        if ($total === 0 && ($confirmed > 0 || $cancel > 0)) {
            $total = $confirmed + $cancel;
        }

        if ($cancel === 0 && $total > $confirmed) {
            $cancel = $total - $confirmed;
        }

        return self::fromCounts($confirmed, $cancel, [
            'frauds' => $payload['frauds'] ?? [],
        ]);
    }

    /**
     * The portal fraud-check JSON now matches the score API. Older sessions
     * still send total_delivered / total_cancelled.
     */
    private static function usesSteadfastScoreShape(array $payload): bool
    {
        foreach (['total_delivered', 'delivered', 'total_cancelled', 'cancelled', 'cancel', 'total_order', 'total'] as $field) {
            if (array_key_exists($field, $payload)) {
                return false;
            }
        }

        return array_key_exists('delivered_count', $payload)
            || array_key_exists('cancelled_count', $payload)
            || array_key_exists('delivery_ratio', $payload);
    }

    /**
     * Packzy GET /fraud_check/score/{phone}.
     *
     * Ratios are whole percents of finished parcels. Null means unknown, not zero.
     * Exact counts are present only while the merchant is booking regularly.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function fromSteadfastScore(array $payload): array
    {
        $delivered = $payload['delivered_count'] ?? null;
        $cancelled = $payload['cancelled_count'] ?? null;
        $deliveryRatio = $payload['delivery_ratio'] ?? null;
        $cancellationRatio = $payload['cancellation_ratio'] ?? null;
        $categories = is_array($payload['fraud_categories'] ?? null) ? $payload['fraud_categories'] : [];
        $frauds = [];

        foreach ($categories as $code => $times) {
            $frauds[] = [
                'type' => (string) $code,
                'count' => (int) $times,
            ];
        }

        $hasCounts = is_numeric($delivered) || is_numeric($cancelled);

        if ($hasCounts) {
            $report = self::fromCounts(
                is_numeric($delivered) ? (int) $delivered : 0,
                is_numeric($cancelled) ? (int) $cancelled : 0,
                ['frauds' => $frauds],
            );
        } else {
            $report = self::emptyReport([
                'frauds' => $frauds,
                'success_rate' => is_numeric($deliveryRatio)
                    ? ((int) $deliveryRatio).'%'
                    : 'No order history found!',
            ]);
        }

        if (is_numeric($deliveryRatio)) {
            $report['success_rate'] = ((int) $deliveryRatio).'%';
        }

        $report['delivery_ratio'] = is_numeric($deliveryRatio) ? (int) $deliveryRatio : null;
        $report['cancellation_ratio'] = is_numeric($cancellationRatio) ? (int) $cancellationRatio : null;
        $report['volume_band'] = $payload['volume_band'] ?? null;
        $report['volume_range'] = $payload['volume_range'] ?? null;
        $report['fraud_categories'] = $categories;
        $report['frauds_count'] = (int) ($payload['total_reports'] ?? 0);
        $report['doubtful_reports'] = (bool) ($payload['doubtful_reports'] ?? false);
        $report['counts_hidden'] = ! $hasCounts;

        if (! is_numeric($deliveryRatio) && ! is_numeric($cancellationRatio)) {
            $report['history_unknown'] = true;
            $report['message'] = 'Steadfast has no finished parcels for this number yet. That is not a clean record.';
        } elseif (! $hasCounts) {
            $report['message'] = 'Steadfast returned a delivery ratio. Exact parcel counts are hidden until this store is booking regularly.';
        }

        return $report;
    }

    public static function fromPathao(array $pathaoResponse): array
    {
        $data = self::extractPathaoData($pathaoResponse);

        if (is_array($customer = Arr::get($data, 'customer'))) {
            $report = self::fromPathaoCustomer($customer);

            if ($report !== null) {
                return $report;
            }
        }

        if (self::pathaoHasDeliveryCounts($data) && ($report = self::fromPathaoCustomer($data)) !== null) {
            return $report;
        }

        if (self::pathaoConfirmsNoHistory($data)) {
            return self::emptyReport([
                'api_success' => true,
                'message' => 'No delivery history found on Pathao.',
            ]);
        }

        if ($rating = Arr::get($data, 'customer_rating')) {
            return self::fromPathaoRating((string) $rating, $data);
        }

        return self::emptyReport();
    }

    public static function fromPaperfly(array $response): array
    {
        $records = collect(Arr::get($response, 'records', []));

        if ($records->isEmpty()) {
            return self::emptyReport();
        }

        $confirmed = (int) $records->sum(fn (array $record) => self::firstInt($record, [
            'delivered',
            'success',
            'successful',
            'confirmed',
        ]));

        $cancel = (int) $records->sum(fn (array $record) => self::firstInt($record, [
            'returned',
            'cancel',
            'cancelled',
            'failed',
        ]));

        if ($confirmed === 0 && $cancel === 0) {
            $confirmed = (int) $records->sum(fn (array $record) => self::firstInt($record, ['total_delivered']));
            $cancel = (int) $records->sum(fn (array $record) => self::firstInt($record, ['total_returned']));
        }

        if ($confirmed === 0 && $cancel === 0) {
            foreach ($records as $record) {
                $status = strtolower((string) ($record['status'] ?? ''));

                if (str_contains($status, 'deliver') || str_contains($status, 'success')) {
                    $confirmed++;
                } elseif (str_contains($status, 'return') || str_contains($status, 'cancel') || str_contains($status, 'fail')) {
                    $cancel++;
                }
            }
        }

        return self::fromCounts($confirmed, $cancel);
    }

    public static function aggregateTotals(array ...$reports): array
    {
        $totals = [
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
        ];

        foreach ($reports as $report) {
            if (!self::shouldIncludeInTotals($report)) {
                continue;
            }

            $totals['total_order'] += (int) ($report['total_order'] ?? 0);
            $totals['confirmed'] += (int) ($report['confirmed'] ?? 0);
            $totals['cancel'] += (int) ($report['cancel'] ?? 0);
        }

        return $totals;
    }

    public static function shouldIncludeInTotals(array $report): bool
    {
        $dataType = $report['data_type'] ?? 'delivery';

        // Rating-only and fraud-report-only payloads must never inflate delivery totals.
        if (in_array($dataType, ['rating', 'fraud_reports'], true) && (int) ($report['total_order'] ?? 0) === 0) {
            return false;
        }

        return self::hasDeliveryCounts($report);
    }

    public static function hasDeliveryCounts(array $report): bool
    {
        return (int) ($report['total_order'] ?? 0) > 0
            || (int) ($report['confirmed'] ?? 0) > 0
            || (int) ($report['cancel'] ?? 0) > 0;
    }

    public static function isRatingOnly(array $report): bool
    {
        if (self::hasDeliveryCounts($report)) {
            return false;
        }

        return ($report['data_type'] ?? '') === 'rating'
            || filled($report['customer_rating'] ?? null)
            || ($report['status'] ?? '') === 'rating_only';
    }

    public static function formatRating(string $rating): string
    {
        return match ($rating) {
            'excellent_customer' => 'Excellent Customer',
            'good_customer' => 'Good Customer',
            'average_customer', 'moderate_customer' => 'Average Customer',
            'poor_customer' => 'Poor Customer',
            'risky_customer' => 'Risky Customer',
            'new_customer' => 'New Customer',
            default => ucwords(str_replace('_', ' ', $rating)),
        };
    }

    public static function estimatedSuccessPercent(?string $rating): ?int
    {
        return match ($rating) {
            'excellent_customer' => 95,
            'good_customer' => 85,
            'average_customer', 'moderate_customer' => 70,
            'poor_customer' => 50,
            'risky_customer' => 30,
            'new_customer' => null,
            default => null,
        };
    }

    private static function extractPathaoData(array $response): array
    {
        $data = Arr::get($response, 'data', $response);

        if (!is_array($data)) {
            return [];
        }

        if (
            isset($data['data'])
            && is_array($data['data'])
            && !isset($data['customer'])
            && !isset($data['customer_rating'])
            && !self::pathaoHasDeliveryCounts($data)
        ) {
            $data = $data['data'];
        }

        return $data;
    }

    private static function fromPathaoCustomer(array $customer): ?array
    {
        if (!self::pathaoHasDeliveryCounts($customer)) {
            return null;
        }

        $confirmed = self::firstInt($customer, [
            'successful_delivery',
            'success_delivery',
            'success',
            'delivered',
            'confirmed',
        ]);

        $cancel = self::firstInt($customer, [
            'failed_delivery',
            'cancelled_delivery',
            'returned_delivery',
            'cancel_delivery',
            'cancelled',
            'cancel',
            'return',
            'returned',
        ]);

        $total = self::firstInt($customer, [
            'total_delivery',
            'total_order',
            'total',
        ]);

        if ($total === 0 && ($confirmed > 0 || $cancel > 0)) {
            $total = $confirmed + $cancel;
        }

        if ($cancel === 0 && $total > $confirmed) {
            $cancel = $total - $confirmed;
        }

        if ($total === 0 && $confirmed === 0 && $cancel === 0) {
            return null;
        }

        return self::fromCounts($confirmed, $cancel, array_filter([
            'customer_rating' => Arr::get($customer, 'customer_rating'),
            'show_count' => Arr::get($customer, 'show_count'),
        ], fn ($value) => $value !== null));
    }

    private static function fromPathaoRating(string $rating, array $data): array
    {
        $estimated = self::estimatedSuccessPercent($rating);

        return array_merge(self::emptyReport([
            'data_type' => 'rating',
            'success_rate' => self::formatRating($rating),
            'customer_rating' => $rating,
            'show_count' => Arr::get($data, 'show_count', false),
            'estimated_success_rate' => $estimated === null ? null : $estimated . '%',
        ]));
    }

    /**
     * Visible counts that are all zero mean Pathao has never delivered to
     * this number. A rating without visible counts (even new_customer) is
     * not proof of an empty history.
     */
    private static function pathaoConfirmsNoHistory(array $data): bool
    {
        return Arr::get($data, 'show_count') === true && is_array(Arr::get($data, 'customer'));
    }

    private static function pathaoHasDeliveryCounts(array $data): bool
    {
        if (Arr::get($data, 'show_count') === false) {
            return false;
        }

        foreach ([
            'total_delivery',
            'successful_delivery',
            'failed_delivery',
            'cancelled_delivery',
            'returned_delivery',
            'total',
            'success',
            'cancel',
        ] as $field) {
            if ((int) Arr::get($data, $field, 0) > 0) {
                return true;
            }
        }

        return Arr::get($data, 'show_count') === true;
    }

    private static function firstInt(array $data, array $keys): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return (int) $data[$key];
            }
        }

        return 0;
    }
}
