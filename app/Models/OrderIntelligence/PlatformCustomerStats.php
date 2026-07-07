<?php

namespace App\Models\OrderIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformCustomerStats extends Model
{
    protected $fillable = [
        'platform_customer_id',
        'phone_normalized',
        'counts',
        'rates',
        'total_orders',
        'total_merchants',
        'total_revenue',
        'delivery_rate',
        'return_rate',
        'risk_tier',
        'risk_score',
        'ai_profile',
        'last_order_at',
        'stats_computed_at',
        'version',
    ];

    protected $casts = [
        'counts' => 'array',
        'rates' => 'array',
        'ai_profile' => 'array',
        'total_revenue' => 'decimal:2',
        'delivery_rate' => 'decimal:4',
        'return_rate' => 'decimal:4',
        'last_order_at' => 'datetime',
        'stats_computed_at' => 'datetime',
        'total_orders' => 'integer',
        'total_merchants' => 'integer',
        'version' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PlatformCustomer::class, 'platform_customer_id');
    }
}
