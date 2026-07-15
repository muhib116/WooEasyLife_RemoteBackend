<?php

namespace App\Services\BlogAi;

use Illuminate\Support\Str;

/**
 * Maps blog clusters → landing/SEO pages and detects cluster from text.
 */
class BlogLandingContextService
{
    /**
     * Resolve cluster automatically from seed + keywords + optional learning fallbacks.
     *
     * @param  list<string>  $keywords
     * @param  array<string, mixed>  $learning
     * @return array{
     *     cluster: string,
     *     source: string,
     *     detected: string,
     *     seed_topic: string,
     *     landing: array<string, mixed>
     * }
     */
    public function resolveCluster(
        ?string $explicitCluster,
        string $seedTopic,
        array $keywords = [],
        array $learning = [],
    ): array {
        $valid = array_keys(config('blog_ai.clusters', []));
        $explicit = trim((string) $explicitCluster);
        if ($explicit !== '' && ! in_array($explicit, $valid, true)) {
            $explicit = '';
        }

        $hay = trim(implode(' ', array_filter([
            $seedTopic,
            ...array_map(fn ($k) => (string) $k, $keywords),
        ])));

        $detected = $this->detectCluster($hay);
        $source = 'detected';

        // Explicit admin/UI pick wins (including "general"). Auto-detect only when blank.
        if ($explicit !== '') {
            $cluster = $explicit;
            $source = 'explicit';
        } elseif ($detected !== 'general') {
            $cluster = $detected;
            $source = 'keywords_seed';
        } else {
            $ideas = $learning['next_post_ideas'] ?? [];
            $recommended = $learning['recommended_clusters'] ?? [];
            if (is_array($ideas) && $ideas !== [] && is_array($ideas[0] ?? null)) {
                $cluster = (string) ($ideas[0]['cluster'] ?? 'fake_order');
                $source = 'learning_idea';
                if ($seedTopic === '') {
                    $seedTopic = (string) ($ideas[0]['seed_topic'] ?? $ideas[0]['suggested_title'] ?? '');
                }
            } elseif (is_array($recommended) && $recommended !== []) {
                $cluster = (string) $recommended[0];
                $source = 'learning_recommended';
            } else {
                $cluster = 'fake_order';
                $source = 'default';
            }
        }

        if (! in_array($cluster, $valid, true)) {
            $cluster = 'general';
        }

        return [
            'cluster' => $cluster,
            'source' => $source,
            'detected' => $detected,
            'seed_topic' => $seedTopic,
            'landing' => $this->forCluster($cluster),
        ];
    }

    public function detectCluster(string $text): string
    {
        $hay = mb_strtolower(trim($text));
        if ($hay === '') {
            return 'general';
        }

        $scores = [];
        foreach (config('blog_ai.cluster_detect_needles', []) as $cluster => $needles) {
            if (! is_array($needles)) {
                continue;
            }
            $score = 0;
            foreach ($needles as $needle) {
                $n = mb_strtolower(trim((string) $needle));
                if ($n !== '' && str_contains($hay, $n)) {
                    $score += mb_strlen($n) >= 6 ? 2 : 1;
                }
            }
            if ($score > 0) {
                $scores[$cluster] = $score;
            }
        }

        if ($scores === []) {
            return 'general';
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    /**
     * Compact landing/SEO page payload for a cluster.
     *
     * @return array<string, mixed>
     */
    public function forCluster(string $cluster): array
    {
        $map = config('blog_ai.cluster_landing.'.$cluster)
            ?? config('blog_ai.cluster_landing.general', []);

        $seoKeys = is_array($map['seo_pages'] ?? null) ? $map['seo_pages'] : [];
        $pages = [];
        foreach ($seoKeys as $key) {
            $page = $this->seoPageBrief((string) $key);
            if ($page !== null) {
                $pages[] = $page;
            }
        }

        $primary = $map['primary_path'] ?? ($pages[0]['path'] ?? null);
        $related = array_values(array_unique(array_filter([
            ...(is_array($map['related_paths'] ?? null) ? $map['related_paths'] : []),
            ...collect($pages)->pluck('path')->all(),
            '/pricing',
        ])));

        $mustLink = array_values(array_filter([
            is_string($primary) ? $primary : null,
            ...(is_array($map['must_link_paths'] ?? null) ? $map['must_link_paths'] : []),
        ]));

        return [
            'cluster' => $cluster,
            'primary_path' => is_string($primary) ? $primary : null,
            'related_paths' => $related,
            'pages' => $pages,
            'must_link_paths' => $mustLink !== [] ? $mustLink : array_values(array_filter([$primary])),
            'claims' => is_array($map['claims'] ?? null) ? array_values($map['claims']) : [],
            'angle_hint' => (string) ($map['angle_hint'] ?? config('blog_ai.clusters.'.$cluster, $cluster)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function seoPageBrief(string $seoKey): ?array
    {
        $page = config('seo.pages.'.$seoKey);
        if (! is_array($page) || $page === []) {
            return null;
        }

        $faqs = collect($page['faqs'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['q'] ?? null))
            ->take(4)
            ->map(fn (array $row) => [
                'q' => (string) $row['q'],
                'a' => (string) ($row['a'] ?? ''),
            ])
            ->values()
            ->all();

        return [
            'key' => $seoKey,
            'path' => (string) ($page['canonical_path'] ?? '/'),
            'title' => (string) ($page['title'] ?? ''),
            'h1' => (string) ($page['prerender_h1'] ?? $page['title'] ?? ''),
            'description' => (string) ($page['description'] ?? ''),
            'lead' => (string) ($page['prerender_lead'] ?? ''),
            'faqs' => $faqs,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        $keys = collect(config('blog_ai.cluster_landing', []))
            ->pluck('seo_pages')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        $out = [];
        foreach ($keys as $key) {
            $brief = $this->seoPageBrief((string) $key);
            if ($brief !== null) {
                $out[] = [
                    'key' => $brief['key'],
                    'path' => $brief['path'],
                    'h1' => $brief['h1'],
                    'description' => Str::limit($brief['description'], 220, '…'),
                ];
            }
        }

        return $out;
    }
}
