<?php

namespace App\WiseAi\Language;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Platform-wide Wise LLM Language Specialist settings (not Blog Landing key).
 */
class LlmLanguageConfig
{
    public const ENABLED_KEY = 'wise.llm.enabled';

    public const API_KEY_KEY = 'wise.llm.api_key';

    public const MODEL_KEY = 'wise.llm.model';

    public const DEFAULT_MODEL = 'gpt-4o-mini';

    /** @var list<string> */
    public const ALLOWED_MODELS = [
        'gpt-4o-mini',
        'gpt-4o',
        'gpt-4.1-mini',
        'gpt-4.1',
        'gpt-5-mini',
        'gpt-5-nano',
    ];

    /**
     * Admin toggle — default ON (fail-open at runtime if no key).
     */
    public function enabled(): bool
    {
        $stored = $this->getStored(self::ENABLED_KEY);
        if ($stored === null) {
            return true;
        }

        if (is_bool($stored)) {
            return $stored;
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
    }

    public function model(): string
    {
        $stored = $this->getStored(self::MODEL_KEY);
        $model = is_string($stored) && $stored !== '' ? $stored : self::DEFAULT_MODEL;
        if (! in_array($model, self::ALLOWED_MODELS, true)) {
            return self::DEFAULT_MODEL;
        }

        return $model;
    }

    public function apiKey(): ?string
    {
        $stored = $this->getStored(self::API_KEY_KEY);
        if (is_string($stored) && $stored !== '') {
            try {
                $plain = Crypt::decryptString($stored);
                if (is_string($plain) && trim($plain) !== '') {
                    return trim($plain);
                }
            } catch (Throwable) {
                // Legacy plaintext fallthrough.
                if (trim($stored) !== '') {
                    return trim($stored);
                }
            }
        }

        $env = trim((string) env('WISE_OPENAI_API_KEY', ''));

        return $env !== '' ? $env : null;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * Safe payload for Config UI (never returns plain key).
     *
     * @return array{enabled: bool, model: string, api_key_set: bool, api_key_hint: string, allowed_models: list<string>}
     */
    public function forAdmin(): array
    {
        $key = $this->apiKey();
        $hint = '';
        if ($key !== null) {
            $hint = strlen($key) > 12
                ? substr($key, 0, 7).'…'.substr($key, -4)
                : '••••';
        }

        return [
            'enabled' => $this->enabled(),
            'model' => $this->model(),
            'api_key_set' => $key !== null,
            'api_key_hint' => $hint,
            'allowed_models' => self::ALLOWED_MODELS,
        ];
    }

    /**
     * @param  array{enabled?: bool, model?: string, api_key?: string|null}  $input
     */
    public function update(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            $this->put(self::ENABLED_KEY, (bool) $input['enabled']);
        }

        if (array_key_exists('model', $input)) {
            $model = (string) ($input['model'] ?? self::DEFAULT_MODEL);
            if (! in_array($model, self::ALLOWED_MODELS, true)) {
                $model = self::DEFAULT_MODEL;
            }
            $this->put(self::MODEL_KEY, $model);
        }

        if (array_key_exists('api_key', $input)) {
            $raw = trim((string) ($input['api_key'] ?? ''));
            if ($raw === '') {
                // Empty string = leave existing key alone.
            } elseif ($raw === '__clear__') {
                $this->put(self::API_KEY_KEY, null);
            } else {
                $this->put(self::API_KEY_KEY, Crypt::encryptString($raw));
            }
        }
    }

    private function getStored(string $key): mixed
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', $key)->first();

        return $row?->value;
    }

    private function put(string $key, mixed $value): void
    {
        if (! Schema::hasTable('platform_settings')) {
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
}
