<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierLicenseLink extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'synced' => 'boolean',
    ];

    public function configuration()
    {
        return $this->belongsTo(CourierConfiguration::class, 'courier_configuration_id');
    }

    public function accessToken()
    {
        return $this->belongsTo(AccessToken::class, 'access_token_id');
    }
}
