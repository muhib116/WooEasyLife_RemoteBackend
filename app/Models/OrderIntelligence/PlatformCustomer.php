<?php

namespace App\Models\OrderIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformCustomer extends Model
{
    protected $fillable = [
        'phone_normalized',
        'latest_name',
        'latest_address',
        'first_seen_at',
        'last_seen_at',
        'last_order_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_order_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(PlatformOrder::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PlatformCustomerStats::class);
    }

    public function merchantStats(): HasMany
    {
        return $this->hasMany(MerchantCustomerStats::class);
    }

    public function courierSnapshots(): HasMany
    {
        return $this->hasMany(CourierCustomerSnapshot::class);
    }

    public function fraudReports(): HasMany
    {
        return $this->hasMany(CourierFraudReport::class);
    }
}
