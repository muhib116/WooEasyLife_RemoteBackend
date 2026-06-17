<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierForwardRetry extends Model
{
    protected $fillable = [
        'shipment_id',
        'webhook_event_id',
        'payload',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'last_attempt_at',
        'last_error',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(CourierShipment::class, 'shipment_id');
    }

    public function webhookEvent()
    {
        return $this->belongsTo(CourierWebhookEvent::class, 'webhook_event_id');
    }
}
