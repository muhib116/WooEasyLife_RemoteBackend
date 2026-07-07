<?php

namespace App\Models\OrderIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantOrderDetail extends Model
{
    protected $fillable = [
        'platform_order_id',
        'access_token_id',
        'customer_name',
        'customer_address',
        'customer_email',
        'product_title',
        'product_sku',
        'quantity',
        'line_items',
    ];

    protected $casts = [
        'line_items' => 'array',
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PlatformOrder::class, 'platform_order_id');
    }
}
