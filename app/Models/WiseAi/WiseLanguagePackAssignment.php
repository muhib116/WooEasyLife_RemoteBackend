<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseLanguagePackAssignment extends Model
{
    protected $table = 'wise_language_pack_assignments';

    protected $fillable = [
        'target_type', 'target_id', 'pack_id', 'priority', 'enabled', 'meta',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
        'meta' => 'array',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(WiseLanguagePack::class, 'pack_id');
    }
}
