<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseTurn extends Model
{
    protected $table = 'wise_turns';

    protected $fillable = [
        'wise_api_key_id',
        'channel',
        'conversation_id',
        'text',
        'payload',
        'config_snapshot',
        'decision',
        'evidence',
        'trace',
        'status',
        'gap',
        'latency_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'config_snapshot' => 'array',
        'decision' => 'array',
        'evidence' => 'array',
        'trace' => 'array',
        'gap' => 'boolean',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }
}
