<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WiseLanguagePack extends Model
{
    protected $table = 'wise_language_packs';

    protected $fillable = [
        'slug', 'kind', 'name', 'semver', 'status', 'locale_scope',
        'depends_on', 'compiler_min_version', 'meta',
    ];

    protected $casts = [
        'depends_on' => 'array',
        'meta' => 'array',
        'compiler_min_version' => 'integer',
    ];

    public function concepts(): HasMany
    {
        return $this->hasMany(WiseLanguageConcept::class, 'pack_id');
    }

    public function surfaces(): HasMany
    {
        return $this->hasMany(WiseLanguageSurface::class, 'pack_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(WiseLanguageArtifact::class, 'pack_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WiseLanguagePackAssignment::class, 'pack_id');
    }
}
