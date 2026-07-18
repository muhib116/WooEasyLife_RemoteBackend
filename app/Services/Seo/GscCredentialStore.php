<?php

namespace App\Services\Seo;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Persists GSC OAuth refresh token outside .env (PlatformSetting + Crypt).
 */
class GscCredentialStore
{
    public const REFRESH_TOKEN_KEY = 'seo.gsc.refresh_token';

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

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
