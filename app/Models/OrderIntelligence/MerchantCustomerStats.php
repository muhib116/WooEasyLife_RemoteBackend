<?php

namespace App\Models\OrderIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantCustomerStats extends Model
{
    protected $fillable = [
        'platform_customer_id',
        'access_token_id',
        'user_id',
        'phone_normalized',
        'counts',
        'total_orders',
        'last_order_at',
        'stats_computed_at',
    ];

    protected $casts = [
        'counts' => 'array',
        'last_order_at' => 'datetime',
        'stats_computed_at' => 'datetime',
        'total_orders' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PlatformCustomer::class, 'platform_customer_id');
    }
}
