<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStoreSyncLog extends Model
{
    public const MAX_ATTEMPTS = 3;

    /**
     * @var list<string>
     */
    public const RETRYABLE_MESSAGES = [
        'forward_failed',
        'forward_exception',
    ];

    protected $fillable = [
        'merchant_user_id',
        'merchant_employee_id',
        'website_id',
        'domain',
        'action',
        'success',
        'message',
        'http_status',
        'attempt_count',
        'max_attempts',
        'retry_scheduled',
        'payload',
        'last_attempted_at',
        'resolved_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'retry_scheduled' => 'boolean',
        'payload' => 'array',
        'last_attempted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(MerchantEmployee::class, 'merchant_employee_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function canRetry(): bool
    {
        if ($this->success || $this->resolved_at !== null) {
            return false;
        }

        if ($this->attempt_count >= $this->max_attempts) {
            return false;
        }

        return in_array((string) $this->message, self::RETRYABLE_MESSAGES, true);
    }
}
