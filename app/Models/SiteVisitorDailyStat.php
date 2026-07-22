<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteVisitorDailyStat extends Model
{
    protected $fillable = [
        'date',
        'path',
        'pageviews',
        'unique_visitors',
        'sessions',
        'avg_engaged_ms',
        'scroll_50_count',
        'cta_clicks',
    ];

    protected $casts = [
        'date' => 'date',
        'pageviews' => 'integer',
        'unique_visitors' => 'integer',
        'sessions' => 'integer',
        'avg_engaged_ms' => 'integer',
        'scroll_50_count' => 'integer',
        'cta_clicks' => 'integer',
    ];
}
