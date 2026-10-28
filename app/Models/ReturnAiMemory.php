<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A store's own return desk rule: in this situation, do this.
 */
class ReturnAiMemory extends Model
{
    protected $fillable = [
        'website_id',
        'personal_access_token_id',
        'user_id',
        'situation',
        'instruction',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
