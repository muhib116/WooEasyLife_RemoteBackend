<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhitelistedDomain extends Model
{
    protected $fillable = [
        'domain',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
