<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WiseLanguageConcept extends Model
{
    protected $table = 'wise_language_concepts';

    protected $fillable = [
        'pack_id', 'category', 'concept_key', 'gloss_en', 'gloss_bn', 'status', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(WiseLanguagePack::class, 'pack_id');
    }

    public function surfaces(): HasMany
    {
        return $this->hasMany(WiseLanguageSurface::class, 'concept_id');
    }
}
