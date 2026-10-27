<?php

namespace App\Models;

use App\Services\FraudCheck\MerchantBulkFraudRow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkFraudBatchItem extends Model
{
    protected $fillable = [
        'batch_id',
        'position',
        'phone',
        'status',
        'source',
        'daily_result_id',
        'attempts',
        'total_orders',
        'received',
        'cancelled',
        'partial',
        'success_rate',
        'risk',
        'couriers',
        'waiting_couriers',
        'fill_report',
        'next_fill_at',
    ];

    protected $casts = [
        'couriers' => 'array',
        'waiting_couriers' => 'array',
        'fill_report' => 'array',
        'next_fill_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BulkFraudBatch::class, 'batch_id');
    }

    public function dailyResult(): BelongsTo
    {
        return $this->belongsTo(BulkFraudDailyResult::class, 'daily_result_id');
    }

    /**
     * Couriers this row still waits for, keyed by courier.
     *
     * @return array<string, array{tries: int, next_at: int, since: int, reason: string}>
     */
    public function waitingCouriers(): array
    {
        $waiting = is_array($this->waiting_couriers) ? $this->waiting_couriers : [];

        return array_filter($waiting, 'is_array');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPortalRow(): array
    {
        $waiting = in_array($this->status, ['found', 'not_found'], true) ? $this->waitingCouriers() : [];
        $risk = $this->risk;

        if ($waiting !== []) {
            $risk = 'waiting';
        } elseif ($risk === 'waiting') {
            $risk = MerchantBulkFraudRow::hasEveryCoreCourier(is_array($this->couriers) ? $this->couriers : [])
                ? MerchantBulkFraudRow::risk($this->success_rate === null ? null : (int) $this->success_rate)
                : 'incomplete';
        }

        $couriers = $this->couriers ?? [];

        foreach ($couriers as $index => $courier) {
            $key = is_array($courier) ? MerchantBulkFraudRow::courierKeyFromTitle((string) ($courier['title'] ?? '')) : null;

            if ($key !== null && isset($waiting[$key]) && ($courier['available'] ?? null) === false) {
                $couriers[$index]['reason'] = 'waiting';
            }
        }

        $row = MerchantBulkFraudRow::sanitizeStoredRow([
            'id' => $this->id,
            'phone' => $this->phone,
            'status' => $this->status === 'checking' ? 'pending' : $this->status,
            'source' => $this->source,
            'attempts' => (int) $this->attempts,
            'total_orders' => (int) $this->total_orders,
            'received' => (int) $this->received,
            'cancelled' => (int) $this->cancelled,
            'partial' => (int) $this->partial,
            'success_rate' => $this->success_rate === null ? null : (int) $this->success_rate,
            'risk' => $risk,
            'couriers' => $couriers,
        ]);

        if ($waiting !== []) {
            $row['waiting'] = array_map(fn (array $entry): array => [
                'tries' => (int) ($entry['tries'] ?? 0),
                'next_at' => Carbon::createFromTimestamp((int) ($entry['next_at'] ?? 0))->toIso8601String(),
                'reason' => (string) ($entry['reason'] ?? 'no_answer'),
            ], $waiting);
        }

        return $row;
    }
}
