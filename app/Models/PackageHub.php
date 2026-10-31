<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class PackageHub extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_special' => 'boolean',
        'show_on_frontend' => 'boolean',
        'app_connect' => 'boolean',
        'features' => 'array',
        'trial_days' => 'integer',
        'order_rate_token' => 'integer',
        'package_price' => 'float',
        'total_website_connect' => 'integer',
        'per_order_rate' => 'float',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function subscriptions()
    {
        return $this->hasMany(UserPackage::class, 'package_hub_id', 'id');
    }

    /**
     * Plans the public homepage pricing section and /pricing page may show.
     */
    public function scopeForPublicPricing($query)
    {
        $query->where('is_active', true)
            ->whereNotNull('package_duration');

        if (Schema::hasColumn($this->getTable(), 'show_on_frontend')) {
            $query->where($this->getTable().'.show_on_frontend', true);
        }

        return $query;
    }
}
