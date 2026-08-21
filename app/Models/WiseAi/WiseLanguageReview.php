<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseLanguageReview extends Model
{
    protected $table = 'wise_language_reviews';

    protected $fillable = [
        'wise_api_key_id',
        'token',
        'kind',
        'channel',
        'sample_text',
        'hit_count',
        'suggested_pack_slug',
        'suggested_category',
        'suggested_concept_key',
        'rank_score',
        'key_breadth',
        'status',
        'wise_language_entry_id',
        'first_turn_id',
        'last_turn_id',
        'last_seen_at',
        'handled_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'handled_at' => 'datetime',
        'rank_score' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            // Keep unique (scope, token) in sync for nullable platform keys (scope = 0).
            $model->wise_api_key_scope = (int) ($model->wise_api_key_id ?? 0);
        });
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(WiseLanguageEntry::class, 'wise_language_entry_id');
    }
}
