<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogLearningInsight extends Model
{
    protected $fillable = [
        'scope',
        'payload_json',
        'summary_bn',
        'posts_analyzed',
        'events_analyzed',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public static function latestGlobal(): ?self
    {
        return static::query()
            ->where('scope', 'global')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();
    }
}
