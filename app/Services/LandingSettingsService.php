<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class LandingSettingsService
{
    public const APP_DOWNLOAD_URL_KEY = 'landing.app_download_url';

    public const PLAY_STORE_URL_KEY = 'landing.play_store_url';

    public const PLUGIN_DOWNLOAD_URL_KEY = 'landing.plugin_download_url';

    public const BKASH_NUMBER_KEY = 'landing.bkash_number';

    public const ROCKET_NUMBER_KEY = 'landing.rocket_number';

    public const NAGAD_NUMBER_KEY = 'landing.nagad_number';

    public const ADMIN_WHATSAPP_KEY = 'landing.admin_whatsapp';

    public const ADMIN_EMAIL_KEY = 'landing.admin_email';

    public const ADMIN_PHONE_KEY = 'landing.admin_phone';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'app_download_url' => $this->appDownloadUrl(),
            'play_store_url' => $this->playStoreUrl(),
            'plugin_download_url' => $this->pluginDownloadUrl(),
            'app_download_url_source' => $this->source(self::APP_DOWNLOAD_URL_KEY, 'WOOEASYLIFE_ANDROID_DOWNLOAD_URL'),
            'play_store_url_source' => $this->source(self::PLAY_STORE_URL_KEY, 'WOOEASYLIFE_PLAY_STORE_URL'),
            'plugin_download_url_source' => $this->pluginSource(),
            'bkash_number' => $this->bkashNumber(),
            'rocket_number' => $this->rocketNumber(),
            'nagad_number' => $this->nagadNumber(),
            'admin_whatsapp' => $this->adminWhatsapp(),
            'admin_email' => $this->adminEmail(),
            'admin_phone' => $this->adminPhone(),
            'bkash_number_source' => $this->sourceWithConfig(self::BKASH_NUMBER_KEY, 'landing.bkash_number'),
            'rocket_number_source' => $this->sourceWithConfig(self::ROCKET_NUMBER_KEY, 'landing.rocket_number'),
            'nagad_number_source' => $this->sourceWithConfig(self::NAGAD_NUMBER_KEY, 'landing.nagad_number'),
            'admin_whatsapp_source' => $this->sourceWithConfig(self::ADMIN_WHATSAPP_KEY, 'landing.whatsapp_phone'),
            'admin_email_source' => $this->sourceWithConfig(self::ADMIN_EMAIL_KEY, 'landing.admin_email'),
            'admin_phone_source' => $this->sourceWithConfig(self::ADMIN_PHONE_KEY, 'landing.helpline_phone'),
        ];
    }

    public function appDownloadUrl(): ?string
    {
        return $this->resolve(self::APP_DOWNLOAD_URL_KEY, env('WOOEASYLIFE_ANDROID_DOWNLOAD_URL'));
    }

    public function playStoreUrl(): ?string
    {
        return $this->resolve(self::PLAY_STORE_URL_KEY, env('WOOEASYLIFE_PLAY_STORE_URL'));
    }

    public function pluginDownloadUrl(): ?string
    {
        $custom = $this->resolve(self::PLUGIN_DOWNLOAD_URL_KEY, env('WOOEASYLIFE_PLUGIN_DOWNLOAD_URL'));

        if ($custom !== null) {
            return $custom;
        }

        return $this->defaultPluginDownloadUrl();
    }

    public function bkashNumber(): ?string
    {
        return $this->resolve(self::BKASH_NUMBER_KEY, config('landing.bkash_number'));
    }

    public function rocketNumber(): ?string
    {
        return $this->resolve(self::ROCKET_NUMBER_KEY, config('landing.rocket_number'));
    }

    public function nagadNumber(): ?string
    {
        return $this->resolve(self::NAGAD_NUMBER_KEY, config('landing.nagad_number'));
    }

    public function adminWhatsapp(): ?string
    {
        return $this->resolve(self::ADMIN_WHATSAPP_KEY, config('landing.whatsapp_phone'));
    }

    public function adminEmail(): ?string
    {
        return $this->resolve(self::ADMIN_EMAIL_KEY, config('landing.admin_email'));
    }

    public function adminPhone(): ?string
    {
        return $this->resolve(self::ADMIN_PHONE_KEY, config('landing.helpline_phone'));
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function update(array $data): void
    {
        if (array_key_exists('app_download_url', $data)) {
            $this->put(self::APP_DOWNLOAD_URL_KEY, $this->normalizeUrl($data['app_download_url'] ?? null));
        }

        if (array_key_exists('play_store_url', $data)) {
            $this->put(self::PLAY_STORE_URL_KEY, $this->normalizeUrl($data['play_store_url'] ?? null));
        }

        if (array_key_exists('plugin_download_url', $data)) {
            $this->put(self::PLUGIN_DOWNLOAD_URL_KEY, $this->normalizeUrl($data['plugin_download_url'] ?? null));
        }

        foreach ([
            'bkash_number' => self::BKASH_NUMBER_KEY,
            'rocket_number' => self::ROCKET_NUMBER_KEY,
            'nagad_number' => self::NAGAD_NUMBER_KEY,
            'admin_whatsapp' => self::ADMIN_WHATSAPP_KEY,
            'admin_email' => self::ADMIN_EMAIL_KEY,
            'admin_phone' => self::ADMIN_PHONE_KEY,
        ] as $field => $key) {
            if (array_key_exists($field, $data)) {
                $this->put($key, $this->normalizeText($data[$field] ?? null));
            }
        }
    }

    private function defaultPluginDownloadUrl(): ?string
    {
        return url('/download-plugins');
    }

    private function pluginSource(): string
    {
        if ($this->getStored(self::PLUGIN_DOWNLOAD_URL_KEY) !== null) {
            return 'database';
        }

        $env = env('WOOEASYLIFE_PLUGIN_DOWNLOAD_URL');

        if (is_string($env) && trim($env) !== '') {
            return 'env';
        }

        return 'auto';
    }

    private function resolve(string $key, mixed $envFallback): ?string
    {
        $stored = $this->getStored($key);

        if ($stored !== null) {
            return $stored;
        }

        $fallback = is_string($envFallback) ? trim($envFallback) : '';

        return $fallback !== '' ? $fallback : null;
    }

    private function getStored(string $key): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', $key)->first();

        if (! $row) {
            return null;
        }

        $value = $row->value;

        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function put(string $key, ?string $value): void
    {
        if (! $this->tableReady()) {
            return;
        }

        if ($value === null) {
            PlatformSetting::query()->where('key', $key)->delete();

            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    private function normalizeUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function source(string $key, string $envKey): string
    {
        if ($this->getStored($key) !== null) {
            return 'database';
        }

        $env = env($envKey);

        if (is_string($env) && trim($env) !== '') {
            return 'env';
        }

        return 'none';
    }

    private function sourceWithConfig(string $key, string $configKey): string
    {
        if ($this->getStored($key) !== null) {
            return 'database';
        }

        if (filled(config($configKey))) {
            return 'env';
        }

        return 'none';
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
