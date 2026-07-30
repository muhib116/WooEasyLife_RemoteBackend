<?php

namespace App\Services\Seo;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Persists GA4 OAuth refresh token, property ID, and public gtag settings outside .env (PlatformSetting).
 */
class GaCredentialStore
{
    public const REFRESH_TOKEN_KEY = 'seo.ga.refresh_token';

    public const PROPERTY_ID_KEY = 'seo.ga.property_id';

    /** Public site gtag.js Measurement ID (G-XXXX). */
    public const MEASUREMENT_ID_KEY = 'seo.ga.measurement_id';

    /** When set to "0", public gtag is off even if env has a Measurement ID. */
    public const MEASUREMENT_ENABLED_KEY = 'seo.ga.measurement_enabled';

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

        // Measurement IDs (G-XXXX) must never be digit-stripped into a fake property ID.
        if ($this->isMeasurementId($id)) {
            return null;
        }

        if (preg_match('#properties/(\d+)#i', $id, $m) === 1) {
            return $m[1];
        }

        // Strict numeric property ID only (no mixed alphanumeric).
        if (preg_match('/^\d{6,12}$/', $id) === 1) {
            return $id;
        }

        return null;
    }

    public function isMeasurementId(string $raw): bool
    {
        return preg_match('/^G-[A-Z0-9]+$/i', trim($raw)) === 1;
    }

    public function normalizeMeasurementId(string $raw): ?string
    {
        $id = strtoupper(trim($raw));
        if (! $this->isMeasurementId($id)) {
            return null;
        }

        return $id;
    }

    public function getMeasurementId(): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::MEASUREMENT_ID_KEY)->first();
        if (! $row || ! is_string($row->value)) {
            return null;
        }

        return $this->normalizeMeasurementId($row->value);
    }

    public function putMeasurementId(?string $measurementId): void
    {
        if (! $this->tableReady()) {
            throw new \RuntimeException('platform_settings table is missing — run migrations.');
        }

        $normalized = $measurementId === null || trim($measurementId) === ''
            ? null
            : $this->normalizeMeasurementId($measurementId);

        if ($normalized === null) {
            PlatformSetting::query()->where('key', self::MEASUREMENT_ID_KEY)->delete();

            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::MEASUREMENT_ID_KEY],
            ['value' => $normalized],
        );
    }

    public function clearMeasurementId(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->where('key', self::MEASUREMENT_ID_KEY)->delete();
    }

    public function hasStoredMeasurementId(): bool
    {
        return filled($this->getMeasurementId());
    }

    /**
     * null = no admin override (env default applies when enabled).
     * true/false = explicit admin choice.
     */
    public function getMeasurementEnabled(): ?bool
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::MEASUREMENT_ENABLED_KEY)->first();
        if (! $row || ! is_string($row->value)) {
            return null;
        }

        $value = strtolower(trim($row->value));
        if ($value === '1' || $value === 'true' || $value === 'yes' || $value === 'on') {
            return true;
        }
        if ($value === '0' || $value === 'false' || $value === 'no' || $value === 'off') {
            return false;
        }

        return null;
    }

    public function putMeasurementEnabled(?bool $enabled): void
    {
        if (! $this->tableReady()) {
            throw new \RuntimeException('platform_settings table is missing — run migrations.');
        }

        if ($enabled === null) {
            PlatformSetting::query()->where('key', self::MEASUREMENT_ENABLED_KEY)->delete();

            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::MEASUREMENT_ENABLED_KEY],
            ['value' => $enabled ? '1' : '0'],
        );
    }

    public function clearMeasurementEnabled(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->where('key', self::MEASUREMENT_ENABLED_KEY)->delete();
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
