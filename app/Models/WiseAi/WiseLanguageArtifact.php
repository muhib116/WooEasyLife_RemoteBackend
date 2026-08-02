<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseLanguageArtifact extends Model
{
    protected $table = 'wise_language_artifacts';

    protected $fillable = [
        'pack_id', 'pack_version', 'compiler_version', 'content_hash',
        'artifact_json', 'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(WiseLanguagePack::class, 'pack_id');
    }

    /** @return array<string, mixed> */
    public function decoded(): array
    {
        $data = json_decode((string) $this->artifact_json, true);

        return is_array($data) ? $data : [];
    }
}
