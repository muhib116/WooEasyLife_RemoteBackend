<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerNotice extends Model
{
    public const TYPES = ['offer', 'maintenance', 'feature', 'general'];

    public const SEVERITIES = ['info', 'warning', 'success', 'danger'];

    public const AUDIENCES = [
        'all',
        'active_subscribers',
        'expiring_soon',
        'recent_expired',
        'not_renewed',
    ];

    protected $fillable = [
        'title',
        'body',
        'type',
        'severity',
        'audience',
        'cta_label',
        'cta_url',
        'is_dismissible',
        'is_active',
        'priority',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_dismissible' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Notices that are currently live (active toggle + within schedule window).
     *
     * @param  Builder<CustomerNotice>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
