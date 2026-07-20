<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogCompetitorAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'keyword',
        'cluster',
        'competitor_urls',
        'snapshots_json',
        'insight_json',
        'summary_bn',
        'beat_score',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'competitor_urls' => 'array',
            'snapshots_json' => 'array',
            'insight_json' => 'array',
            'beat_score' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function latestForKeyword(string $keyword): ?self
    {
        $key = mb_strtolower(trim($keyword));
        if ($key === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(keyword) = ?', [$key])
            ->orderByDesc('id')
            ->first();
    }
}
