<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierWebhookEvent extends Model
{
    protected $fillable = [
        'partner',
        'environment',
        'consignment_id',
        'shipment_id',
        'access_token_id',
        'site_url',
        'wc_order_id',
        'event_type',
        'forward_status',
        'forward_message',
        'payload_summary',
    ];

    protected $casts = [
        'payload_summary' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(CourierShipment::class, 'shipment_id');
    }
}
