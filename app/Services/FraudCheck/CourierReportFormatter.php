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

    public static function fromPathao(array $pathaoResponse): array
    {
        $data = self::extractPathaoData($pathaoResponse);

        if (Arr::get($data, 'is_new', false)) {
            return self::emptyReport();
        }

        if (is_array($customer = Arr::get($data, 'customer'))) {
            $report = self::fromPathaoCustomer($customer);

            if ($report !== null) {
                return $report;
            }
        }

        if (self::pathaoHasDeliveryCounts($data)) {
            return self::fromPathaoCustomer($data);
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
        if (($report['data_type'] ?? 'delivery') === 'rating' && (int) ($report['total_order'] ?? 0) === 0) {
            return false;
        }

        return (int) ($report['total_order'] ?? 0) > 0
            || (int) ($report['confirmed'] ?? 0) > 0
            || (int) ($report['cancel'] ?? 0) > 0;
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
