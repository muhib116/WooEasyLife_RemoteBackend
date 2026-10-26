<?php

namespace App\Services\FraudCheck;

use App\Models\BulkFraudBatchItem;
use App\Models\BulkFraudDailyResult;
use Carbon\Carbon;

/**
 * Keep today's saved bulk row, and the batch lines that point at it, aligned
 * with a newer courier report. Does not create a daily row on its own.
 */
class MerchantBulkDailySync
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function syncIfPresent(string $phoneNormalized, array $report): void
    {
        if (! MerchantBulkFraudRow::isUsable($report)) {
            return;
        }

        try {
            $today = Carbon::now('Asia/Dhaka')->toDateString();
            $existing = BulkFraudDailyResult::query()
                ->where('phone', $phoneNormalized)
                ->where('checked_on', $today)
                ->first();

            if (! $existing) {
                return;
            }

            $row = MerchantBulkFraudRow::fromReport($phoneNormalized, $report);

            $existingOrders = (int) $existing->total_orders + (int) $existing->received + (int) $existing->cancelled + (int) $existing->partial;
            $newOrders = (int) ($row['total_orders'] ?? 0) + (int) ($row['received'] ?? 0) + (int) ($row['cancelled'] ?? 0) + (int) ($row['partial'] ?? 0);

            if ($existingOrders > 0 && $newOrders === 0) {
                return;
            }

            $attributes = [
                'status' => $row['status'],
                'total_orders' => $row['total_orders'],
                'received' => $row['received'],
                'cancelled' => $row['cancelled'],
                'partial' => $row['partial'],
                'success_rate' => $row['success_rate'],
                'risk' => $row['risk'],
                'couriers' => $row['couriers'],
            ];

            $existing->update($attributes);

            BulkFraudBatchItem::query()
                ->where('daily_result_id', $existing->id)
                ->whereIn('status', ['found', 'not_found'])
                ->get()
                ->each(function (BulkFraudBatchItem $item) use ($attributes): void {
                    $item->fill($attributes)->save();
                });
        } catch (\Throwable) {
            // Best-effort sync; never interrupt the fraud check response.
        }
    }
}
