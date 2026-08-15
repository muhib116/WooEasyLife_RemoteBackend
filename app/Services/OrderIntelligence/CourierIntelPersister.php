<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\CourierFraudReport;
use App\Models\OrderIntelligence\PlatformCustomer;
use Carbon\Carbon;

class CourierIntelPersister
{
    /**
     * @param  array<string, mixed>  $steadfastReport
     * @param  array<string, mixed>  $pathaoReport
     * @param  array<string, mixed>  $paperflyReport
     * @param  array<string, mixed>  $redxReport
     * @param  array<string, mixed>  $carrybeeReport
     */
    public function persistFromFraudCheckReports(
        PlatformCustomer $customer,
        string $phoneNormalized,
        array $steadfastReport,
        array $pathaoReport,
        array $paperflyReport,
        array $redxReport = [],
        array $carrybeeReport = [],
        ?int $sourceAccessTokenId = null,
    ): void {
        $now = now();

        $this->upsertSnapshot($customer, $phoneNormalized, 'steadfast', $steadfastReport, $now, $sourceAccessTokenId);
        $this->upsertSnapshot($customer, $phoneNormalized, 'pathao', $pathaoReport, $now, $sourceAccessTokenId);
        $this->upsertSnapshot($customer, $phoneNormalized, 'paperfly', $paperflyReport, $now, $sourceAccessTokenId);

        if ($redxReport !== []) {
            $this->upsertSnapshot($customer, $phoneNormalized, 'redx', $redxReport, $now, $sourceAccessTokenId);
        }

        if ($carrybeeReport !== []) {
            $this->upsertSnapshot($customer, $phoneNormalized, 'carrybee', $carrybeeReport, $now, $sourceAccessTokenId);

            $carrybeeFraudsCount = (int) ($carrybeeReport['frauds_count'] ?? 0);
            if ($carrybeeFraudsCount > 0) {
                $this->upsertFraudReport($customer, $phoneNormalized, [
                    'name' => 'Carrybee',
                    'details' => "{$carrybeeFraudsCount} fraud report(s) recorded on Carrybee",
                    'courier' => 'carrybee',
                ], $sourceAccessTokenId, $now);
            }
        }

        $frauds = is_array($steadfastReport['frauds'] ?? null) ? $steadfastReport['frauds'] : [];

        foreach ($frauds as $fraud) {
            if (! is_array($fraud)) {
                continue;
            }

            $this->upsertFraudReport($customer, $phoneNormalized, [
                ...$fraud,
                'courier' => $fraud['courier'] ?? 'steadfast',
            ], $sourceAccessTokenId, $now);
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function upsertSnapshot(
        PlatformCustomer $customer,
        string $phoneNormalized,
        string $courier,
        array $report,
        Carbon $fetchedAt,
        ?int $sourceAccessTokenId,
    ): void {
        if ($report === []) {
            return;
        }

        $existing = CourierCustomerSnapshot::query()
            ->where('platform_customer_id', $customer->id)
            ->where('courier', $courier)
            ->first();

        // Never treat failed/blocked fetches as fresh successful cache.
        if ($this->isFailedFetch($report)) {
            $staleHours = max(1, (int) config('order_intelligence.fraud_check.max_snapshot_staleness_hours', 10)) + 1;
            $preserve = (bool) config('order_intelligence.fraud_check.preserve_snapshot_on_failure', true);

            if ($preserve && $existing && $this->snapshotHasUsefulData($existing)) {
                $raw = is_array($existing->raw_report) ? $existing->raw_report : [];

                // Keep last-good counts, but mark failed + stale so partial refresh retries.
                $existing->fill([
                    'phone_normalized' => $phoneNormalized,
                    'raw_report' => array_merge($raw, $report, [
                        'fetch_failed' => true,
                        'preserved_on_failure' => true,
                    ]),
                    'fetched_at' => now()->subHours($staleHours),
                    'source_access_token_id' => $sourceAccessTokenId,
                ])->save();

                return;
            }

            CourierCustomerSnapshot::query()->updateOrCreate(
                [
                    'platform_customer_id' => $customer->id,
                    'courier' => $courier,
                ],
                [
                    'phone_normalized' => $phoneNormalized,
                    'total_order' => 0,
                    'confirmed' => 0,
                    'cancel' => 0,
                    'success_rate' => isset($report['success_rate']) ? (string) $report['success_rate'] : 'No order history found!',
                    'customer_rating' => null,
                    'frauds_count' => 0,
                    'raw_report' => array_merge($report, ['fetch_failed' => true]),
                    'fetched_at' => now()->subHours($staleHours),
                    'source_access_token_id' => $sourceAccessTokenId,
                ],
            );

            return;
        }

        if ($this->shouldPreserveExistingSnapshot($existing, $report)) {
            return;
        }

        $fraudsCount = array_key_exists('frauds_count', $report)
            ? (int) $report['frauds_count']
            : count(is_array($report['frauds'] ?? null) ? $report['frauds'] : []);

        CourierCustomerSnapshot::query()->updateOrCreate(
            [
                'platform_customer_id' => $customer->id,
                'courier' => $courier,
            ],
            [
                'phone_normalized' => $phoneNormalized,
                'total_order' => (int) ($report['total_order'] ?? 0),
                'confirmed' => (int) ($report['confirmed'] ?? 0),
                'cancel' => (int) ($report['cancel'] ?? 0),
                'success_rate' => isset($report['success_rate']) ? (string) $report['success_rate'] : null,
                'customer_rating' => isset($report['customer_rating']) ? (string) $report['customer_rating'] : null,
                'frauds_count' => $fraudsCount,
                'raw_report' => $report,
                'fetched_at' => $fetchedAt,
                'source_access_token_id' => $sourceAccessTokenId,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function isFailedFetch(array $report): bool
    {
        if (! empty($report['unavailable']) || ! empty($report['credential_error']) || ! empty($report['fetch_failed'])) {
            return true;
        }

        $message = strtolower((string) ($report['message'] ?? ''));

        foreach ([
            'session expired',
            'credentials are invalid',
            'credential',
            'login failed',
            're-login failed',
            'token expired',
            'temporarily unavailable',
        ] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep last known good courier data when a partner is blocked/unavailable.
     * Authentic empty responses (api_success) are allowed to overwrite.
     *
     * @param  array<string, mixed>  $report
     */
    private function shouldPreserveExistingSnapshot(?CourierCustomerSnapshot $existing, array $report): bool
    {
        if (! config('order_intelligence.fraud_check.preserve_snapshot_on_failure', true)) {
            return false;
        }

        if ($existing === null || ! $this->snapshotHasUsefulData($existing)) {
            return false;
        }

        if ($this->isFailedFetch($report)) {
            return true;
        }

        if ($this->reportHasUsefulData($report)) {
            return false;
        }

        // Successful empty lookup may clear history; failed/ambiguous empty must not.
        if (! empty($report['api_success'])) {
            return false;
        }

        return true;
    }

    private function snapshotHasUsefulData(CourierCustomerSnapshot $snapshot): bool
    {
        return (int) $snapshot->total_order > 0
            || (int) $snapshot->confirmed > 0
            || (int) $snapshot->cancel > 0
            || (int) $snapshot->frauds_count > 0
            || filled($snapshot->customer_rating);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function reportHasUsefulData(array $report): bool
    {
        return (int) ($report['total_order'] ?? 0) > 0
            || (int) ($report['confirmed'] ?? 0) > 0
            || (int) ($report['cancel'] ?? 0) > 0
            || (int) ($report['frauds_count'] ?? 0) > 0
            || ! empty($report['frauds'])
            || filled($report['customer_rating'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $fraud
     */
    private function upsertFraudReport(
        PlatformCustomer $customer,
        string $phoneNormalized,
        array $fraud,
        ?int $sourceAccessTokenId,
        Carbon $now,
    ): void {
        $details = trim((string) ($fraud['details'] ?? ''));

        if ($details === '') {
            return;
        }

        $courier = (string) ($fraud['courier'] ?? 'steadfast');
        $consignmentId = trim((string) ($fraud['consignment_id'] ?? ''));
        $reportedAt = $fraud['created_at'] ?? null;
        $fingerprint = hash('sha256', implode('|', [
            $courier,
            $phoneNormalized,
            $details,
            $consignmentId,
            (string) $reportedAt,
        ]));

        $existing = CourierFraudReport::query()->where('fingerprint', $fingerprint)->first();

        if ($existing) {
            $existing->last_seen_at = $now;
            $existing->save();

            return;
        }

        CourierFraudReport::query()->create([
            'platform_customer_id' => $customer->id,
            'phone_normalized' => $phoneNormalized,
            'courier' => $courier,
            'reporter_name' => isset($fraud['name']) ? (string) $fraud['name'] : null,
            'details' => $details,
            'consignment_id' => $consignmentId !== '' ? $consignmentId : null,
            'reported_at' => $reportedAt ? Carbon::parse($reportedAt) : null,
            'fingerprint' => $fingerprint,
            'source_access_token_id' => $sourceAccessTokenId,
            'raw_payload' => $fraud,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);
    }
}
