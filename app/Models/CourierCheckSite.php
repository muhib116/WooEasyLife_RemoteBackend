<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierCheckSite extends Model
{
    protected $fillable = [
        'site_key',
        'domain',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }
}
