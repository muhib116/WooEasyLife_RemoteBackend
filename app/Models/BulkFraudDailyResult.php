<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkFraudDailyResult extends Model
{
    protected $fillable = [
        'phone',
        'checked_on',
        'status',
        'total_orders',
        'received',
        'cancelled',
        'partial',
        'success_rate',
        'risk',
        'couriers',
        'attempts',
    ];

    protected $casts = [
        'checked_on' => 'date',
        'couriers' => 'array',
    ];

    /**
     * @return array<string, mixed>
     */
    public function counts(): array
    {
        return [
            'status' => $this->status,
            'total_orders' => (int) $this->total_orders,
            'received' => (int) $this->received,
            'cancelled' => (int) $this->cancelled,
            'partial' => (int) $this->partial,
            'success_rate' => $this->success_rate === null ? null : (int) $this->success_rate,
            'risk' => $this->risk,
            'couriers' => $this->couriers ?? [],
            'attempts' => (int) $this->attempts,
        ];
    }
}
