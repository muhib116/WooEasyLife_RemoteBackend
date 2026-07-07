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
     */
    public function persistFromFraudCheckReports(
        PlatformCustomer $customer,
        string $phoneNormalized,
        array $steadfastReport,
        array $pathaoReport,
        array $paperflyReport,
        ?int $sourceAccessTokenId = null,
    ): void {
        $now = now();

        $this->upsertSnapshot($customer, $phoneNormalized, 'steadfast', $steadfastReport, $now, $sourceAccessTokenId);
        $this->upsertSnapshot($customer, $phoneNormalized, 'pathao', $pathaoReport, $now, $sourceAccessTokenId);
        $this->upsertSnapshot($customer, $phoneNormalized, 'paperfly', $paperflyReport, $now, $sourceAccessTokenId);

        $frauds = is_array($steadfastReport['frauds'] ?? null) ? $steadfastReport['frauds'] : [];

        foreach ($frauds as $fraud) {
            if (! is_array($fraud)) {
                continue;
            }

            $this->upsertFraudReport($customer, $phoneNormalized, $fraud, $sourceAccessTokenId, $now);
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
                'frauds_count' => count(is_array($report['frauds'] ?? null) ? $report['frauds'] : []),
                'raw_report' => $report,
                'fetched_at' => $fetchedAt,
                'source_access_token_id' => $sourceAccessTokenId,
            ],
        );
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

        $consignmentId = trim((string) ($fraud['consignment_id'] ?? ''));
        $reportedAt = $fraud['created_at'] ?? null;
        $fingerprint = hash('sha256', implode('|', [
            'steadfast',
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
            'courier' => 'steadfast',
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
