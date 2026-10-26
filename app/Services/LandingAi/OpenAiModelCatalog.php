<?php

namespace App\Services\LandingAi;

use App\Services\LandingSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Text models the store landing generator can use, newest and strongest first.
 * The live list comes from GET /v1/models with the hub key; FALLBACK_MODELS is
 * used when OpenAI cannot be reached or no key is saved.
 */
class OpenAiModelCatalog
{
    private const CACHE_KEY = 'landing_ai.openai_models.v1';

    /**
     * @var list<string>
     */
    public const FALLBACK_MODELS = [
        'gpt-5.1',
        'gpt-5',
        'gpt-5-mini',
        'gpt-4.1',
        'gpt-4.1-mini',
        'gpt-4o',
        'gpt-4o-mini',
    ];

    /**
     * Ids containing any of these are not general text models, or are too slow /
     * expensive for a one-click page (pro, deep research).
     *
     * @var list<string>
     */
    private const EXCLUDE = [
        'audio',
        'realtime',
        'transcribe',
        'tts',
        'image',
        'embedding',
        'search',
        'instruct',
        'codex',
        'computer-use',
        'moderation',
        'deep-research',
        'chat-latest',
        'preview',
        '-pro',
        'oss',
    ];

    public function __construct(
        private LandingSettingsService $settings,
    ) {}

    /**
     * @return array{models: list<string>, best: string, source: string, fetched_at: string|null, error: string|null}
     */
    public function catalog(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (! is_array($cached) || ! isset($cached['models']) || ! is_array($cached['models'])) {
            $cached = $this->fetch();
            $ttl = $cached['source'] === 'live'
                ? (int) config('landing_ai.models_cache_seconds', 43200)
                : (int) config('landing_ai.models_failure_cache_seconds', 600);
            Cache::put(self::CACHE_KEY, $cached, max(60, $ttl));
        }

        return [
            'models' => $cached['models'],
            'best' => self::best($cached['models']),
            'source' => (string) ($cached['source'] ?? 'fallback'),
            'fetched_at' => $cached['fetched_at'] ?? null,
            'error' => $cached['error'] ?? null,
        ];
    }

    public function bestModel(): string
    {
        return $this->catalog()['best'];
    }

    /**
     * Saved Settings choice, else the best model on the account.
     */
    public function effectiveModel(): string
    {
        $saved = trim((string) $this->settings->openaiLandingModel());

        return $saved !== '' ? $saved : $this->bestModel();
    }

    /**
     * Ids an admin may save (live list plus the built-in list).
     *
     * @return list<string>
     */
    public function selectableModels(): array
    {
        return array_values(array_unique([
            ...$this->catalog()['models'],
            ...self::FALLBACK_MODELS,
        ]));
    }

    /**
     * Select options for Settings → AI. The empty value means "always use the best".
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(bool $refresh = false): array
    {
        $catalog = $this->catalog($refresh);
        $options = [[
            'value' => '',
            'label' => 'Auto — '.$catalog['best'].' (best available)',
        ]];

        foreach ($catalog['models'] as $id) {
            $options[] = ['value' => $id, 'label' => $id.self::hint($id, $catalog['best'])];
        }

        return $options;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public static function filter(array $ids): array
    {
        $ids = array_values(array_unique(array_map(
            fn ($id) => strtolower(trim((string) $id)),
            $ids,
        )));
        $aliases = array_flip($ids);
        $out = [];

        foreach ($ids as $id) {
            if (! self::isTextModel($id)) {
                continue;
            }
            // Dated snapshots clutter the list when the alias exists (gpt-4.1-2025-04-14).
            if (preg_match('/^(.+)-\d{4}-\d{2}-\d{2}$/', $id, $m) && isset($aliases[$m[1]])) {
                continue;
            }
            $out[] = $id;
        }

        return self::sort($out);
    }

    public static function isTextModel(string $id): bool
    {
        $id = strtolower(trim($id));

        if (! preg_match('/^(gpt-\d+(\.\d+)?o?|o[3-9])(-|$)/', $id)) {
            return false;
        }

        // gpt-3.5 / classic gpt-4 have no strict structured output.
        if (str_starts_with($id, 'gpt-3') || preg_match('/^gpt-4(-|$)/', $id)) {
            return false;
        }

        foreach (self::EXCLUDE as $needle) {
            if (str_contains($id, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reasoning models reject temperature and accept reasoning.effort.
     */
    public static function isReasoning(string $id): bool
    {
        $rank = self::rank($id);

        return $rank['family'] === 1 || ($rank['family'] === 2 && $rank['major'] >= 5);
    }

