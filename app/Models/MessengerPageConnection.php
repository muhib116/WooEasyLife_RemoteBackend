<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerPageConnection extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'scopes' => 'array',
        'page_access_token' => 'encrypted',
        'user_access_token' => 'encrypted',
        'webhook_subscribed_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    protected $hidden = [
        'page_access_token',
        'user_access_token',
    ];

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(AccessToken::class, 'access_token_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }
}
