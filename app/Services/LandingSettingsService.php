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

    public const META_PIXEL_ID_KEY = 'landing.meta_pixel_id';

    public const OPENAI_API_KEY_KEY = 'landing.openai_api_key';

    public const OPENAI_BLOG_MODEL_KEY = 'landing.openai_blog_model';

    public const OPENAI_IMAGE_MODEL_KEY = 'landing.openai_image_model';

    public const BLOG_AI_DAILY_TOKEN_CAP_KEY = 'landing.blog_ai_daily_token_cap';

    /**
     * @var list<string>
     */
    public const BLOG_MODELS = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4.1',
        'gpt-4.1-mini',
        'gpt-4.1-nano',
        'o3-mini',
        'o4-mini',
    ];

    /**
     * @var list<string>
     */
    public const IMAGE_MODELS = [
        'gpt-image-1',
        'dall-e-3',
        'dall-e-2',
    ];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'app_download_url' => $this->appDownloadUrl(),
            'play_store_url' => $this->playStoreUrl(),
            'plugin_download_url' => $this->pluginDownloadUrl(),
            'app_download_url_source' => $this->sourceWithConfig(self::APP_DOWNLOAD_URL_KEY, 'landing.app_download_url'),
            'play_store_url_source' => $this->sourceWithConfig(self::PLAY_STORE_URL_KEY, 'landing.play_store_url'),
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
            'meta_pixel_id' => $this->metaPixelId(),
            'meta_pixel_id_source' => $this->sourceWithConfig(self::META_PIXEL_ID_KEY, 'landing.meta_pixel_id'),
            'openai_api_key' => $this->openaiApiKey(),
            'openai_blog_model' => $this->openaiBlogModel(),
            'openai_image_model' => $this->openaiImageModel(),
            'openai_api_key_source' => $this->sourceWithConfig(self::OPENAI_API_KEY_KEY, 'landing.openai_api_key'),
            'openai_blog_model_source' => $this->sourceWithConfig(self::OPENAI_BLOG_MODEL_KEY, 'landing.openai_blog_model'),
            'openai_image_model_source' => $this->sourceWithConfig(self::OPENAI_IMAGE_MODEL_KEY, 'landing.openai_image_model'),
            'blog_ai_daily_token_cap' => $this->blogAiDailyTokenCap(),
            'blog_ai_daily_token_cap_source' => $this->sourceWithConfig(self::BLOG_AI_DAILY_TOKEN_CAP_KEY, 'blog_ai.daily_token_cap'),
            'blog_model_options' => self::BLOG_MODELS,
            'image_model_options' => self::IMAGE_MODELS,
        ];
    }

    public function appDownloadUrl(): ?string
    {
        return $this->resolve(self::APP_DOWNLOAD_URL_KEY, config('landing.app_download_url'));
    }

    public function playStoreUrl(): ?string
    {
        return $this->resolve(self::PLAY_STORE_URL_KEY, config('landing.play_store_url'));
    }

    public function pluginDownloadUrl(): ?string
    {
        $custom = $this->resolve(self::PLUGIN_DOWNLOAD_URL_KEY, config('landing.plugin_download_url'));

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

    public function metaPixelId(): ?string
    {
        return $this->resolve(self::META_PIXEL_ID_KEY, config('landing.meta_pixel_id'));
    }

    public function openaiApiKey(): ?string
    {
        return $this->resolve(self::OPENAI_API_KEY_KEY, config('landing.openai_api_key'));
    }

    public function openaiBlogModel(): ?string
    {
        return $this->resolve(self::OPENAI_BLOG_MODEL_KEY, config('landing.openai_blog_model'));
    }

    public function openaiImageModel(): ?string
    {
        return $this->resolve(self::OPENAI_IMAGE_MODEL_KEY, config('landing.openai_image_model'));
    }

    /**
     * Effective daily Blog AI token cap (DB override, else config/env default).
     */
    public function blogAiDailyTokenCap(): int
    {
        $stored = $this->getStored(self::BLOG_AI_DAILY_TOKEN_CAP_KEY);

        if ($stored !== null && is_numeric($stored)) {
            return max(1, (int) $stored);
        }

        return max(1, (int) config('blog_ai.daily_token_cap', 400000));
    }

    /**
     * @return array{meta_pixel_id: string|null, meta_pixel_id_source: string}
     */
    public function marketingTracking(): array
    {
        return [
            'meta_pixel_id' => $this->metaPixelId(),
            'meta_pixel_id_source' => $this->sourceWithConfig(self::META_PIXEL_ID_KEY, 'landing.meta_pixel_id'),
        ];
    }

    /**
     * @param  array<string, string|int|null>  $data
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
            'meta_pixel_id' => self::META_PIXEL_ID_KEY,
            'openai_api_key' => self::OPENAI_API_KEY_KEY,
            'openai_blog_model' => self::OPENAI_BLOG_MODEL_KEY,
            'openai_image_model' => self::OPENAI_IMAGE_MODEL_KEY,
        ] as $field => $key) {
            if (array_key_exists($field, $data)) {
                $this->put($key, $this->normalizeText($data[$field] ?? null));
            }
        }

        if (array_key_exists('blog_ai_daily_token_cap', $data)) {
            $this->put(self::BLOG_AI_DAILY_TOKEN_CAP_KEY, $this->normalizePositiveInt($data['blog_ai_daily_token_cap'] ?? null));
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

        $env = config('landing.plugin_download_url');

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

    private function normalizePositiveInt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || ! is_numeric($value)) {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int >= 1 ? (string) $int : null;
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
