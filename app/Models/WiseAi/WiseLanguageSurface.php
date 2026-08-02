<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseLanguageSurface extends Model
{
    protected $table = 'wise_language_surfaces';

    protected $fillable = [
        'pack_id', 'concept_id', 'surface_text', 'surface_hash', 'to_text', 'script', 'region_tags',
        'confidence', 'popularity', 'frequency', 'last_seen_at',
        'merchant_count', 'region_count', 'industry_count',
        'approval_status', 'deprecated', 'replacement_concept_id',
        'evidence_source', 'meta',
    ];

    protected $casts = [
        'region_tags' => 'array',
        'meta' => 'array',
        'deprecated' => 'boolean',
        'confidence' => 'float',
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $surface) {
            $surface->surface_hash = hash('sha1', (string) $surface->surface_text);
        });
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(WiseLanguagePack::class, 'pack_id');
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(WiseLanguageConcept::class, 'concept_id');
    }
}
