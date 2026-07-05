<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInquiry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'order_limit' => 'integer',
        'total_amount' => 'float',
        'transaction_charge' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function packageHub(): BelongsTo
    {
        return $this->belongsTo(PackageHub::class);
    }

    public function packagePaymentRequest(): BelongsTo
    {
        return $this->belongsTo(PackagePaymentRequest::class);
    }
}
