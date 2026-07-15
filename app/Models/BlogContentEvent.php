<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogContentEvent extends Model
{
    public const TYPE_VIEW = 'view';

    public const TYPE_CTA_CLICK = 'cta_click';

    public const TYPE_SCROLL = 'scroll_depth';

    protected $fillable = [
        'slug',
        'blog_post_id',
        'event_type',
        'visitor_hash',
        'session_hash',
        'cta_label',
        'referrer_host',
        'scroll_pct',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }
}
