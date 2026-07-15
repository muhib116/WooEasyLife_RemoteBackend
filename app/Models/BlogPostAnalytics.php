<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostAnalytics extends Model
{
    protected $table = 'blog_post_analytics';

    protected $fillable = [
        'slug',
        'blog_post_id',
        'title',
        'focus_keyword',
        'cluster',
        'locale',
        'views_total',
        'views_7d',
        'views_28d',
        'unique_visitors_28d',
        'cta_clicks_total',
        'cta_clicks_28d',
        'gsc_clicks_28d',
        'gsc_impressions_28d',
        'gsc_ctr_28d',
        'gsc_position_28d',
        'engagement_score',
        'top_cta_labels',
        'meta_json',
        'last_viewed_at',
        'metrics_refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'top_cta_labels' => 'array',
            'meta_json' => 'array',
            'gsc_ctr_28d' => 'float',
            'gsc_position_28d' => 'float',
            'engagement_score' => 'float',
            'last_viewed_at' => 'datetime',
            'metrics_refreshed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }
}
