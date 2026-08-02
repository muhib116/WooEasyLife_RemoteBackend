<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseExperienceSignal extends Model
{
    protected $table = 'wise_experience_signals';

    protected $fillable = [
        'wise_api_key_id',
        'signal_type',
        'intent',
        'action',
        'source',
        'pattern_key',
        'weight',
        'idempotency_key',
        'wise_turn_id',
        'meta',
    ];

    protected $casts = [
        'weight' => 'float',
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
