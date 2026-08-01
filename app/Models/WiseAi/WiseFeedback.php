<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseFeedback extends Model
{
    protected $table = 'wise_feedback';

    protected $fillable = [
        'wise_api_key_id',
        'wise_turn_id',
        'outcome',
        'reason_code',
        'edited_reply',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(WiseTurn::class, 'wise_turn_id');
    }
}
