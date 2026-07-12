<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInquiry extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REJECTED = 'rejected';

    /** Statuses that block a new purchase request. */
    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONTACTED,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'order_limit' => 'integer',
        'total_amount' => 'float',
        'transaction_charge' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function packageHub(): BelongsTo
    {
        return $this->belongsTo(PackageHub::class);
    }

    public function packagePaymentRequest(): BelongsTo
    {
        return $this->belongsTo(PackagePaymentRequest::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn($query->getModel()->getTable().'.status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
