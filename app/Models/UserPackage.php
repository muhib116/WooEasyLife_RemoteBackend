<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPackage extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'features' => 'array',
        'order_rate_token' => 'integer',
        'app_connect' => 'boolean',
        'total_website_connect' => 'integer',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
    
}
