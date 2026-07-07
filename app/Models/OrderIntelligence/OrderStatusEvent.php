<?php

namespace App\Models\OrderIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'platform_order_id',
        'platform_customer_id',
        'from_status',
        'to_status',
        'source',
        'access_token_id',
        'courier_webhook_event_id',
        'partner',
        'raw_status',
        'occurred_at',
        'idempotency_key',
        'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PlatformOrder::class, 'platform_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PlatformCustomer::class, 'platform_customer_id');
    }
}
