<?php

namespace App\Services\ReturnAi;

use App\Models\PlatformSetting;
use App\Services\LandingAi\OpenAiModelCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Return desk AI settings for every store, saved in Settings → AI.
 * Stores read them through GET /api/return-ai/config and cannot change them.
 */
class ReturnAiSettings
{
    public const KEY = 'return_ai.settings';

    /** Set once the old gpt-4.1-mini default has been moved to "auto". */
    public const LEGACY_UPGRADE_KEY = 'return_ai.legacy_mini_upgraded';

    private const CACHE_KEY = 'return_ai.settings.v1';

    /** Previous hardcoded default. Upgraded once so the desk uses the best model. */
    private const LEGACY_DEFAULT_MODEL = 'gpt-4.1-mini';

    public const MODES = ['off', 'suggest', 'auto'];

    public function __construct(
        private OpenAiModelCatalog $models,
    ) {}

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

        $stored = is_array($stored) ? $stored : [];
        $clean = self::sanitize($stored, self::defaults());

        return $this->maybeUpgradeLegacyMini($stored, $clean);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $next = self::sanitize($input, $this->all());

        $this->write(self::KEY, $next);
        $this->write(self::LEGACY_UPGRADE_KEY, ['at' => now()->toIso8601String()]);
        Cache::forget(self::CACHE_KEY);

        return $next;
    }

    /**
     * Saved id, or the strongest text model on the hub key when the choice is auto.
     */
    public function model(): string
    {
        $saved = (string) $this->all()['model'];

        return $saved === 'auto' || $saved === '' ? $this->models->bestModel() : $saved;
    }

    /**
     * Ids an admin may save: auto, plus every text model on the key.
     *
     * @return list<string>
     */
    public function allowedModelIds(): array
    {
        return array_values(array_unique(['auto', ...$this->models->selectableModels()]));
    }

    /**
     * What a store needs to run its desk. No key, no prompt.
     *
     * @return array<string, mixed>
     */
    public function forStore(): array
    {
        $settings = $this->all();
        $saved = (string) $settings['model'];

        return [
            ...collect($settings)->except(['daily_review_limit'])->all(),
            'model_label' => $saved === 'auto' ? 'Best available' : $saved,
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
     * Settings → AI dropdown: best-available first, then every text model on the key.
     *
     * @return list<array{value: string, label: string, hint: string, recommended: bool}>
     */
    public function modelOptions(): array
    {
        $catalog = $this->models->catalog();
        $best = (string) $catalog['best'];
        $options = [[
            'value' => 'auto',
            'label' => 'Best available — '.$best.' (recommended)',
            'hint' => 'Strongest text model on this key. Best chance of a careful read that can hold the 95% bar.',
            'recommended' => true,
        ]];

        foreach ($catalog['models'] as $id) {
            $tier = OpenAiModelCatalog::rank($id)['tier'];
            $options[] = [
                'value' => $id,
                'label' => $id.($id === $best ? ' — best quality' : ''),
                'hint' => match ($tier) {
                    1 => 'Fastest, lowest cost — weaker on long Bangla notes',
                    2 => 'Faster and cheaper — more mistakes on mixed or long notes',
                    default => $id === $best
                        ? 'Strongest model on the key'
                        : 'Strong reader for a full note thread',
                },
                'recommended' => false,
            ];
        }

        return $options;
    }

    /**
     * Unknown values fall back to the base. Confidence never goes below 95.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $base
     * @param  list<string>|null  $allowed  When set, a model outside this list becomes auto.
     * @return array<string, mixed>
     */
    public static function sanitize(array $input, array $base, ?array $allowed = null): array
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
        $out['model'] = self::acceptedModel((string) $out['model'], $allowed);

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

    /**
     * The old default was gpt-4.1-mini. Move that saved value to auto once,
     * so an explicit later choice of mini is kept.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>
     */
    private function maybeUpgradeLegacyMini(array $stored, array $clean): array
    {
        if (($stored['model'] ?? null) !== self::LEGACY_DEFAULT_MODEL || $this->legacyUpgradeDone()) {
            return $clean;
        }

        $clean['model'] = 'auto';
        $this->write(self::KEY, $clean);
        $this->write(self::LEGACY_UPGRADE_KEY, ['at' => now()->toIso8601String()]);
        Cache::forget(self::CACHE_KEY);

        return $clean;
    }

    private function legacyUpgradeDone(): bool
    {
        if (! $this->tableReady()) {
            return true;
        }

        return PlatformSetting::query()->where('key', self::LEGACY_UPGRADE_KEY)->exists();
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function write(string $key, array $value): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * @param  list<string>|null  $allowed
     */
    private static function acceptedModel(string $model, ?array $allowed): string
    {
        if ($model === '' || $model === 'auto') {
            return 'auto';
        }
        if (is_array($allowed)) {
            return in_array($model, $allowed, true) ? $model : 'auto';
        }

        return OpenAiModelCatalog::isTextModel($model) ? $model : 'auto';
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
