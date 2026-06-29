<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class MerchantEmployee extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function websites(): BelongsToMany
    {
        return $this->belongsToMany(
            Website::class,
            'merchant_employee_website',
            'merchant_employee_id',
            'website_id'
        )->withTimestamps();
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        return asset('storage/'.$this->photo);
    }

    public function isAssignedToWebsite(?int $websiteId): bool
    {
        if (! $websiteId) {
            return false;
        }

        $this->loadMissing('websites');

        if ($this->websites->isNotEmpty()) {
            return $this->websites->contains('id', $websiteId);
        }

        if ($this->website_id) {
            return (int) $this->website_id === $websiteId;
        }

        return true;
    }
}
