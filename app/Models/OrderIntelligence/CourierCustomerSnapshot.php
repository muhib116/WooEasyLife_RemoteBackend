<?php

namespace App\Models\OrderIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierCustomerSnapshot extends Model
{
    protected $fillable = [
        'platform_customer_id',
        'phone_normalized',
        'courier',
        'total_order',
        'confirmed',
        'cancel',
        'success_rate',
        'customer_rating',
        'frauds_count',
        'raw_report',
        'fetched_at',
        'source_access_token_id',
    ];

    protected $casts = [
        'raw_report' => 'array',
        'fetched_at' => 'datetime',
        'total_order' => 'integer',
        'confirmed' => 'integer',
        'cancel' => 'integer',
        'frauds_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PlatformCustomer::class, 'platform_customer_id');
    }
}
