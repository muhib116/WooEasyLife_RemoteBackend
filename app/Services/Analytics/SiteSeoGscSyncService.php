<?php

namespace App\Services\Analytics;

use App\Models\SiteGscPageMetric;
use App\Models\SiteGscQueryMetric;
use App\Services\BlogAi\BlogLearningService;
use App\Services\Seo\GoogleSearchConsoleClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SiteSeoGscSyncService
{
    public function __construct(
        private GoogleSearchConsoleClient $gsc,
        private BlogLearningService $blogLearning,
    ) {}

    /**
     * @return array{configured: bool, queries_synced: int, pages_synced: int, error?: string, skipped?: bool}
     */
    public function sync(): array
    {
        if (
            ! Schema::hasTable('site_gsc_query_metrics')
            || ! Schema::hasTable('site_gsc_page_metrics')
            || ! Schema::hasTable('site_gsc_query_metrics_staging')
            || ! Schema::hasTable('site_gsc_page_metrics_staging')
        ) {
            return [
                'configured' => $this->gsc->configured(),
                'queries_synced' => 0,
                'pages_synced' => 0,
                'skipped' => true,
                'error' => 'missing_table',
            ];
        }

        if (! $this->gsc->configured()) {
            return [
                'configured' => false,
                'queries_synced' => 0,
                'pages_synced' => 0,
                'skipped' => true,
            ];
        }

        $queries = $this->syncQueryMetrics();
        if (! empty($queries['error'])) {
            return [
                'configured' => true,
                'queries_synced' => 0,
                'pages_synced' => 0,
                'error' => $queries['error'],
                'skipped' => false,
            ];
        }

        $pages = $this->syncPageMetrics();

        return [
            'configured' => true,
            'queries_synced' => (int) ($queries['synced'] ?? 0),
            'pages_synced' => (int) ($pages['synced'] ?? 0),
            'error' => $pages['error'] ?? null,
            'skipped' => false,
        ];
    }

    /**
     * @return array{
     *     configured: bool,
     *     table_ready: bool,
     *     refreshed_at: string|null,
     *     top_keywords: list<array<string, mixed>>,
     *     opportunities: list<array<string, mixed>>,
     *     landing_pages: list<array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function seoPanel(?string $path = null, int $limit = 25): array
    {
        $configured = $this->gsc->configured();
        $tableReady = Schema::hasTable('site_gsc_query_metrics')
            && Schema::hasTable('site_gsc_page_metrics');

        if (! $tableReady) {
            return [
                'configured' => $configured,
                'table_ready' => false,
                'refreshed_at' => null,
                'top_keywords' => [],
                'opportunities' => [],
                'landing_pages' => [],
                'summary' => [],
            ];
        }

        $refreshed = SiteGscQueryMetric::query()->max('metrics_refreshed_at');

        $keywordQuery = SiteGscQueryMetric::query()
            ->select([
                'query',
                DB::raw('SUM(clicks_28d) as clicks_28d'),
                DB::raw('SUM(impressions_28d) as impressions_28d'),
                DB::raw('AVG(position_28d) as position_28d'),
                DB::raw('CASE WHEN SUM(impressions_28d) > 0 THEN SUM(clicks_28d) / SUM(impressions_28d) ELSE 0 END as ctr_28d'),
            ])
            ->groupBy('query')
            ->orderByDesc('impressions_28d')
            ->limit($limit);

        if ($path) {
            $keywordQuery->where('path', $path);
        }

        $topKeywords = $keywordQuery->get()->map(fn ($row) => [
            'query' => $row->query,
            'clicks_28d' => (int) $row->clicks_28d,
            'impressions_28d' => (int) $row->impressions_28d,
            'ctr_28d' => round((float) $row->ctr_28d, 4),
            'position_28d' => round((float) $row->position_28d, 2),
        ])->all();

        $oppQuery = SiteGscQueryMetric::query()
            ->whereIn('bucket', [
                SiteGscQueryMetric::BUCKET_STRIKING,
                SiteGscQueryMetric::BUCKET_FIX_CTR,
                SiteGscQueryMetric::BUCKET_DEFEND,
                SiteGscQueryMetric::BUCKET_BURIED,
                SiteGscQueryMetric::BUCKET_CANNIBALIZED,
            ])
            ->orderByDesc('opportunity_score')
            ->limit($limit);

        if ($path) {
            $oppQuery->where('path', $path);
        }

        $opportunities = $oppQuery->get()->map(fn (SiteGscQueryMetric $row) => [
            'query' => $row->query,
            'path' => $row->path,
            'page_url' => $row->page_url,
            'clicks_28d' => (int) $row->clicks_28d,
            'impressions_28d' => (int) $row->impressions_28d,
            'ctr_28d' => $row->ctr_28d,
            'position_28d' => $row->position_28d,
            'bucket' => $row->bucket,
            'bucket_label' => SiteGscQueryMetric::bucketLabel((string) $row->bucket),
            'opportunity_score' => (float) $row->opportunity_score,
            'improvement_hint' => $row->improvement_hint,
        ])->all();

        $pageQuery = SiteGscPageMetric::query()
            ->orderByDesc('impressions_28d')
            ->limit($limit);

        if ($path) {
            $pageQuery->where('path', $path);
        }

        $landingPages = $pageQuery->get()->map(fn (SiteGscPageMetric $row) => [
            'path' => $row->path,
            'page_url' => $row->page_url,
            'clicks_28d' => (int) $row->clicks_28d,
            'impressions_28d' => (int) $row->impressions_28d,
            'ctr_28d' => $row->ctr_28d,
            'position_28d' => $row->position_28d,
        ])->all();

        $summaryQuery = SiteGscQueryMetric::query();
        if ($path) {
            $summaryQuery->where('path', $path);
        }
        $summary = $summaryQuery
            ->select('bucket', DB::raw('COUNT(*) as total'))
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($n) => (int) $n)
            ->all();

        return [
            'configured' => $configured,
            'table_ready' => true,
            'refreshed_at' => $refreshed ? (string) $refreshed : null,
            'top_keywords' => $topKeywords,
            'opportunities' => $opportunities,
            'landing_pages' => $landingPages,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{synced: int, error?: string}
     */
    private function syncQueryMetrics(): array
    {
        $pageSize = 1000;
        $maxPages = 5;
        $rawRows = [];

        try {
            for ($page = 0; $page < $maxPages; $page++) {
                $payload = $this->gsc->searchAnalytics(array_filter([
                    'startDate' => now()->subDays(28)->toDateString(),
                    'endDate' => now()->subDay()->toDateString(),
                    'dimensions' => ['query', 'page'],
                    'rowLimit' => $pageSize,
                    'startRow' => $page * $pageSize,
                    'dimensionFilterGroups' => $this->countryFilterGroups(),
                ]));

                $batch = $payload['rows'] ?? [];
                if (! is_array($batch) || $batch === []) {
                    break;
                }

                foreach ($batch as $row) {
                    if (is_array($row)) {
                        $rawRows[] = $row;
                    }
                }

                if (count($batch) < $pageSize) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Site GSC query sync failed', ['message' => $e->getMessage()]);

            return ['synced' => 0, 'error' => $e->getMessage()];
        }

        if ($rawRows === []) {
            return ['synced' => 0];
        }

        $prepared = [];
        $queryPages = [];

        foreach ($rawRows as $row) {
            $keys = $row['keys'] ?? [];
            $query = trim((string) ($keys[0] ?? ''));
            $pageUrl = trim((string) ($keys[1] ?? ''));
            if ($query === '' || $pageUrl === '') {
                continue;
            }

            $path = $this->pathFromPageUrl($pageUrl);
            if ($path === null || ! app(SiteVisitorTracker::class)->isAllowedPath($path)) {
                continue;
            }

            $clicks = (int) ($row['clicks'] ?? 0);
            $impr = (int) ($row['impressions'] ?? 0);
            $ctr = isset($row['ctr']) ? round((float) $row['ctr'], 4) : ($impr > 0 ? round($clicks / $impr, 4) : null);
            $position = isset($row['position']) ? round((float) $row['position'], 2) : null;

            $prepared[] = [
                'query' => mb_substr($query, 0, 500),
                'page_url' => mb_substr($pageUrl, 0, 500),
                'path' => $path,
                'clicks_28d' => $clicks,
                'impressions_28d' => $impr,
                'ctr_28d' => $ctr,
                'position_28d' => $position,
            ];

            $queryKey = mb_strtolower($query);
            $queryPages[$queryKey] = $queryPages[$queryKey] ?? [];
            $queryPages[$queryKey][$pageUrl] = ($queryPages[$queryKey][$pageUrl] ?? 0) + $impr;
        }

        if ($prepared === []) {
            return ['synced' => 0];
        }

        $now = now();
        $rows = [];

        foreach ($prepared as $item) {
            $queryKey = mb_strtolower($item['query']);
            $pagesForQuery = $queryPages[$queryKey] ?? [];
            $isCannibalized = count($pagesForQuery) > 1;
            $topPage = null;
            if ($isCannibalized) {
                arsort($pagesForQuery);
                $topPage = array_key_first($pagesForQuery);
            }

            $classified = $this->blogLearning->classifyRankOpportunity(
                impressions: $item['impressions_28d'],
                clicks: $item['clicks_28d'],
                ctr: $item['ctr_28d'],
                position: $item['position_28d'],
                isCannibalizedSecondary: $isCannibalized && $topPage !== null && $item['page_url'] !== $topPage,
            );

            $rows[] = [
                'pair_hash' => hash('sha256', mb_strtolower($item['query']).'|'.$item['page_url']),
                'query' => $item['query'],
                'page_url' => $item['page_url'],
                'path' => $item['path'],
                'clicks_28d' => $item['clicks_28d'],
                'impressions_28d' => $item['impressions_28d'],
                'ctr_28d' => $item['ctr_28d'],
                'position_28d' => $item['position_28d'],
                'bucket' => $classified['bucket'],
                'opportunity_score' => $classified['score'],
                'improvement_hint' => $classified['hint'],
                'metrics_refreshed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('site_gsc_query_metrics_staging')->delete();
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('site_gsc_query_metrics_staging')->insert($chunk);
        }

        $this->swapMetricTables('site_gsc_query_metrics', 'site_gsc_query_metrics_staging');

        return ['synced' => count($rows)];
    }

    /**
     * @return array{synced: int, error?: string}
     */
    private function syncPageMetrics(): array
    {
        try {
            $payload = $this->gsc->searchAnalytics(array_filter([
                'startDate' => now()->subDays(28)->toDateString(),
                'endDate' => now()->subDay()->toDateString(),
                'dimensions' => ['page'],
                'rowLimit' => 1000,
                'dimensionFilterGroups' => $this->countryFilterGroups(),
            ]));
        } catch (\Throwable $e) {
            Log::warning('Site GSC page sync failed', ['message' => $e->getMessage()]);

            return ['synced' => 0, 'error' => $e->getMessage()];
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return ['synced' => 0];
        }

        $now = now();
        $prepared = [];
        $seenPaths = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pageUrl = trim((string) (($row['keys'][0] ?? '')));
            if ($pageUrl === '') {
                continue;
            }
            $path = $this->pathFromPageUrl($pageUrl);
            if ($path === null || ! app(SiteVisitorTracker::class)->isAllowedPath($path)) {
                continue;
            }
            // Prefer first GSC row per path (highest impressions order from API).
            if (isset($seenPaths[$path])) {
                continue;
            }
            $seenPaths[$path] = true;

            $clicks = (int) ($row['clicks'] ?? 0);
            $impr = (int) ($row['impressions'] ?? 0);
            $ctr = isset($row['ctr']) ? round((float) $row['ctr'], 4) : ($impr > 0 ? round($clicks / $impr, 4) : null);
            $position = isset($row['position']) ? round((float) $row['position'], 2) : null;

            $prepared[] = [
                'page_url' => mb_substr($pageUrl, 0, 500),
                'path' => $path,
                'clicks_28d' => $clicks,
                'impressions_28d' => $impr,
                'ctr_28d' => $ctr,
                'position_28d' => $position,
                'metrics_refreshed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($prepared === []) {
            return ['synced' => 0];
        }

        DB::table('site_gsc_page_metrics_staging')->delete();
        foreach (array_chunk($prepared, 200) as $chunk) {
            DB::table('site_gsc_page_metrics_staging')->insert($chunk);
        }

        $this->swapMetricTables('site_gsc_page_metrics', 'site_gsc_page_metrics_staging');

        return ['synced' => count($prepared)];
    }

    /**
     * Atomically publish staging → live without an empty window for readers.
     * MySQL/MariaDB: RENAME TABLE swap. Others: transactional replace.
     */
    private function swapMetricTables(string $live, string $staging): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $tmp = $live.'_swap_tmp';
            DB::statement("RENAME TABLE `{$live}` TO `{$tmp}`, `{$staging}` TO `{$live}`, `{$tmp}` TO `{$staging}`");
            DB::table($staging)->delete();

            return;
        }

        DB::transaction(function () use ($live, $staging) {
            DB::table($live)->delete();
            $rows = DB::table($staging)->get()->map(fn ($row) => (array) $row)->all();
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($live)->insert($chunk);
            }
            DB::table($staging)->delete();
        });
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function countryFilterGroups(): ?array
    {
        $country = strtoupper(trim((string) config('seo.gsc.country', '')));
        if ($country === '' || strlen($country) !== 3) {
            return null;
        }

        return [[
            'filters' => [[
                'dimension' => 'country',
                'operator' => 'equals',
                'expression' => $country,
            ]],
        ]];
    }

    private function pathFromPageUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '/';
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }

        return Str::limit($path, 500, '');
    }
}
