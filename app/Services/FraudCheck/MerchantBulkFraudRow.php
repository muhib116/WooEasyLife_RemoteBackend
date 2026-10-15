<?php

namespace App\Services\FraudCheck;

/**
 * Compact bulk-check row for the merchant portal.
 * Courier counts and note text are kept. Customer names are not.
 */
class MerchantBulkFraudRow
{
    public const SAFE_MIN = 80;

    public const REVIEW_MIN = 50;

    /**
     * A report is reusable when at least one courier actually answered.
     * A total failure is retried and is not stored for the rest of the day.
     *
     * @param  array<string, mixed>  $report
     */
    public static function isUsable(array $report): bool
    {
        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $courier = is_array($entry['report'] ?? null) ? $entry['report'] : [];
            $status = (string) ($courier['status'] ?? '');

            if (in_array($status, ['ok', 'rating_only'], true) || ! empty($courier['api_success'])) {
                return true;
            }

            if ((int) ($courier['total_order'] ?? 0) > 0 || self::partialCount($courier) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public static function fromReport(string $phone, array $report): array
    {
        $couriers = self::couriers($report);
        $received = self::countOf(array_sum(array_column($couriers, 'received')));
        $cancelled = self::countOf(array_sum(array_column($couriers, 'cancelled')));
        $partial = self::countOf(array_sum(array_column($couriers, 'partial')));
        $rate = self::ratePercent($report['success_rate'] ?? null, $received, $cancelled);
        $hasHistory = ($received + $cancelled + $partial) > 0 || $rate !== null;

        if (! $hasHistory) {
            return self::row($phone, 'not_found', 0, 0, 0, 0, null, null, $couriers);
        }

        $total = $received + $cancelled + $partial;

        return self::row(
            $phone,
            'found',
            $total,
            $received,
            $cancelled,
            $partial,
            $rate,
            self::risk($rate),
            $couriers,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function invalid(string $phone): array
    {
        return self::row($phone, 'invalid', 0, 0, 0, 0, null, null, []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function failed(string $phone): array
    {
        return self::row($phone, 'error', 0, 0, 0, 0, null, null, []);
    }

    public static function risk(?int $rate): ?string
    {
        if ($rate === null) {
            return null;
        }

        if ($rate >= self::SAFE_MIN) {
            return 'safe';
        }

        if ($rate >= self::REVIEW_MIN) {
            return 'review';
        }

        return 'high';
    }

    /**
     * @param  list<array<string, mixed>>  $couriers
     * @return array<string, mixed>
     */
    private static function row(
        string $phone,
        string $status,
        int $total,
        int $received,
        int $cancelled,
        int $partial,
        ?int $rate,
        ?string $risk,
        array $couriers,
    ): array {
        return [
            'phone' => $phone,
            'status' => $status,
            'total_orders' => $total,
            'received' => $received,
            'cancelled' => $cancelled,
            'partial' => $partial,
            'success_rate' => $rate,
            'risk' => $risk,
            'couriers' => $couriers,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array<string, mixed>>
     */
    private static function couriers(array $report): array
    {
        $rows = [];

        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $courierReport = is_array($entry['report'] ?? null) ? $entry['report'] : [];
            $received = self::countOf($courierReport['confirmed'] ?? 0);
            $cancelled = self::countOf($courierReport['cancel'] ?? 0);
            $partial = self::partialCount($courierReport);
            $status = (string) ($courierReport['status'] ?? '');
            $available = in_array($status, ['ok', 'rating_only'], true)
                || ! empty($courierReport['api_success'])
                || ($received + $cancelled + $partial) > 0;

            $rows[] = [
                'title' => (string) ($entry['title'] ?? 'Courier'),
                'received' => $received,
                'cancelled' => $cancelled,
                'partial' => $partial,
                'success_rate' => ($received + $cancelled) > 0
                    ? self::ratePercent($courierReport['success_rate'] ?? null, $received, $cancelled)
                    : null,
                'available' => $available,
                'notes' => self::notes($courierReport),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $courierReport
     */
    private static function partialCount(array $courierReport): int
    {
        foreach (['partial', 'partial_delivered', 'partially_delivered', 'total_partial'] as $key) {
            if (array_key_exists($key, $courierReport) && is_numeric($courierReport[$key])) {
                return self::countOf($courierReport[$key]);
            }
        }

        return 0;
    }

    /**
     * Note text only. The customer name on a fraud report is left out.
     *
     * @param  array<string, mixed>  $courierReport
     * @return list<string>
     */
    private static function notes(array $courierReport): array
    {
        $notes = [];

        foreach ($courierReport['frauds'] ?? [] as $fraud) {
            if (is_string($fraud)) {
                $text = trim($fraud);
                if ($text !== '') {
                    $notes[] = mb_substr($text, 0, 240);
                }

                continue;
            }

            if (! is_array($fraud)) {
                continue;
            }

            $details = trim((string) ($fraud['details'] ?? $fraud['note'] ?? ''));
            if ($details !== '') {
                $notes[] = mb_substr($details, 0, 240);

                continue;
            }

            $type = trim((string) ($fraud['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $label = ucfirst(str_replace('_', ' ', $type));
            $count = (int) ($fraud['count'] ?? 0);
            $notes[] = $count > 0 ? $label.' × '.$count : $label;
        }

        $message = trim((string) ($courierReport['message'] ?? ''));
        if ($message !== '' && ! str_contains($message, '<') && mb_strlen($message) <= 240) {
            $notes[] = $message;
        }

        return array_values(array_unique($notes));
    }

    private static function ratePercent(mixed $raw, int $received, int $cancelled): ?int
    {
        if (is_string($raw) && str_contains(strtolower($raw), 'no order history')) {
            $raw = null;
        }

        if (is_numeric($raw)) {
            return self::percentOf((int) round((float) $raw));
        }

        if (is_string($raw) && preg_match('/(\d+(?:\.\d+)?)\s*%/', $raw, $match)) {
            return self::percentOf((int) round((float) $match[1]));
        }

        $finished = $received + $cancelled;

        if ($finished <= 0) {
            return null;
        }

        return self::percentOf((int) ceil(($received / $finished) * 100));
    }

    private static function countOf(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $number = (float) $value;

        if ($number <= 0) {
            return 0;
        }

        if ($number >= 999999999) {
            return 999999999;
        }

        return (int) $number;
    }

    private static function percentOf(int $rate): int
    {
        return max(0, min(100, $rate));
    }
}
