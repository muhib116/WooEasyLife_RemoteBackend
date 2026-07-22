<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteGscPageMetric extends Model
{
    protected $fillable = [
        'page_url',
        'path',
        'clicks_28d',
        'impressions_28d',
        'ctr_28d',
        'position_28d',
        'metrics_refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'ctr_28d' => 'float',
            'position_28d' => 'float',
            'metrics_refreshed_at' => 'datetime',
        ];
    }
}
