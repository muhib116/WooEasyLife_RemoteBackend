<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageHub extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_special' => 'boolean',
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
}
