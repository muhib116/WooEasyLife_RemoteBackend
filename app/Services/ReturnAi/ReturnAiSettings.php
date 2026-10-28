<?php

namespace App\Services\ReturnAi;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Return desk AI settings for every store, saved in Settings → AI.
 * Stores read them through GET /api/return-ai/config and cannot change them.
 */
class ReturnAiSettings
{
    public const KEY = 'return_ai.settings';

    private const CACHE_KEY = 'return_ai.settings.v1';

    public const MODES = ['off', 'suggest', 'auto'];

    /**
     * @return array{
     *     mode: string, auto_note: bool, auto_resend: bool, auto_cancel: bool, model: string,
     *     min_confidence: int, daily_auto_limit: int, daily_review_limit: int, background_review: bool
     * }
     */
    public function all(): array
    {
        $stored = Cache::remember(
            self::CACHE_KEY,
            (int) config('return_ai.settings_cache_seconds', 300),
            function () {
                if (! $this->tableReady()) {
                    return [];
                }
                $value = PlatformSetting::query()->where('key', self::KEY)->value('value');

                return is_array($value) ? $value : [];
            },
        );

        return self::sanitize(is_array($stored) ? $stored : [], self::defaults());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $next = self::sanitize($input, $this->all());

        if ($this->tableReady()) {
            PlatformSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => $next]);
        }
        Cache::forget(self::CACHE_KEY);

        return $next;
    }

    public function model(): string
    {
        return $this->all()['model'];
    }

    /**
     * What a store needs to run its desk. No key, no prompt.
     *
     * @return array<string, mixed>
     */
    public function forStore(): array
    {
        $settings = $this->all();
        $models = self::models();

        return [
            ...collect($settings)->except(['daily_review_limit'])->all(),
            'model_label' => $models[$settings['model']]['label'] ?? $settings['model'],
            'daily_review_limit' => $settings['daily_review_limit'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return (array) config('return_ai.defaults', []);
    }

    /**
     * @return array<string, array{label: string, hint: string, recommended?: bool}>
     */
    public static function models(): array
    {
        return (array) config('return_ai.models', []);
    }

    /**
     * @return list<array{value: string, label: string, hint: string, recommended: bool}>
     */
    public static function modelOptions(): array
    {
        $out = [];
        foreach (self::models() as $id => $model) {
            $recommended = ! empty($model['recommended']);
            $out[] = [
                'value' => $id,
                'label' => $model['label'].($recommended ? ' (recommended)' : ''),
                'hint' => $model['hint'],
                'recommended' => $recommended,
            ];
        }

        return $out;
    }

    public static function defaultModel(): string
    {
        foreach (self::models() as $id => $model) {
            if (! empty($model['recommended'])) {
                return $id;
            }
        }

        return (string) (self::defaults()['model'] ?? 'gpt-4.1-mini');
    }

    /**
     * Unknown values fall back to the base. Confidence never goes below 95.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function sanitize(array $input, array $base): array
    {
        $out = array_merge(self::defaults(), $base);

        if (array_key_exists('mode', $input)) {
            $mode = strtolower(trim((string) $input['mode']));
            $out['mode'] = in_array($mode, self::MODES, true) ? $mode : 'suggest';
        }

        foreach (['auto_note', 'auto_resend', 'auto_cancel', 'background_review'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $out[$flag] = filter_var($input[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (array_key_exists('model', $input)) {
            $out['model'] = trim((string) $input['model']);
        }
        if (! array_key_exists((string) $out['model'], self::models())) {
            $out['model'] = self::defaultModel();
        }

        foreach ([
            'min_confidence' => [95, 99],
            'daily_auto_limit' => [1, 200],
            'daily_review_limit' => [10, 5000],
        ] as $field => [$min, $max]) {
            if (array_key_exists($field, $input) && is_numeric($input[$field])) {
                $out[$field] = (int) $input[$field];
            }
            $out[$field] = max($min, min($max, (int) $out[$field]));
        }

        $out['mode'] = in_array($out['mode'], self::MODES, true) ? $out['mode'] : 'suggest';
        foreach (['auto_note', 'auto_resend', 'auto_cancel', 'background_review'] as $flag) {
            $out[$flag] = (bool) $out[$flag];
        }

        return [
            'mode' => $out['mode'],
            'auto_note' => $out['auto_note'],
            'auto_resend' => $out['auto_resend'],
            'auto_cancel' => $out['auto_cancel'],
            'model' => $out['model'],
            'min_confidence' => $out['min_confidence'],
            'daily_auto_limit' => $out['daily_auto_limit'],
            'daily_review_limit' => $out['daily_review_limit'],
            'background_review' => $out['background_review'],
        ];
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
