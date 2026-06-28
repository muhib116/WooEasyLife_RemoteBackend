<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $guarded = ['id'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function adminUsers(): HasMany
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }

    public function merchantEmployees(): HasMany
    {
        return $this->hasMany(MerchantEmployee::class);
    }

    public function isPlatformScope(): bool
    {
        return $this->scope === 'platform';
    }

    public function isMerchantScope(): bool
    {
        return $this->scope === 'merchant';
    }
}
