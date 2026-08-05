<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'gap_handled_at',
        'gap_knowledge_id',
        'gap_auto_draft_id',
        'latency_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'config_snapshot' => 'array',
        'decision' => 'array',
        'evidence' => 'array',
        'trace' => 'array',
        'gap' => 'boolean',
        'gap_handled_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }

    public function gapKnowledge(): BelongsTo
    {
        return $this->belongsTo(WiseKnowledgeItem::class, 'gap_knowledge_id');
    }

    public function gapAutoDraft(): BelongsTo
    {
        return $this->belongsTo(WiseKnowledgeItem::class, 'gap_auto_draft_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(WiseFeedback::class, 'wise_turn_id');
    }

    public function latestFeedback(): HasOne
    {
        return $this->hasOne(WiseFeedback::class, 'wise_turn_id')->latestOfMany();
    }
}
