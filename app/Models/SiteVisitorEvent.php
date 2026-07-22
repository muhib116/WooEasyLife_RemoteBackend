<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteVisitorEvent extends Model
{
    public const TYPE_PAGE_VIEW = 'page_view';

    public const TYPE_HEARTBEAT = 'heartbeat';

    public const TYPE_SCROLL = 'scroll_depth';

    public const TYPE_CTA_CLICK = 'cta_click';

    public const TYPE_TOOL_ACTION = 'tool_action';

    public const EVENT_TYPES = [
        self::TYPE_PAGE_VIEW,
        self::TYPE_HEARTBEAT,
        self::TYPE_SCROLL,
        self::TYPE_CTA_CLICK,
        self::TYPE_TOOL_ACTION,
    ];

    protected $fillable = [
        'path',
        'event_type',
        'visitor_hash',
        'session_hash',
        'referrer_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'search_keyword',
        'source_channel',
        'device_type',
        'country',
        'scroll_pct',
        'engaged_ms',
        'cta_label',
        'action_name',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'scroll_pct' => 'integer',
        'engaged_ms' => 'integer',
    ];
}
