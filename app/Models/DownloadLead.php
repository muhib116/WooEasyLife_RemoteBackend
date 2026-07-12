<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadLead extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'website',
        'phone_verified_at',
        'download_token',
        'download_token_expires_at',
        'downloads_count',
        'last_download_at',
        'last_asset',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'download_token_expires_at' => 'datetime',
            'last_download_at' => 'datetime',
        ];
    }

    public function hasValidDownloadToken(): bool
    {
        return filled($this->download_token)
            && $this->download_token_expires_at
            && $this->download_token_expires_at->isFuture();
    }
}
