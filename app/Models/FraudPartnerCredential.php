<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class FraudPartnerCredential extends Model
{
    public const COURIERS = [
        'steadfast',
        'pathao',
        'paperfly',
        'redx',
        'carrybee',
    ];

    protected $fillable = [
        'courier',
        'label',
        'identifier',
        'secret',
        'is_active',
        'priority',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'last_used_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function setSecretAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->attributes['secret'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute(): ?string
    {
        $encrypted = $this->attributes['secret'] ?? null;
        if (! filled($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function maskedIdentifier(): string
    {
        $value = (string) $this->identifier;
        $len = strlen($value);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 2).str_repeat('*', max(0, $len - 4)).substr($value, -2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'courier' => $this->courier,
            'label' => $this->label,
            'identifier' => $this->identifier,
            'masked_identifier' => $this->maskedIdentifier(),
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'last_success_at' => $this->last_success_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'has_secret' => filled($this->attributes['secret'] ?? null),
            'source' => 'database',
        ];
    }
}
