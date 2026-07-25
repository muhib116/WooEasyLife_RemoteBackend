<?php

namespace App\Services\Seo;

/**
 * Read-only accessor for config/seo_keyword_inventory.php
 * Used by SEO mentor plans and Blog AI (seeds, LP head-term guard, topic picker).
 */
class SeoKeywordInventory
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $status = null): array
    {
        $entries = config('seo_keyword_inventory.entries', []);
        if (! is_array($entries)) {
            return [];
        }

        $rows = array_values(array_filter($entries, fn ($row) => is_array($row)));

        if ($status === null || $status === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row) => ($row['status'] ?? '') === $status
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCluster(string $cluster, ?string $type = null, ?string $status = null): array
    {
        $cluster = trim($cluster);

        return array_values(array_filter($this->all($status), function (array $row) use ($cluster, $type) {
            if ($cluster !== '' && ($row['cluster'] ?? '') !== $cluster) {
                return false;
            }
            if ($type !== null && $type !== '' && ($row['type'] ?? '') !== $type) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Seed strings for Google Suggest / Blog AI keyword boxes.
     *
     * @return list<string>
     */
    public function seedQueriesForCluster(string $cluster, int $limit = 16): array
    {
        $out = [];
        foreach ($this->forCluster($cluster) as $row) {
            $primary = trim((string) ($row['primary'] ?? ''));
            if ($primary !== '') {
                $out[] = $primary;
            }
            foreach ($row['secondary'] ?? [] as $sec) {
                $sec = trim((string) $sec);
                if ($sec !== '') {
                    $out[] = $sec;
                }
            }
        }

        return collect($out)->unique()->take($limit)->values()->all();
    }

    /**
     * Short head terms owned by live money/tool/pillar pages — blogs must not use as focus_keyword.
     *
     * @return list<string>
     */
    public function reservedHeadTermsForCluster(string $cluster): array
    {
        $terms = [];
        foreach ($this->forCluster($cluster, null, 'live') as $row) {
            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, ['money', 'tool', 'pillar'], true)) {
                continue;
            }
            $primary = trim((string) ($row['primary'] ?? ''));
            if ($primary !== '' && $this->isShortHead($primary)) {
                $terms[] = $primary;
            }
            foreach ($row['secondary'] ?? [] as $sec) {
                $sec = trim((string) $sec);
                if ($sec !== '' && $this->isShortHead($sec)) {
                    $terms[] = $sec;
                }
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * Planned blog topics for Smart One-Click / cold-start picker.
     *
     * @return list<array{seed_topic: string, keyword: string, cluster: string, target_slug: string|null, reason: string, article_type: string|null, cta: string|null}>
     */
    public function plannedBlogTopics(?string $cluster = null, int $limit = 20): array
    {
        $rows = $cluster
            ? $this->forCluster($cluster, 'planned_blog', 'planned')
            : array_values(array_filter(
                $this->all('planned'),
                fn (array $row) => ($row['type'] ?? '') === 'planned_blog'
            ));

        $out = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            $primary = trim((string) ($row['primary'] ?? ''));
            if ($primary === '') {
                continue;
            }
            $out[] = [
                'seed_topic' => $primary,
                'keyword' => $primary,
                'cluster' => (string) ($row['cluster'] ?? 'general'),
                'target_slug' => filled($row['slug'] ?? null) ? (string) $row['slug'] : null,
                'reason' => 'seo_keyword_inventory',
                'article_type' => filled($row['article_type'] ?? null) ? (string) $row['article_type'] : null,
                'cta' => filled($row['cta'] ?? null) ? (string) $row['cta'] : null,
            ];
        }

        return $out;
    }

    /**
     * Planned FAQ paths (for mentor plans / future sitemap — not live internal links yet).
     *
     * @return list<array<string, mixed>>
     */
    public function plannedFaqs(?string $cluster = null): array
    {
        if ($cluster) {
            return $this->forCluster($cluster, 'planned_faq', 'planned');
        }

        return array_values(array_filter(
            $this->all('planned'),
            fn (array $row) => ($row['type'] ?? '') === 'planned_faq'
        ));
    }

    /**
     * Live money/tool paths with keyword anchors for InternalLinkCatalog enrichment.
     *
     * @return list<array{path: string, title: string, type: string, anchor_hints: list<string>}>
     */
    public function liveLinkTargets(): array
    {
        $out = [];
        foreach ($this->all('live') as $row) {
            $path = trim((string) ($row['path'] ?? ''));
            if ($path === '' || ! str_starts_with($path, '/')) {
                continue;
            }
            if (str_starts_with($path, '/blog/') || str_starts_with($path, '/faq/')) {
                continue;
            }
            $primary = trim((string) ($row['primary'] ?? ''));
            $hints = array_values(array_filter(array_merge(
                $primary !== '' ? [$primary] : [],
                array_map('strval', $row['secondary'] ?? [])
            )));
            $out[] = [
                'path' => $path,
                'title' => $primary !== '' ? $primary : $path,
                'type' => 'seo_inventory',
                'anchor_hints' => array_slice($hints, 0, 6),
            ];
        }

        // Dedupe by path (first wins).
        $seen = [];
        $unique = [];
        foreach ($out as $row) {
            if (isset($seen[$row['path']])) {
                continue;
            }
            $seen[$row['path']] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * Compact block for AI prompts.
     */
    public function toPromptBlockForCluster(string $cluster): string
    {
        $payload = [
            'live_money_paths' => collect($this->forCluster($cluster, null, 'live'))
                ->filter(fn ($r) => in_array($r['type'] ?? '', ['money', 'tool', 'pillar'], true))
                ->map(fn ($r) => [
                    'path' => $r['path'] ?? null,
                    'primary' => $r['primary'] ?? null,
                    'cta' => $r['cta'] ?? null,
                ])
                ->values()
                ->all(),
            'reserved_head_terms' => $this->reservedHeadTermsForCluster($cluster),
            'planned_blog_topics' => collect($this->plannedBlogTopics($cluster, 8))
                ->map(fn ($r) => [
                    'primary' => $r['keyword'],
                    'slug' => $r['target_slug'],
                    'cta' => $r['cta'],
                ])
                ->all(),
            'planned_faqs' => collect($this->plannedFaqs($cluster))
                ->map(fn ($r) => [
                    'path' => $r['path'] ?? null,
                    'primary' => $r['primary'] ?? null,
                    'cta' => $r['cta'] ?? null,
                ])
                ->values()
                ->all(),
            'rules' => [
                'Do not use reserved_head_terms as blog focus_keyword (those belong to money pages).',
                'Prefer long-tail planned_blog_topics or GSC opportunities.',
                'Soft-link live_money_paths; never invent features.',
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function isShortHead(string $term): bool
    {
        $term = trim($term);
        if ($term === '' || mb_strlen($term) > 42) {
            return false;
        }
        // Bangla phrases often have no spaces counted by str_word_count — allow by length.
        $words = str_word_count($term);

        return $words <= 4 || mb_strlen($term) <= 28;
    }
}
