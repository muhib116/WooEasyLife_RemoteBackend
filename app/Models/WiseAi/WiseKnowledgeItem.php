<?php

namespace App\Models\WiseAi;

use App\WiseAi\Knowledge\KnowledgeLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseKnowledgeItem extends Model
{
    protected $table = 'wise_knowledge_items';

    protected $fillable = [
        'wise_api_key_id',
        'external_id',
        'type',
        'scope',
        'title',
        'question',
        'answer',
        'keywords',
        'meta',
        'status',
        'version',
    ];

    protected $casts = [
        'keywords' => 'array',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (WiseKnowledgeItem $item): void {
            $item->match_text = KnowledgeLookup::buildMatchText(
                (string) $item->title,
                (string) ($item->question ?? ''),
                $item->keywords ?? [],
            );
        });
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