    public static function maxOutputTokens(string $id): int
    {
        return str_starts_with(strtolower($id), 'gpt-4o') ? 16000 : 32000;
    }

    /**
     * @param  list<string>  $models
     */
    public static function best(array $models): string
    {
        $sorted = self::sort($models);

        foreach ($sorted as $id) {
            $rank = self::rank($id);
            if ($rank['family'] === 2 && $rank['tier'] === 3 && $rank['dated'] === 0) {
                return $id;
            }
        }

        return $sorted[0] ?? self::FALLBACK_MODELS[0];
    }

    /**
     * @param  list<string>  $models
     * @return list<string>
     */
    public static function sort(array $models): array
    {
        $models = array_values(array_unique($models));

        usort($models, function (string $a, string $b): int {
            $ra = self::rank($a);
            $rb = self::rank($b);

            foreach (['family', 'major', 'minor', 'omni', 'tier'] as $part) {
                if ($ra[$part] !== $rb[$part]) {
                    return $rb[$part] <=> $ra[$part];
                }
            }
            if ($ra['dated'] !== $rb['dated']) {
                return $ra['dated'] <=> $rb['dated'];
            }

            return strcmp($a, $b);
        });

        return $models;
    }

    /**
     * family 2 = gpt-*, 1 = o-series. tier 3 = flagship, 2 = mini, 1 = nano.
     *
     * @return array{family: int, major: int, minor: int, omni: int, tier: int, dated: int}
     */
    public static function rank(string $id): array
    {
        $id = strtolower($id);
        $family = 0;
        $major = 0;
        $minor = 0;
        $omni = 0;

        if (preg_match('/^gpt-(\d+)(?:\.(\d+))?(o)?/', $id, $m)) {
            $family = 2;
            $major = (int) $m[1];
            $minor = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $omni = ! empty($m[3]) ? 1 : 0;
        } elseif (preg_match('/^o(\d+)/', $id, $m)) {
            $family = 1;
            $major = (int) $m[1];
        }

        $tier = str_contains($id, 'nano') ? 1 : (str_contains($id, 'mini') ? 2 : 3);

        return [
            'family' => $family,
            'major' => $major,
            'minor' => $minor,
            'omni' => $omni,
            'tier' => $tier,
            'dated' => preg_match('/-\d{4}-\d{2}-\d{2}$/', $id) ? 1 : 0,
        ];
    }

    private static function hint(string $id, string $best): string
    {
        if ($id === $best) {
            return ' — best quality';
        }

        return match (self::rank($id)['tier']) {
            1 => ' — fastest, lowest cost',
            2 => ' — faster, lower cost',
            default => '',
        };
    }

    /**
     * @return array{models: list<string>, source: string, fetched_at: string|null, error: string|null}
     */
    private function fetch(): array
    {
        $fallback = [
            'models' => self::sort(self::FALLBACK_MODELS),
            'source' => 'fallback',
            'fetched_at' => null,
            'error' => null,
        ];

        $key = $this->settings->openaiApiKey();
        if (! filled($key)) {
            return [...$fallback, 'error' => 'No OpenAI API key saved yet.'];
        }

        try {
            $response = Http::withToken((string) $key)
                ->acceptJson()
                ->timeout(8)
                ->connectTimeout(5)
                ->get('https://api.openai.com/v1/models');
        } catch (\Throwable) {
            return [...$fallback, 'error' => 'Could not reach OpenAI. Showing the built-in list.'];
        }

        if (! $response->successful()) {
            $error = $response->status() === 401
                ? 'OpenAI rejected the API key.'
                : 'OpenAI answered HTTP '.$response->status().'. Showing the built-in list.';

            return [...$fallback, 'error' => $error];
        }

        $ids = collect($response->json('data', []))
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values()
            ->all();

        $models = self::filter($ids);
        if ($models === []) {
            return [...$fallback, 'error' => 'OpenAI listed no text models for this key.'];
        }

        return [
            'models' => $models,
            'source' => 'live',
            'fetched_at' => now()->toIso8601String(),
            'error' => null,
        ];
    }
}
