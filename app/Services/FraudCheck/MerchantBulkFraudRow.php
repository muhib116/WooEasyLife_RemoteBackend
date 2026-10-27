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
     * SteadFast / Pathao / Paperfly rows that are present must each have answered.
     * Otherwise merchants see Found from one courier while Pathao still says
     * "Data unavailable" until a manual refresh.
     *
     * @param  array<string, mixed>  $report
     */
    public static function coreCouriersAnswered(array $report): bool
    {
        return self::unansweredCoreCouriers($report) === [];
    }

    /**
     * SteadFast / Pathao / Paperfly entries that did not answer, keyed by courier.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array<string, mixed>>
     */
    public static function unansweredCoreCouriers(array $report): array
    {
        $missing = [];

        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = self::courierKeyFromTitle((string) ($entry['title'] ?? ''));
            if (! in_array($key, ['steadfast', 'pathao', 'paperfly'], true)) {
                continue;
            }

            $courier = is_array($entry['report'] ?? null) ? $entry['report'] : [];
            $status = (string) ($courier['status'] ?? '');

            if (
                in_array($status, ['ok', 'rating_only'], true)
                || ! empty($courier['api_success'])
                || (int) ($courier['total_order'] ?? 0) > 0
                || self::partialCount($courier) > 0
            ) {
                continue;
            }

            $missing[$key] = $courier;
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public static function fromReport(string $phone, array $report): array
    {
        $couriers = self::couriers($report);
        $aggregatable = self::aggregatableCouriers($couriers);
        $received = self::countOf(array_sum(array_column($aggregatable, 'received')));
        $cancelled = self::countOf(array_sum(array_column($aggregatable, 'cancelled')));
        $partial = self::countOf(array_sum(array_column($aggregatable, 'partial')));
        $reportedTotal = self::countOf($report['total_order'] ?? 0);
        $hasOrderCounts = ($received + $cancelled + $partial) > 0 || $reportedTotal > 0;

        $rate = $hasOrderCounts
            ? self::ratePercent($report['success_rate'] ?? null, $received, $cancelled)
            : self::rateFromZeroCountReport($report, $couriers);

        $hasHistory = $hasOrderCounts || $rate !== null;

        if (! $hasHistory) {
            return self::row($phone, 'not_found', 0, 0, 0, 0, null, null, $couriers);
        }

        $total = max($reportedTotal, $received + $cancelled) + $partial;
        $risk = self::risk($rate);
        if (self::missingCoreCourier($couriers)) {
            $risk = 'incomplete';
        }

        return self::row(
            $phone,
            'found',
            $total,
            $received,
            $cancelled,
            $partial,
            $rate,
            $risk,
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
        $topLevelFraudsByCourier = self::topLevelFraudsByCourier($report);

        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $title = (string) ($entry['title'] ?? 'Courier');
            $courierKey = self::courierKeyFromTitle($title);
            $courierReport = is_array($entry['report'] ?? null) ? $entry['report'] : [];
            $received = self::countOf($courierReport['confirmed'] ?? 0);
            $cancelled = self::countOf($courierReport['cancel'] ?? 0);
            $partial = self::partialCount($courierReport);
            $status = (string) ($courierReport['status'] ?? '');
            $available = in_array($status, ['ok', 'rating_only'], true)
                || ! empty($courierReport['api_success'])
                || ($received + $cancelled + $partial) > 0;

            $extraFrauds = $courierKey !== null ? ($topLevelFraudsByCourier[$courierKey] ?? []) : [];

            $row = [
                'title' => $title,
                'received' => $received,
                'cancelled' => $cancelled,
                'partial' => $partial,
                'success_rate' => self::courierSuccessRate($courierReport, $received, $cancelled),
                'available' => $available,
                'notes' => self::notes($courierReport, $extraFrauds),
            ];

            if (! $available) {
                $row['reason'] = self::unavailableReason($courierReport);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Why a courier has no answer on the row: sample, busy (not asked yet
     * or asked to slow down), paused (blocking after errors), credentials,
     * not_set_up, or no_answer.
     *
     * @param  array<string, mixed>  $report
     */
    public static function unavailableReason(array $report): string
    {
        return match (true) {
            ! empty($report['static_sample']) || ($report['message'] ?? null) === RedxStaticSample::MESSAGE => 'sample',
            ! empty($report['deferred']) || ! empty($report['rate_limited']) => 'busy',
            ! empty($report['retry_later']) => 'paused',
            ! empty($report['credential_error']) => 'credentials',
            ($report['retryable'] ?? true) === false => 'not_set_up',
            default => 'no_answer',
        };
    }

    /**
     * Core couriers that did not answer but can be asked again later:
     * not answered for lack of room, rate limited, paused, or failed.
     * Missing setup and rejected credentials are not asked again.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array<string, mixed>>
     */
    public static function couriersToAskLater(array $report): array
    {
        return array_filter(
            self::unansweredCoreCouriers($report),
            static fn (array $courier): bool => ! in_array(self::unavailableReason($courier), ['sample', 'credentials', 'not_set_up'], true),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $couriers
     * @return list<array<string, mixed>>
     */
    private static function aggregatableCouriers(array $couriers): array
    {
        $aggregateRedx = (bool) config('fraud_check.aggregate_redx', true);
        $aggregateCarrybee = (bool) config('fraud_check.aggregate_carrybee', true);

        return array_values(array_filter($couriers, static function (array $courier) use ($aggregateRedx, $aggregateCarrybee): bool {
            $key = self::courierKeyFromTitle((string) ($courier['title'] ?? ''));

            if ($key === 'redx' && ! $aggregateRedx) {
                return false;
            }

            if ($key === 'carrybee' && ! $aggregateCarrybee) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $courierReport
     */
    private static function courierSuccessRate(array $courierReport, int $received, int $cancelled): ?int
    {
        if (($received + $cancelled) > 0) {
            return self::ratePercent($courierReport['success_rate'] ?? null, $received, $cancelled);
        }

        // Steadfast Packzy score API can return a valid delivery_ratio when exact parcel counts are hidden.
        if (! empty($courierReport['counts_hidden']) && empty($courierReport['history_unknown']) && is_numeric($courierReport['delivery_ratio'] ?? null)) {
            return self::percentOf((int) round((float) $courierReport['delivery_ratio']));
        }

        // Pathao rating-only payload provides a customer_rating / estimated_success_rate when counts are hidden.
        if (($courierReport['data_type'] ?? '') === 'rating' || ! empty($courierReport['customer_rating'])) {
            $estimated = $courierReport['estimated_success_rate']
                ?? CourierReportFormatter::estimatedSuccessPercent((string) ($courierReport['customer_rating'] ?? ''))
                ?? CourierReportFormatter::estimatedSuccessPercent(strtolower(str_replace(' ', '_', (string) ($courierReport['success_rate'] ?? ''))));

            if ($estimated !== null) {
                return self::ratePercent($estimated, 0, 0);
            }
        }

        return null;
    }

    /**
     * When all couriers have 0 order counts, only accept a rate if at least one courier
     * reported a rating or hidden-count ratio (never a 0% platform placeholder on 0 orders).
     *
     * @param  array<string, mixed>  $report
     * @param  list<array<string, mixed>>  $couriers
     */
    private static function rateFromZeroCountReport(array $report, array $couriers): ?int
    {
        foreach ($couriers as $courier) {
            if (isset($courier['success_rate']) && is_numeric($courier['success_rate'])) {
                return self::percentOf((int) $courier['success_rate']);
            }
        }

        $rawRate = $report['success_rate'] ?? null;
        if (is_string($rawRate) && trim($rawRate) !== '') {
            $estimated = CourierReportFormatter::estimatedSuccessPercent(
                strtolower(str_replace(' ', '_', trim($rawRate)))
            );

            if ($estimated !== null) {
                return self::percentOf($estimated);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, list<array<string, mixed>>>
     */
    private static function topLevelFraudsByCourier(array $report): array
    {
        $byCourier = [
            'steadfast' => [],
            'pathao' => [],
            'paperfly' => [],
            'redx' => [],
            'carrybee' => [],
        ];

        $sources = array_merge(
            is_array($report['frauds'] ?? null) ? $report['frauds'] : [],
            is_array($report['courier_fraud_notes'] ?? null) ? $report['courier_fraud_notes'] : [],
        );

        foreach ($sources as $fraud) {
            if (! is_array($fraud)) {
                continue;
            }

            $key = self::courierKeyFromTitle((string) ($fraud['courier'] ?? $fraud['name'] ?? 'steadfast')) ?? 'steadfast';
            $byCourier[$key][] = $fraud;
        }

        return $byCourier;
    }

    public static function courierKeyFromTitle(string $title): ?string
    {
        $lower = strtolower(trim($title));

        return match (true) {
            str_contains($lower, 'stead') => 'steadfast',
            str_contains($lower, 'pathao') => 'pathao',
            str_contains($lower, 'paper') => 'paperfly',
            str_contains($lower, 'redx') || str_contains($lower, 'red x') => 'redx',
            str_contains($lower, 'carry') => 'carrybee',
            default => null,
        };
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
     * System/status messages on $courierReport['message'] are never treated as customer fraud notes.
     *
     * @param  array<string, mixed>  $courierReport
     * @param  list<array<string, mixed>>  $extraFrauds
     * @return list<string>
     */
    private static function notes(array $courierReport, array $extraFrauds = []): array
    {
        $notes = [];
        $allFrauds = array_merge(
            is_array($courierReport['frauds'] ?? null) ? $courierReport['frauds'] : [],
            $extraFrauds,
        );

        foreach ($allFrauds as $fraud) {
            if (is_string($fraud)) {
                $text = trim($fraud);
                if ($text !== '' && ! self::isInternalCourierMessage($text)) {
                    $notes[] = mb_substr($text, 0, 240);
                }

                continue;
            }

            if (! is_array($fraud)) {
                continue;
            }

            $details = trim((string) ($fraud['details'] ?? $fraud['note'] ?? ''));
            if ($details !== '') {
                if (! self::isInternalCourierMessage($details)) {
                    $notes[] = mb_substr($details, 0, 240);
                }

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

        if ($notes === [] && ($courierReport['data_type'] ?? '') === 'fraud_reports') {
            $fraudsCount = (int) ($courierReport['frauds_count'] ?? 0);
            if ($fraudsCount > 0) {
                $notes[] = "{$fraudsCount} fraud report(s) on Carrybee";
            }
        }

        return self::cleanNotes($notes);
    }

    /**
     * Filter out transport, login, cache, and empty-history status messages from note arrays.
     *
     * @param  array<int, mixed>  $notes
     * @return list<string>
     */
    public static function cleanNotes(array $notes): array
    {
        $cleaned = [];

        foreach ($notes as $note) {
            if (! is_string($note)) {
                continue;
            }

            $text = trim($note);
            if ($text === '' || str_contains($text, '<') || mb_strlen($text) > 240 || self::isInternalCourierMessage($text)) {
                continue;
            }

            $cleaned[] = $text;
        }

        return array_values(array_unique($cleaned));
    }

    /**
     * Sanitize a stored bulk/daily row so previously cached rows with system messages
     * or 0-order 0%-rate placeholders are normalized consistently on read.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function sanitizeStoredRow(array $row): array
    {
        $couriers = is_array($row['couriers'] ?? null) ? $row['couriers'] : [];
        $hasCourierRate = false;

        foreach ($couriers as $index => $courier) {
            if (! is_array($courier)) {
                continue;
            }

            $couriers[$index]['notes'] = self::cleanNotes(is_array($courier['notes'] ?? null) ? $courier['notes'] : []);

            if (isset($courier['success_rate']) && is_numeric($courier['success_rate'])) {
                $hasCourierRate = true;
            }
        }

        $row['couriers'] = $couriers;

        $totalOrders = (int) ($row['total_orders'] ?? 0);
        $received = (int) ($row['received'] ?? 0);
        $cancelled = (int) ($row['cancelled'] ?? 0);
        $partial = (int) ($row['partial'] ?? 0);
        $hasOrders = ($totalOrders + $received + $cancelled + $partial) > 0;

        if (($row['status'] ?? '') === 'found' && ! $hasOrders && ! $hasCourierRate && (int) ($row['success_rate'] ?? 0) === 0) {
            $row['status'] = 'not_found';
            $row['success_rate'] = null;
            $row['risk'] = null;
        }

        if (
            self::missingCoreCourier($couriers)
            && in_array($row['status'] ?? '', ['found', 'not_found'], true)
            && ($row['risk'] ?? null) !== 'waiting'
        ) {
            $row['risk'] = 'incomplete';
        }

        return $row;
    }

    /**
     * Every SteadFast, Pathao, and Paperfly line of a stored row answered.
     *
     * @param  array<int, mixed>  $couriers
     */
    public static function hasEveryCoreCourier(array $couriers): bool
    {
        return ! self::missingCoreCourier($couriers);
    }

    /**
     * SteadFast, Pathao, or Paperfly failed to answer. The rate from the other
     * couriers must not be shown as Safe.
     *
     * @param  list<array<string, mixed>>  $couriers
     */
    private static function missingCoreCourier(array $couriers): bool
    {
        foreach ($couriers as $courier) {
            if (! is_array($courier) || ($courier['available'] ?? null) !== false) {
                continue;
            }

            if (in_array(self::courierKeyFromTitle((string) ($courier['title'] ?? '')), ['steadfast', 'pathao', 'paperfly'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Courier transport, login, cache, and empty-history messages are not customer fraud.
     */
    public static function isInternalCourierMessage(string $message): bool
    {
        $lower = strtolower(trim($message));

        foreach ([
            'login failed',
            're-login',
            'token expired',
            'session expired',
            'credential',
            'curl error',
            'connection reset',
            'sqlstate',
            'stack trace',
            'smart-check failed',
            'stats request failed',
            'customer lookup failed',
            'http 4',
            'http 5',
            'vendor/',
            '.php',
            'no delivery history',
            'no delivery records',
            'no order history',
            'no fraud reports',
            'did not answer',
            'did not return',
            'not been checked',
            'not set up for fraud',
            'asked us to slow down',
            'is busy, so this number',
            'not asked yet',
            'ask again in about',
            'limit reached for this store',
            'temporarily unreachable',
            'temporarily unavailable',
            'showing last known good data',
            'exact parcel counts are hidden',
            'no finished parcels for this number',
            'platform cache',
            'no delivery detail rows',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
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
