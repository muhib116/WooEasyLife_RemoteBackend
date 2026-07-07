<?php

namespace App\Models\OrderIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierFraudReport extends Model
{
    protected $fillable = [
        'platform_customer_id',
        'phone_normalized',
        'courier',
        'reporter_name',
        'details',
        'consignment_id',
        'reported_at',
        'fingerprint',
        'source_access_token_id',
        'raw_payload',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'reported_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PlatformCustomer::class, 'platform_customer_id');
    }
}
