<?php

namespace App\Services\FraudCheck;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Platform Steadfast score-API keys saved from the admin panel.
 * Merchant plugin checks still use each store's own courier keys.
 */
class SteadfastFraudApiCredentialStore
{
    public const SETTING_KEY = 'fraud_check.steadfast_api';

    /**
     * @return array{api_key: string, secret_key: string}|null
     */
    public function pair(): ?array
    {
        $stored = $this->read();
        if ($stored === null) {
            return null;
        }

        return [
            'api_key' => $stored['api_key'],
            'secret_key' => $stored['secret_key'],
        ];
    }

    /**
     * @return array{saved: bool, api_key: string, has_secret: bool, env_configured: bool}
     */
    public function adminState(): array
    {
        $stored = $this->read();

        return [
            'saved' => $stored !== null,
            'api_key' => $stored['api_key'] ?? '',
            'has_secret' => $stored !== null,
            'env_configured' => filled(config('fraud-checker-bd-courier.steadfast.api_key'))
                && filled(config('fraud-checker-bd-courier.steadfast.secret_key')),
        ];
    }

    public function put(string $apiKey, ?string $secretKey): void
    {
        if (! $this->tableReady()) {
            throw new InvalidArgumentException('Platform settings are not available yet.');
        }

        $apiKey = trim($apiKey);
        $secretKey = $secretKey !== null ? trim($secretKey) : '';

        if ($apiKey === '') {
            throw new InvalidArgumentException('API key is required.');
        }

        if ($secretKey === '') {
            $existing = $this->read();
            if ($existing === null) {
                throw new InvalidArgumentException('Secret key is required.');
            }

            $secretKey = $existing['secret_key'];
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => [
                'api_key' => Crypt::encryptString($apiKey),
                'secret_key' => Crypt::encryptString($secretKey),
            ]],
        );
    }

    public function clear(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->where('key', self::SETTING_KEY)->delete();
    }

    /**
     * @return array{api_key: string, secret_key: string}|null
     */
    private function read(): ?array
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::SETTING_KEY)->first();
        $value = $row?->value;
        if (! is_array($value)) {
            return null;
        }

        $apiKey = $this->decrypt((string) ($value['api_key'] ?? ''));
        $secretKey = $this->decrypt((string) ($value['secret_key'] ?? ''));

        if ($apiKey === null || $secretKey === null) {
            return null;
        }

        return [
            'api_key' => $apiKey,
            'secret_key' => $secretKey,
        ];
    }

    private function decrypt(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $plain = trim(Crypt::decryptString($value));

            return $plain !== '' ? $plain : null;
        } catch (DecryptException|Throwable) {
            return null;
        }
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
