<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Page tokens for the unified System User Meta connection flow.
 * Token storage mirrors MessengerPageConnection (encrypted cast + hidden).
 * Existing OAuth Messenger rows in messenger_page_connections are untouched.
 */
class MetaPage extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'page_access_token' => 'encrypted',
        'is_tracked' => 'boolean',
        'webhook_subscribed' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'page_access_token',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaConnection::class, 'meta_connection_id');
    }

    public function scopeTracked($query)
    {
        return $query->where('is_tracked', true);
    }
}
