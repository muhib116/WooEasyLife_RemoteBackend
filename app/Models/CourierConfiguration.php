<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierConfiguration extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];
}
