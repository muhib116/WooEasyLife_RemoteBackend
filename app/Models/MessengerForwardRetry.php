<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerForwardRetry extends Model
{
    protected $fillable = [
        'webhook_event_id',
        'connection_id',
        'payload',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'last_attempt_at',
        'last_error',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(MessengerWebhookEvent::class, 'webhook_event_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MessengerPageConnection::class, 'connection_id');
    }
}
