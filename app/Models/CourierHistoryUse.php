<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierHistoryUse extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'checked_on',
    ];

    protected $casts = [
        'checked_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
