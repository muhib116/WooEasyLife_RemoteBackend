<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogGscQueryMetric extends Model
{
    protected $table = 'blog_gsc_query_metrics';

    public const BUCKET_STRIKING = 'striking_distance';

    public const BUCKET_FIX_CTR = 'fix_ctr';

    public const BUCKET_DEFEND = 'defend';

    public const BUCKET_BURIED = 'buried';

    public const BUCKET_CANNIBALIZED = 'cannibalized';

    public const BUCKET_OTHER = 'other';

    protected $fillable = [
        'pair_hash',
        'query',
        'page_url',
        'slug',
        'clicks_28d',
        'impressions_28d',
        'ctr_28d',
        'position_28d',
        'bucket',
        'opportunity_score',
        'improvement_hint',
        'metrics_refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'ctr_28d' => 'float',
            'position_28d' => 'float',
            'opportunity_score' => 'float',
            'metrics_refreshed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'slug', 'slug');
    }
}
