<?php

namespace App\Services\BlogAi;

use App\Models\BlogCluster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Resolves blog topic clusters from DB (admin-editable) with config fallback.
 */
class BlogClusterCatalog
{
    private const CACHE_KEY = 'blog_ai.cluster_catalog.v2';

    /**
     * @return array<string, string> key => label
     */
    public function labels(bool $activeOnly = true): array
    {
        $out = [];
        foreach (config('blog_ai.clusters', []) as $key => $label) {
            $out[(string) $key] = (string) $label;
        }
        foreach ($this->rows($activeOnly) as $row) {
            $out[$row['key']] = $row['label'];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function keys(bool $activeOnly = true): array
    {
        return array_keys($this->labels($activeOnly));
    }

    public function label(string $key): string
    {
        $key = trim($key);
        $labels = $this->labels(false);

        return $labels[$key] ?? (string) config('blog_ai.clusters.'.$key, $key);
    }

    /**
     * @return list<string>
     */
    public function seedQueries(string $key): array
    {
        $configSeeds = config('blog_ai.cluster_seed_queries.'.$key, []);
        $configSeeds = is_array($configSeeds)
            ? array_values(array_filter(array_map('strval', $configSeeds)))
            : [];

        $row = $this->row($key);
        $dbSeeds = is_array($row)
            ? array_values(array_filter(array_map('strval', $row['seed_queries'] ?? [])))
            : [];

        $base = array_values(array_unique(array_merge($configSeeds, $dbSeeds)));

        // Merge curated SEO inventory seeds (keywords/slugs roadmap) without dropping DB/config seeds.
        try {
            $inventory = app(\App\Services\Seo\SeoKeywordInventory::class)->seedQueriesForCluster($key, 12);
        } catch (\Throwable) {
            $inventory = [];
        }

        return array_values(array_unique(array_merge($base, $inventory)));
    }

    /**
     * Landing map for a cluster.
     * Config forces product-truth paths/claims; admin angle_hint and other extras stay from DB.
     *
     * @return array<string, mixed>
     */
    public function landing(string $key): array
    {
        $config = config('blog_ai.cluster_landing.'.$key, []);
        $config = is_array($config) ? $config : [];

        $row = $this->row($key);
        $db = is_array($row['landing_json'] ?? null) ? $row['landing_json'] : [];

        if ($config === [] && $db === []) {
            $general = config('blog_ai.cluster_landing.general', []);

            return is_array($general) ? $general : [];
        }

        $merged = array_replace_recursive($config, $db);

        // Deployed product truth — always refresh these from config when present.
        foreach (['primary_path', 'claims', 'must_link_paths', 'related_paths', 'seo_pages'] as $field) {
            if (array_key_exists($field, $config)) {
                $merged[$field] = $config[$field];
            }
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    public function detectNeedles(string $key): array
    {
        $config = config('blog_ai.cluster_detect_needles.'.$key, []);
        $config = is_array($config) ? array_values(array_filter(array_map('strval', $config))) : [];

        $row = $this->row($key);
        $db = is_array($row['detect_needles'] ?? null)
            ? array_values(array_filter(array_map('strval', $row['detect_needles'])))
            : [];

        return array_values(array_unique(array_merge($config, $db)));
    }

    /**
     * @return array<string, list<string>>
     */
    public function detectNeedlesMap(bool $activeOnly = true): array
    {
        $out = [];
        foreach (array_keys(config('blog_ai.cluster_detect_needles', []) ?: []) as $key) {
            $needles = $this->detectNeedles((string) $key);
            if ($needles !== []) {
                $out[(string) $key] = $needles;
            }
        }
        foreach ($this->rows($activeOnly) as $row) {
            $needles = $this->detectNeedles($row['key']);
            if ($needles !== []) {
                $out[$row['key']] = $needles;
            }
        }

        if ($out === []) {
            $cfg = config('blog_ai.cluster_detect_needles', []);

            return is_array($cfg) ? $cfg : [];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForAdmin(): array
    {
        return array_map(function (array $row) {
            $seeds = array_values(array_filter(array_map('strval', $row['seed_queries'] ?? [])));
            $landing = is_array($row['landing_json'] ?? null) ? $row['landing_json'] : [];
            $needles = array_values(array_filter(array_map('strval', $row['detect_needles'] ?? [])));

            return [
                'id' => $row['id'],
                'key' => $row['key'],
                'label' => $row['label'],
                'seed_queries' => $seeds,
                'landing' => [
                    'primary_path' => (string) ($landing['primary_path'] ?? ''),
                    'related_paths_text' => implode("\n", array_values(array_filter(
                        is_array($landing['related_paths'] ?? null) ? $landing['related_paths'] : []
                    ))),
                    'must_link_paths_text' => implode("\n", array_values(array_filter(
                        is_array($landing['must_link_paths'] ?? null) ? $landing['must_link_paths'] : []
                    ))),
                    'claims_text' => implode("\n", array_values(array_filter(
                        is_array($landing['claims'] ?? null) ? $landing['claims'] : []
                    ))),
                    'angle_hint' => (string) ($landing['angle_hint'] ?? ''),
                    'seo_pages_text' => implode("\n", array_values(array_filter(
                        is_array($landing['seo_pages'] ?? null) ? $landing['seo_pages'] : []
                    ))),
                ],
                'detect_needles' => $needles,
                'detect_needles_text' => implode("\n", $needles),
                'sort_order' => (int) $row['sort_order'],
                'is_active' => (bool) $row['is_active'],
                'seed_count' => count($seeds),
            ];
        }, $this->rows(false));
    }

    public function inRule(bool $activeOnly = true): \Illuminate\Validation\Rules\In
    {
        $keys = $this->keys($activeOnly);
        if ($keys === []) {
            $keys = ['general'];
        }

        return Rule::in($keys);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('blog_ai.cluster_catalog.v1');
    }

    public function normalizeKey(string $raw): string
    {
        $key = Str::slug(trim($raw), '_');
        $key = preg_replace('/_+/', '_', $key) ?? $key;
        $key = trim($key, '_');

        return Str::limit($key, 64, '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $key): ?array
    {
        $key = trim($key);
        foreach ($this->rows(false) as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(bool $activeOnly): array
    {
        $all = Cache::remember(self::CACHE_KEY, 300, function () {
            if (! Schema::hasTable('blog_clusters')) {
                return $this->configFallbackRows();
            }

            $dbRows = BlogCluster::query()->ordered()->get();
            if ($dbRows->isEmpty()) {
                return $this->configFallbackRows();
            }

            return $dbRows->map(fn (BlogCluster $row) => [
                'id' => $row->id,
                'key' => $row->key,
                'label' => $row->label,
                'seed_queries' => is_array($row->seed_queries) ? $row->seed_queries : [],
                'landing_json' => is_array($row->landing_json) ? $row->landing_json : [],
                'detect_needles' => is_array($row->detect_needles) ? $row->detect_needles : [],
                'sort_order' => (int) $row->sort_order,
                'is_active' => (bool) $row->is_active,
            ])->all();
        });

        if (! $activeOnly) {
            return $all;
        }

        return array_values(array_filter($all, fn (array $row) => ($row['is_active'] ?? true) === true));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function configFallbackRows(): array
    {
        $labels = config('blog_ai.clusters', []);
        if (! is_array($labels)) {
            return [];
        }

        $out = [];
        $i = 0;
        foreach ($labels as $key => $label) {
            $seeds = config('blog_ai.cluster_seed_queries.'.$key, []);
            $landing = config('blog_ai.cluster_landing.'.$key, []);
            $needles = config('blog_ai.cluster_detect_needles.'.$key, []);
            $out[] = [
                'id' => null,
                'key' => (string) $key,
                'label' => (string) $label,
                'seed_queries' => is_array($seeds) ? array_values($seeds) : [],
                'landing_json' => is_array($landing) ? $landing : [],
                'detect_needles' => is_array($needles) ? array_values($needles) : [],
                'sort_order' => (++$i) * 10,
                'is_active' => true,
            ];
        }

        return $out;
    }
}
