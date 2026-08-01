<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseKnowledgeItem extends Model
{
    protected $table = 'wise_knowledge_items';

    protected $fillable = [
        'wise_api_key_id',
        'type',
        'title',
        'question',
        'answer',
        'keywords',
        'status',
        'version',
    ];

    protected $casts = [
        'keywords' => 'array',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
