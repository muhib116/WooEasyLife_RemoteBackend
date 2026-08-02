<?php

namespace App\Models\WiseAi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseCommerceEvent extends Model
{
    protected $table = 'wise_commerce_events';

    protected $fillable = [
        'wise_api_key_id',
        'event_type',
        'conversation_id',
        'wise_turn_id',
        'external_order_id',
        'platform',
        'amount',
        'currency',
        'occurred_at',
        'idempotency_key',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(WiseApiKey::class, 'wise_api_key_id');
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(WiseTurn::class, 'wise_turn_id');
    }
}
