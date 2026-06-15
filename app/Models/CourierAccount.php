<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierAccount extends Model
{
    protected $fillable = [
        'user_id',
        'courier_configuration_id',
        'partner',
        'environment',
        'credential_hash',
        'webhook_verify_secret',
        'is_active',
        'retired_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'retired_at' => 'datetime',
    ];

    public function configuration()
    {
        return $this->belongsTo(CourierConfiguration::class, 'courier_configuration_id');
    }

    public function shipments()
    {
        return $this->hasMany(CourierShipment::class, 'courier_account_id');
    }
}
