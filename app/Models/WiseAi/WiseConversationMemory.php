<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soft conversation rollup (summary/goal/preferences) — not groundable knowledge.
 */
class WiseConversationMemory extends Model
{
    protected $table = 'wise_conversation_memories';

    protected $fillable = [
        'wise_api_key_id',
        'conversation_id',
        'summary',
        'goal',
        'preferences',
        'last_turn_id',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'last_turn_id' => 'integer',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }
}
