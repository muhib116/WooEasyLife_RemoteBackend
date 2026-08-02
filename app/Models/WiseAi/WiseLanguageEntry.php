<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseLanguageEntry extends Model
{
    protected $table = 'wise_language_entries';

    protected $fillable = [
        'wise_api_key_id',
        'type',
        'from_text',
        'to_text',
        'status',
        'enabled',
        'version',
        'meta',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'meta' => 'array',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }
}
