<?php

namespace App\Models\OrderIntelligence;

use App\Models\CourierShipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformOrder extends Model
{
    protected $fillable = [
        'platform_customer_id',
        'access_token_id',
        'user_id',
        'wc_order_id',
        'external_ref',
        'current_status',
        'status_changed_at',
        'courier_partner',
        'consignment_id',
        'courier_shipment_id',
        'order_amount',
        'currency',
        'product_category',
        'source',
        'fraud_checked_at',
    ];

    protected $casts = [
        'order_amount' => 'decimal:2',
        'status_changed_at' => 'datetime',
        'fraud_checked_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PlatformCustomer::class, 'platform_customer_id');
    }

    public function details(): HasOne
    {
        return $this->hasOne(MerchantOrderDetail::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class);
    }

    public function courierShipment(): BelongsTo
    {
        return $this->belongsTo(CourierShipment::class, 'courier_shipment_id');
    }
}
