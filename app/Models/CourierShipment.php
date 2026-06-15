<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierShipment extends Model
{
    protected $fillable = [
        'partner',
        'environment',
        'consignment_id',
        'invoice',
        'wc_order_id',
        'user_id',
        'access_token_id',
        'site_url',
        'site_domain',
        'courier_account_id',
        'courier_configuration_id',
        'status',
        'last_webhook_at',
    ];

    protected $casts = [
        'last_webhook_at' => 'datetime',
    ];

    public function accessToken()
    {
        return $this->belongsTo(AccessToken::class, 'access_token_id');
    }

    public function courierAccount()
    {
        return $this->belongsTo(CourierAccount::class, 'courier_account_id');
    }
}
