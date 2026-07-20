<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogAiMemory extends Model
{
    public const TYPE_KEYWORD_PREFER = 'keyword_prefer';

    public const TYPE_KEYWORD_AVOID = 'keyword_avoid';

    public const TYPE_TOPIC = 'topic';

    public const TYPE_INSTRUCTION = 'instruction';

    public const TYPE_BRAND_NOTE = 'brand_note';

    public const TYPE_LESSON = 'lesson';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LEARNING = 'learning';

    public const SOURCE_COMPETITOR = 'competitor';

    public const SOURCE_SYSTEM = 'system';

    public const TYPES = [
        self::TYPE_KEYWORD_PREFER,
        self::TYPE_KEYWORD_AVOID,
        self::TYPE_TOPIC,
        self::TYPE_INSTRUCTION,
        self::TYPE_BRAND_NOTE,
        self::TYPE_LESSON,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'content',
        'normalized_key',
        'cluster',
        'source',
        'priority',
        'hits',
        'is_active',
        'meta_json',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'hits' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function normalizeKey(string $content): string
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $content) ?? $content));

        return mb_substr($key, 0, 180);
    }
}
