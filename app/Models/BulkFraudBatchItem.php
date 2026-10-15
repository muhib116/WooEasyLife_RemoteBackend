<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'couriers' => 'array',
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
     * @return array<string, mixed>
     */
    public function toPortalRow(): array
    {
        return [
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
            'risk' => $this->risk,
            'couriers' => $this->couriers ?? [],
        ];
    }
}
