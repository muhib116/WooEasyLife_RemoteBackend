<?php

namespace App\Services\Seo;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Persists GA4 OAuth refresh token + property ID outside .env (PlatformSetting).
 */
class GaCredentialStore
{
    public const REFRESH_TOKEN_KEY = 'seo.ga.refresh_token';

    public const PROPERTY_ID_KEY = 'seo.ga.property_id';

    public function getRefreshToken(): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::REFRESH_TOKEN_KEY)->first();
        if (! $row) {
            return null;
        }

        $value = $row->value;
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($value);

            return filled($plain) ? $plain : null;
        } catch (DecryptException|Throwable) {
            // Legacy/plain values (unlikely) — treat as unusable rather than leaking bad tokens.
            return null;
        }
    }

    public function putRefreshToken(string $token): void
    {
        if (! $this->tableReady()) {
            throw new \RuntimeException('platform_settings table is missing — run migrations.');
        }

        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Refresh token cannot be empty.');
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::REFRESH_TOKEN_KEY],
            ['value' => Crypt::encryptString($token)],
        );
    }

    public function clearRefreshToken(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->where('key', self::REFRESH_TOKEN_KEY)->delete();
    }

    public function hasStoredRefreshToken(): bool
    {
        return filled($this->getRefreshToken());
    }

    public function getPropertyId(): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::PROPERTY_ID_KEY)->first();
        if (! $row || ! is_string($row->value)) {
            return null;
        }

        return $this->normalizePropertyId($row->value);
    }

    public function putPropertyId(?string $propertyId): void
    {
        if (! $this->tableReady()) {
            throw new \RuntimeException('platform_settings table is missing — run migrations.');
        }

        $normalized = $this->normalizePropertyId((string) $propertyId);
        if ($normalized === null) {
            PlatformSetting::query()->where('key', self::PROPERTY_ID_KEY)->delete();

            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::PROPERTY_ID_KEY],
            ['value' => $normalized],
        );
    }

    public function clearPropertyId(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->where('key', self::PROPERTY_ID_KEY)->delete();
    }

    public function hasStoredPropertyId(): bool
    {
        return filled($this->getPropertyId());
    }

    public function normalizePropertyId(string $raw): ?string
    {
        $id = trim($raw);
        if ($id === '') {
            return null;
        }

        if (preg_match('#properties/(\d+)#i', $id, $m) === 1) {
            return $m[1];
        }

        $id = preg_replace('/\D+/', '', $id) ?: '';

        return $id !== '' ? $id : null;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
