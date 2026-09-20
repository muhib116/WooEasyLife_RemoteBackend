<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaConnection extends Model
{
    public const SOURCE_SYSTEM_USER = 'system_user';

    public const SOURCE_LEGACY_OAUTH = 'legacy_oauth';

    protected $guarded = ['id'];

    protected $casts = [
        'system_user_token' => 'encrypted',
        'granted_scopes' => 'array',
        'last_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'system_user_token',
        'license_key',
    ];

    public function adAccounts(): HasMany
    {
        return $this->hasMany(MetaAdAccount::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(MetaPage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
