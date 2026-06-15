<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseCourierAccount extends Model
{
    protected $fillable = [
        'access_token_id',
        'courier_account_id',
        'is_current',
        'assigned_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
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
