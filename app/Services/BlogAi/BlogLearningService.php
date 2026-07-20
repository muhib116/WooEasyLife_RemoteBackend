<?php

namespace App\Services\BlogAi;

use App\Models\BlogContentEvent;
use App\Models\BlogGscQueryMetric;
use App\Models\BlogLearningInsight;
use App\Models\BlogPost;
use App\Models\BlogPostAnalytics;
use App\Services\Seo\GoogleSearchConsoleClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Roll up engagement + optional GSC page metrics, then distill AI learning insights.
 */
class BlogLearningService
{
    public function __construct(
        private GoogleSearchConsoleClient $gsc,
    ) {}

    /**
     * @return array{slugs: int, events: int}
     */
    public function rollupAnalytics(): array
    {
        $slugs = BlogContentEvent::query()
            ->select('slug')
            ->distinct()
            ->pluck('slug')
            ->merge(
                BlogPost::query()->whereNotNull('slug')->pluck('slug')
            )
            ->filter()
            ->unique()
            ->values();

        $eventsTotal = 0;

        foreach ($slugs as $slug) {
            $eventsTotal += $this->rollupSlug((string) $slug);
        }

        return [
            'slugs' => $slugs->count(),
            'events' => $eventsTotal,
        ];
    }

    public function rollupSlug(string $slug): int
    {
        $post = BlogPost::query()->where('slug', $slug)->first();
        $since7 = now()->subDays(7);
        $since28 = now()->subDays(28);

        $viewsTotal = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_VIEW)
            ->count();

        $views7 = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_VIEW)
            ->where('created_at', '>=', $since7)
            ->count();

        $views28 = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_VIEW)
            ->where('created_at', '>=', $since28)
            ->count();

        // Soft-cap abnormal single-day spikes (beacon spam) for 28d scoring window.
        $dayCap = max(10, (int) config('blog_ai.analytics.spam_views_per_slug_day_cap', 80));
        try {
            $cappedViews28 = (int) BlogContentEvent::query()
                ->where('slug', $slug)
                ->where('event_type', BlogContentEvent::TYPE_VIEW)
                ->where('created_at', '>=', $since28)
                ->selectRaw('DATE(created_at) as d')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('d')
                ->get()
                ->sum(fn ($row) => min((int) $row->c, $dayCap));
            if ($cappedViews28 > 0) {
                $views28 = min($views28, $cappedViews28);
            }
        } catch (\Throwable) {
            // Driver may not support DATE() grouping — keep raw $views28.
        }

        $scrolls28 = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_SCROLL)
            ->where('created_at', '>=', $since28)
            ->count();

        $unique28 = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_VIEW)
            ->where('created_at', '>=', $since28)
            ->whereNotNull('visitor_hash')
            ->distinct('visitor_hash')
            ->count('visitor_hash');

        // SQLite may not support distinct count the same way — fallback.
        if ($unique28 === 0 && $views28 > 0) {
            $unique28 = BlogContentEvent::query()
                ->where('slug', $slug)
                ->where('event_type', BlogContentEvent::TYPE_VIEW)
                ->where('created_at', '>=', $since28)
                ->whereNotNull('visitor_hash')
                ->pluck('visitor_hash')
                ->unique()
                ->count();
        }

        $ctaTotal = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_CTA_CLICK)
            ->count();

        $cta28 = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_CTA_CLICK)
            ->where('created_at', '>=', $since28)
            ->count();

        $topCtas = BlogContentEvent::query()
            ->where('slug', $slug)
            ->where('event_type', BlogContentEvent::TYPE_CTA_CLICK)
            ->where('created_at', '>=', $since28)
            ->whereNotNull('cta_label')
            ->select('cta_label', DB::raw('COUNT(*) as c'))
            ->groupBy('cta_label')
            ->orderByDesc('c')
            ->limit(5)
            ->pluck('c', 'cta_label')
            ->all();

        $row = BlogPostAnalytics::query()->firstOrNew(['slug' => $slug]);
        $row->blog_post_id = $post?->id;
        $row->title = $post?->title ?: $row->title;
        $row->focus_keyword = $post?->focus_keyword ?: $row->focus_keyword;
        $row->cluster = $post?->cluster ?: $row->cluster ?: $this->inferCluster($row->focus_keyword, $row->title);
        $row->locale = $post?->locale ?: ($row->locale ?: 'bn');
        $row->views_total = $viewsTotal;
        $row->views_7d = $views7;
        $row->views_28d = $views28;
        $row->unique_visitors_28d = $unique28;
        $row->cta_clicks_total = $ctaTotal;
        $row->cta_clicks_28d = $cta28;
        $row->top_cta_labels = $topCtas ?: null;
        $meta = is_array($row->meta_json) ? $row->meta_json : [];
        $meta['scrolls_28d'] = $scrolls28;
        $row->meta_json = $meta;
        $row->engagement_score = $this->score($row);
        $row->metrics_refreshed_at = now();
        if ($viewsTotal > 0) {
            $last = BlogContentEvent::query()
                ->where('slug', $slug)
                ->where('event_type', BlogContentEvent::TYPE_VIEW)
                ->orderByDesc('id')
                ->value('created_at');
            $row->last_viewed_at = $last;
        }
        $row->save();

        return $viewsTotal + $ctaTotal;
    }

    public function score(BlogPostAnalytics $row): float
    {
        $views28 = (float) $row->views_28d;
        $cta28 = (float) $row->cta_clicks_28d;
        $scrolls28 = (float) (is_array($row->meta_json) ? ($row->meta_json['scrolls_28d'] ?? 0) : 0);
        $gscClicks = (float) $row->gsc_clicks_28d;
        $gscImpr = (float) $row->gsc_impressions_28d;
        $ctr = $gscImpr > 0 ? ($gscClicks / $gscImpr) : (float) ($row->gsc_ctr_28d ?? 0);
        $positionBoost = $row->gsc_position_28d
            ? max(0, 20 - (float) $row->gsc_position_28d)
            : 0;

        // Weighted: on-site engagement + search demand.
        return round(
            ($views28 * 1.0)
            + ($cta28 * 8.0)
            + ($scrolls28 * 3.0)
            + ($gscClicks * 5.0)
            + ($gscImpr * 0.05)
            + ($ctr * 100)
            + $positionBoost,
            2
        );
    }

    /**
     * Pull page-level GSC metrics when credentials exist.
     *
     * @return array{synced: int, skipped?: bool, error?: string, pages?: int, kept_existing?: bool}
     */
    public function syncGscPageMetrics(): array
    {
        if (! $this->gsc->configured()) {
            return ['synced' => 0, 'skipped' => true];
        }

        $pageSize = 100;
        $maxPages = 10; // up to 1,000 blog pages
        $rawRows = [];
        $pagesFetched = 0;

        try {
            for ($page = 0; $page < $maxPages; $page++) {
                try {
                    $payload = $this->gsc->searchAnalytics([
                        'startDate' => now()->subDays(28)->toDateString(),
                        'endDate' => now()->subDay()->toDateString(),
                        'dimensions' => ['page'],
                        'rowLimit' => $pageSize,
                        'startRow' => $page * $pageSize,
                        'dimensionFilterGroups' => $this->gscBlogFilterGroups(),
                    ]);
                } catch (\Throwable $pageError) {
                    if ($rawRows !== []) {
                        Log::warning('Blog GSC page sync page failed; keeping partial updates.', [
                            'page' => $page,
                            'message' => $pageError->getMessage(),
                            'rows_so_far' => count($rawRows),
                        ]);

                        break;
                    }

                    throw $pageError;
                }

                $pagesFetched++;
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
            Log::warning('Blog GSC sync failed', ['message' => $e->getMessage()]);

            return ['synced' => 0, 'error' => $e->getMessage(), 'pages' => $pagesFetched];
        }

        $synced = 0;
        foreach ($rawRows as $row) {
            $page = (string) ($row['keys'][0] ?? '');
            $slug = $this->slugFromBlogUrl($page);
            if ($slug === null) {
                continue;
            }

            $analytics = BlogPostAnalytics::query()->firstOrNew(['slug' => $slug]);
            $analytics->gsc_clicks_28d = (int) ($row['clicks'] ?? 0);
            $analytics->gsc_impressions_28d = (int) ($row['impressions'] ?? 0);
            $analytics->gsc_ctr_28d = isset($row['ctr']) ? round((float) $row['ctr'], 4) : null;
            $analytics->gsc_position_28d = isset($row['position']) ? round((float) $row['position'], 2) : null;
            $analytics->engagement_score = $this->score($analytics);
            $analytics->metrics_refreshed_at = now();
            $analytics->save();
            $synced++;
        }

        return ['synced' => $synced, 'skipped' => false, 'pages' => $pagesFetched];
    }

    /**
     * Pull query×page GSC rows, classify rank opportunities, and upsert metrics.
     *
     * Never wipes existing rows on empty/invalid API payloads — only replaces after
     * a successful sync that produced at least one valid query×page pair.
     *
     * @return array{synced: int, skipped?: bool, error?: string, kept_existing?: bool, pages?: int}
     */
    public function syncGscQueryMetrics(): array
    {
        if (! Schema::hasTable('blog_gsc_query_metrics')) {
            return ['synced' => 0, 'skipped' => true, 'error' => 'missing_table'];
        }

        if (! $this->gsc->configured()) {
            return ['synced' => 0, 'skipped' => true];
        }

        $pageSize = 1000;
        $maxPages = 5; // up to 5,000 query×page rows
        $rawRows = [];
        $pagesFetched = 0;

        try {
            for ($page = 0; $page < $maxPages; $page++) {
                try {
                    $payload = $this->gsc->searchAnalytics([
                        'startDate' => now()->subDays(28)->toDateString(),
                        'endDate' => now()->subDay()->toDateString(),
                        'dimensions' => ['query', 'page'],
                        'rowLimit' => $pageSize,
                        'startRow' => $page * $pageSize,
                        'dimensionFilterGroups' => $this->gscBlogFilterGroups(),
                    ]);
                } catch (\Throwable $pageError) {
                    // Never replace the table with a truncated pagination snapshot.
                    if ($rawRows !== []) {
                        Log::warning('Blog GSC query sync page failed; keeping existing metrics.', [
                            'page' => $page,
                            'message' => $pageError->getMessage(),
                            'rows_so_far' => count($rawRows),
                        ]);

                        return [
                            'synced' => 0,
                            'skipped' => false,
                            'kept_existing' => true,
                            'pages' => $pagesFetched,
                            'error' => $pageError->getMessage(),
                        ];
                    }

                    throw $pageError;
                }

                $pagesFetched++;
                $batch = $payload['rows'] ?? [];
                if (! is_array($batch) || $batch === []) {
                    break;
                }

                foreach ($batch as $row) {
                    $rawRows[] = $row;
                }

                if (count($batch) < $pageSize) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Blog GSC query sync failed', ['message' => $e->getMessage()]);

            return [
                'synced' => 0,
                'error' => $e->getMessage(),
                'kept_existing' => $rawRows !== [],
            ];
        }

        if ($rawRows === []) {
            // Empty GSC window / no blog traffic yet — do not wipe a good previous sync.
            return [
                'synced' => 0,
                'skipped' => false,
                'kept_existing' => true,
                'pages' => $pagesFetched,
            ];
        }

        $prepared = [];
        $queryPages = [];

        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $keys = $row['keys'] ?? [];
            $query = trim((string) ($keys[0] ?? ''));
            $page = trim((string) ($keys[1] ?? ''));
            if ($query === '' || $page === '') {
                continue;
            }

            $clicks = (int) ($row['clicks'] ?? 0);
            $impr = (int) ($row['impressions'] ?? 0);
            $ctr = isset($row['ctr']) ? round((float) $row['ctr'], 4) : ($impr > 0 ? round($clicks / $impr, 4) : null);
            $position = isset($row['position']) ? round((float) $row['position'], 2) : null;
            $slug = $this->slugFromBlogUrl($page);

            $prepared[] = [
                'query' => mb_substr($query, 0, 500),
                'page_url' => mb_substr($page, 0, 500),
                'slug' => $slug,
                'clicks_28d' => $clicks,
                'impressions_28d' => $impr,
                'ctr_28d' => $ctr,
                'position_28d' => $position,
            ];

            $queryKey = mb_strtolower($query);
            $queryPages[$queryKey] = $queryPages[$queryKey] ?? [];
            $queryPages[$queryKey][$page] = ($queryPages[$queryKey][$page] ?? 0) + $impr;
        }

        if ($prepared === []) {
            // API returned rows but none were usable — keep existing metrics.
            return [
                'synced' => 0,
                'skipped' => false,
                'kept_existing' => true,
                'pages' => $pagesFetched,
            ];
        }

        $now = now();
        $synced = 0;

        DB::transaction(function () use ($prepared, $queryPages, $now, &$synced) {
            BlogGscQueryMetric::query()->delete();

            foreach ($prepared as $item) {
                $queryKey = mb_strtolower($item['query']);
                $pagesForQuery = $queryPages[$queryKey] ?? [];
                $isCannibalized = count($pagesForQuery) > 1;
                $topPage = null;
                if ($isCannibalized) {
                    arsort($pagesForQuery);
                    $topPage = array_key_first($pagesForQuery);
                }

                $classified = $this->classifyRankOpportunity(
                    impressions: $item['impressions_28d'],
                    clicks: $item['clicks_28d'],
                    ctr: $item['ctr_28d'],
                    position: $item['position_28d'],
                    isCannibalizedSecondary: $isCannibalized && $topPage !== null && $item['page_url'] !== $topPage,
                );

                BlogGscQueryMetric::query()->create([
                    'pair_hash' => hash('sha256', mb_strtolower($item['query']).'|'.$item['page_url']),
                    ...$item,
                    'bucket' => $classified['bucket'],
                    'opportunity_score' => $classified['score'],
                    'improvement_hint' => $classified['hint'],
                    'metrics_refreshed_at' => $now,
                ]);
                $synced++;
            }
        });

        return [
            'synced' => $synced,
            'skipped' => false,
            'pages' => $pagesFetched,
        ];
    }

    /**
     * Rank / CTR opportunity list for admin UI (from last sync).
     *
     * @return array{
     *     configured: bool,
     *     table_ready: bool,
     *     refreshed_at: string|null,
     *     summary: array<string, int>,
     *     items: list<array<string, mixed>>
     * }
     */
    public function rankOpportunitiesForAdmin(int $limit = 40): array
    {
        $configured = $this->gsc->configured();
        $tableReady = Schema::hasTable('blog_gsc_query_metrics');

        if (! $tableReady) {
            return [
                'configured' => $configured,
                'table_ready' => false,
                'refreshed_at' => null,
                'summary' => [],
                'items' => [],
            ];
        }

        $summary = BlogGscQueryMetric::query()
            ->select('bucket', DB::raw('COUNT(*) as c'))
            ->groupBy('bucket')
            ->pluck('c', 'bucket')
            ->map(fn ($c) => (int) $c)
            ->all();

        $refreshedAt = BlogGscQueryMetric::query()->max('metrics_refreshed_at');

        $items = BlogGscQueryMetric::query()
            ->whereIn('bucket', [
                BlogGscQueryMetric::BUCKET_STRIKING,
                BlogGscQueryMetric::BUCKET_FIX_CTR,
                BlogGscQueryMetric::BUCKET_DEFEND,
                BlogGscQueryMetric::BUCKET_BURIED,
                BlogGscQueryMetric::BUCKET_CANNIBALIZED,
            ])
            ->orderByDesc('opportunity_score')
            ->orderByDesc('impressions_28d')
            ->limit($limit)
            ->get()
            ->map(fn (BlogGscQueryMetric $row) => [
                'query' => $row->query,
                'slug' => $row->slug,
                'page_url' => $row->page_url,
                'clicks_28d' => $row->clicks_28d,
                'impressions_28d' => $row->impressions_28d,
                'ctr_28d' => $row->ctr_28d,
                'position_28d' => $row->position_28d,
                'bucket' => $row->bucket,
                'bucket_label' => $this->bucketLabel($row->bucket),
                'opportunity_score' => $row->opportunity_score,
                'improvement_hint' => $row->improvement_hint,
            ])
            ->all();

        return [
            'configured' => $configured,
            'table_ready' => true,
            'refreshed_at' => $refreshedAt
                ? (string) \Illuminate\Support\Carbon::parse($refreshedAt)->toIso8601String()
                : null,
            'summary' => $summary,
            'items' => $items,
        ];
    }

    /**
     * @return array{bucket: string, score: float, hint: string}
     */
    public function classifyRankOpportunity(
        int $impressions,
        int $clicks,
        ?float $ctr,
        ?float $position,
        bool $isCannibalizedSecondary = false,
    ): array {
        $ctrValue = $ctr ?? ($impressions > 0 ? $clicks / $impressions : 0.0);
        $pos = $position ?? 100.0;
        $expectedCtr = $this->expectedCtrForPosition($pos);
        $ctrGap = max(0, $expectedCtr - $ctrValue);

        if ($isCannibalizedSecondary && $impressions >= 10) {
            return [
                'bucket' => BlogGscQueryMetric::BUCKET_CANNIBALIZED,
                'score' => round(($impressions * 0.4) + ($ctrGap * 80), 2),
                'hint' => 'Same query ranks on multiple URLs — consolidate or differentiate this weaker page.',
            ];
        }

        if ($impressions >= 30 && $pos <= 5 && $ctrGap >= 0.03) {
            return [
                'bucket' => BlogGscQueryMetric::BUCKET_DEFEND,
                'score' => round(($impressions * 0.35) + ($ctrGap * 200) + ($clicks * 2), 2),
                'hint' => 'Top ranking but CTR is below expected — tighten title/meta for this query.',
            ];
        }

        if ($impressions >= 20 && $pos >= 8 && $pos <= 20) {
            return [
                'bucket' => BlogGscQueryMetric::BUCKET_STRIKING,
                'score' => round(($impressions * 0.5) + ((21 - $pos) * 8) + ($ctrGap * 100), 2),
                'hint' => 'Striking distance (pos 8–20) — add matching H2/FAQ, strengthen intro, and internal links.',
            ];
        }

        if ($impressions >= 40 && $ctrGap >= 0.025 && $pos <= 15) {
            return [
                'bucket' => BlogGscQueryMetric::BUCKET_FIX_CTR,
                'score' => round(($impressions * 0.45) + ($ctrGap * 250), 2),
                'hint' => 'High impressions, weak CTR — rewrite title/description to match search intent.',
            ];
        }

        if ($impressions >= 25 && $pos > 20) {
            return [
                'bucket' => BlogGscQueryMetric::BUCKET_BURIED,
                'score' => round(($impressions * 0.25) + min(40, $pos), 2),
                'hint' => 'Buried past page 2 — refresh content depth or consider a dedicated supporting post.',
            ];
        }

        return [
            'bucket' => BlogGscQueryMetric::BUCKET_OTHER,
            'score' => round(($impressions * 0.05) + ($clicks * 0.5), 2),
            'hint' => 'Monitor — not an urgent opportunity yet.',
        ];
    }

    public function buildInsights(): BlogLearningInsight
    {
        $this->rollupAnalytics();
        $this->syncGscPageMetrics();
        $this->syncGscQueryMetrics();

        $top = BlogPostAnalytics::query()
            ->orderByDesc('engagement_score')
            ->orderByDesc('views_28d')
            ->limit(15)
            ->get();

        $bottom = BlogPostAnalytics::query()
            ->where('views_28d', '>', 0)
            ->orderBy('engagement_score')
            ->orderBy('views_28d')
            ->limit(8)
            ->get();

        $clusterWins = BlogPostAnalytics::query()
            ->whereNotNull('cluster')
            ->where('cluster', '!=', '')
            ->select('cluster', DB::raw('AVG(engagement_score) as avg_score'), DB::raw('SUM(views_28d) as views'), DB::raw('COUNT(*) as posts'))
            ->groupBy('cluster')
            ->orderByDesc('avg_score')
            ->get()
            ->map(fn ($r) => [
                'cluster' => $r->cluster,
                'label' => config('blog_ai.clusters.'.$r->cluster, $r->cluster),
                'avg_score' => round((float) $r->avg_score, 2),
                'views_28d' => (int) $r->views,
                'posts' => (int) $r->posts,
            ])
            ->values()
            ->all();

        $winningKeywords = $top
            ->pluck('focus_keyword')
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();

        $gscKeywordSeeds = $this->gscKeywordSeeds(15);
        $gscQueries = collect($gscKeywordSeeds)->pluck('query')->filter()->values()->all();
        $winningKeywords = collect($gscQueries)
            ->merge($winningKeywords)
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(16)
            ->values()
            ->all();

        $winningTitles = $top->take(8)->map(fn (BlogPostAnalytics $r) => [
            'title' => $r->title,
            'slug' => $r->slug,
            'score' => $r->engagement_score,
            'views_28d' => $r->views_28d,
            'cta_28d' => $r->cta_clicks_28d,
            'cluster' => $r->cluster,
            'focus_keyword' => $r->focus_keyword,
        ])->values()->all();

        $underperformers = $bottom->map(fn (BlogPostAnalytics $r) => [
            'title' => $r->title,
            'slug' => $r->slug,
            'score' => $r->engagement_score,
            'focus_keyword' => $r->focus_keyword,
            'cluster' => $r->cluster,
            'hint' => 'Low engagement — avoid cloning angle; refresh or pick different intent',
        ])->values()->all();

        $recommendedClusters = collect($clusterWins)->take(5)->pluck('cluster')->filter()->values()->all();
        if ($recommendedClusters === []) {
            $recommendedClusters = ['fake_order', 'fraud_checker', 'courier'];
        }

        $ctaWinners = [];
        foreach ($top as $row) {
            foreach ($row->top_cta_labels ?? [] as $label => $count) {
                $ctaWinners[$label] = ($ctaWinners[$label] ?? 0) + (int) $count;
            }
        }
        arsort($ctaWinners);

        $coverageGaps = $this->coverageGaps();
        $nextIdeas = $this->buildNextPostIdeas($recommendedClusters, $coverageGaps, $winningKeywords, $gscKeywordSeeds);
        $rankOpportunities = $this->rankOpportunitiesForAdmin(15);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'market' => 'Bangladesh',
            'recommended_clusters' => $recommendedClusters,
            'cluster_performance' => $clusterWins,
            'winning_keywords' => $winningKeywords,
            'gsc_keyword_seeds' => array_slice($gscKeywordSeeds, 0, 12),
            'winning_titles' => $winningTitles,
            'underperforming_topics' => $underperformers,
            'cta_labels_that_convert' => array_slice($ctaWinners, 0, 8, true),
            'next_post_ideas' => $nextIdeas,
            'rank_opportunities' => [
                'summary' => $rankOpportunities['summary'],
                'items' => array_slice($rankOpportunities['items'], 0, 10),
                'refreshed_at' => $rankOpportunities['refreshed_at'],
            ],
            'writing_guidance' => [
                'Prefer clusters and angles from winning_titles / recommended_clusters / next_post_ideas.',
                'Prioritize gsc_keyword_seeds / rank_opportunities (striking_distance, fix_ctr) in titles, H2, FAQ.',
                'Do not cannibalize exact focus_keyword of top winners unless updating that topic.',
                'Underperforming topics: change intent/angle; do not rewrite the same weak hook.',
                'Include soft CTA patterns similar to cta_labels_that_convert when relevant.',
                'Keep Bangla seller-talk; practical steps beat generic fluff.',
            ],
            'coverage_gaps' => $coverageGaps,
        ];

        $summary = $this->summarizeBn($payload);

        $eventsAnalyzed = BlogContentEvent::query()
            ->where('created_at', '>=', now()->subDays(28))
            ->count();

        $insight = BlogLearningInsight::query()->create([
            'scope' => 'global',
            'payload_json' => $payload,
            'summary_bn' => $summary,
            'posts_analyzed' => $top->count() + $bottom->count(),
            'events_analyzed' => $eventsAnalyzed,
            'generated_at' => now(),
        ]);

        try {
            app(BlogMemoryService::class)->absorbFromInsight($insight);
        } catch (\Throwable) {
            // Memory absorb is best-effort — never block the nightly learning job.
        }

        return $insight;
    }

    /**
     * Compact block for OpenAI prompts.
     *
     * @return array<string, mixed>
     */
    public function promptLearningBlock(): array
    {
        $insight = BlogLearningInsight::latestGlobal();
        if (! $insight) {
            $cold = [
                'status' => 'cold_start',
                'note' => 'No learning snapshot yet — use BD seller pain topics (fake order, fraud checker, courier). Run System Maintenance → Blog learning insights.',
                'recommended_clusters' => ['fake_order', 'fraud_checker', 'courier'],
                'next_post_ideas' => $this->buildNextPostIdeas(
                    ['fake_order', 'fraud_checker', 'courier'],
                    ['facebook_ads', 'ai_orders', 'checkout_protection'],
                    [],
                    $this->gscKeywordSeeds(8),
                ),
                'gsc_keyword_seeds' => $this->gscKeywordSeeds(8),
                'coverage_gaps' => ['facebook_ads', 'ai_orders', 'checkout_protection'],
            ];
            $competitor = $this->recentCompetitorPromptBlock();
            if ($competitor !== null) {
                $cold['competitor_intelligence'] = $competitor;
            }

            return $cold;
        }

        $payload = $insight->payload_json ?? [];

        $block = [
            'status' => 'ready',
            'generated_at' => optional($insight->generated_at)?->toIso8601String(),
            'summary_bn' => $insight->summary_bn,
            'recommended_clusters' => $payload['recommended_clusters'] ?? [],
            'winning_keywords' => array_slice($payload['winning_keywords'] ?? [], 0, 10),
            'gsc_keyword_seeds' => array_slice($payload['gsc_keyword_seeds'] ?? $this->gscKeywordSeeds(8), 0, 10),
            'winning_title_patterns' => collect($payload['winning_titles'] ?? [])
                ->take(6)
                ->map(fn ($r) => [
                    'title' => $r['title'] ?? null,
                    'cluster' => $r['cluster'] ?? null,
                    'focus_keyword' => $r['focus_keyword'] ?? null,
                    'score' => $r['score'] ?? null,
                ])
                ->values()
                ->all(),
            'avoid_or_refresh' => collect($payload['underperforming_topics'] ?? [])
                ->take(5)
                ->values()
                ->all(),
            'coverage_gaps' => $payload['coverage_gaps'] ?? [],
            'next_post_ideas' => array_slice($payload['next_post_ideas'] ?? [], 0, 5),
            'rank_opportunities' => array_slice($payload['rank_opportunities']['items'] ?? [], 0, 5),
            'writing_guidance' => $payload['writing_guidance'] ?? [],
            'cta_labels_that_convert' => $payload['cta_labels_that_convert'] ?? [],
        ];

        $competitor = $this->recentCompetitorPromptBlock();
        if ($competitor !== null) {
            $block['competitor_intelligence'] = $competitor;
        }

        return $block;
    }

    /**
     * Latest competitor gap analysis for AI prompts (lazy resolve to avoid DI cycles).
     *
     * @return array<string, mixed>|null
     */
    private function recentCompetitorPromptBlock(): ?array
    {
        if (! config('blog_ai.competitors.enabled', true)
            || ! config('blog_ai.competitors.in_prompts', true)) {
            return null;
        }

        if (! Schema::hasTable('blog_competitor_analyses')) {
            return null;
        }

        // Only attach competitor intel when it matches a next-idea / GSC seed keyword.
        /** @var BlogCompetitorAnalyzer $analyzer */
        $analyzer = app(BlogCompetitorAnalyzer::class);
        $insight = BlogLearningInsight::latestGlobal();
        $payload = $insight && is_array($insight->payload_json) ? $insight->payload_json : [];

        $keywords = [];
        foreach (array_slice($payload['next_post_ideas'] ?? [], 0, 5) as $idea) {
            if (is_array($idea)) {
                $keywords[] = (string) ($idea['seed_topic'] ?? '');
            }
        }
        foreach (array_slice($payload['gsc_keyword_seeds'] ?? [], 0, 8) as $seed) {
            $keywords[] = is_array($seed) ? (string) ($seed['query'] ?? '') : (string) $seed;
        }

        foreach (array_filter($keywords) as $keyword) {
            $block = $analyzer->promptBlockForKeyword($keyword);
            if ($block !== null) {
                return $block;
            }
        }

        return null;
    }

    public function adminDashboard(): array
    {
        $insight = BlogLearningInsight::latestGlobal();
        $top = BlogPostAnalytics::query()
            ->orderByDesc('engagement_score')
            ->limit(10)
            ->get()
            ->map(fn (BlogPostAnalytics $r) => [
                'slug' => $r->slug,
                'title' => $r->title,
                'cluster' => $r->cluster,
                'focus_keyword' => $r->focus_keyword,
                'views_28d' => $r->views_28d,
                'cta_clicks_28d' => $r->cta_clicks_28d,
                'gsc_clicks_28d' => $r->gsc_clicks_28d,
                'engagement_score' => $r->engagement_score,
            ])
            ->all();

        $competitors = [];
        $memories = [];
        $memoryStats = ['active' => 0, 'total' => 0, 'by_type' => []];
        if (config('blog_ai.competitors.enabled', true) && Schema::hasTable('blog_competitor_analyses')) {
            $competitors = app(BlogCompetitorAnalyzer::class)->recentForAdmin(6);
        }
        if (config('blog_ai.memory.enabled', true) && Schema::hasTable('blog_ai_memories')) {
            $memory = app(BlogMemoryService::class);
            $memories = $memory->listForAdmin(null, 40);
            $memoryStats = $memory->stats();
        }

        return [
            'insight' => $insight ? [
                'generated_at' => optional($insight->generated_at)?->toIso8601String(),
                'summary_bn' => $insight->summary_bn,
                'posts_analyzed' => $insight->posts_analyzed,
                'events_analyzed' => $insight->events_analyzed,
                'payload' => $insight->payload_json,
            ] : null,
            'top_posts' => $top,
            'rank_opportunities' => $this->rankOpportunitiesForAdmin(25),
            'competitors' => $competitors,
            'memories' => $memories,
            'memory_stats' => $memoryStats,
            'intelligence' => app(BlogIntelligenceScorer::class)->score(),
            'clusters' => config('blog_ai.clusters', []),
        ];
    }

    private function inferCluster(?string $focusKeyword, ?string $title): ?string
    {
        $hay = mb_strtolower(trim(($focusKeyword ?? '').' '.($title ?? '')));
        if ($hay === '') {
            return null;
        }

        $map = [
            'fake_order' => ['ফেক', 'fake order', 'cod fraud'],
            'fraud_checker' => ['ফ্রড', 'fraud', 'হিস্টোরি', 'history'],
            'courier' => ['কুরিয়ার', 'courier', 'pathao', 'steadfast', 'redx'],
            'checkout_protection' => ['otp', 'চেকআউট', 'block', 'ডুপ্লিকেট'],
            'missing_order' => ['হারানো', 'missing', 'abandoned'],
            'facebook_ads' => ['pixel', 'ফেসবুক', 'facebook ads'],
            'ai_orders' => ['ai order', 'মেসেজ থেকে', 'screenshot'],
        ];

        foreach ($map as $cluster => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($hay, mb_strtolower($needle))) {
                    return $cluster;
                }
            }
        }

        return 'general';
    }

    /**
     * GSC filters for blog URLs (+ optional country). Empty country preserves live behavior.
     *
     * @return list<array{filters: list<array{dimension: string, operator: string, expression: string}>}>
     */
    private function gscBlogFilterGroups(): array
    {
        $filters = [[
            'dimension' => 'page',
            'operator' => 'contains',
            'expression' => '/blog/',
        ]];

        $country = strtolower(trim((string) config('seo.gsc.country', '')));
        if ($country !== '' && preg_match('/^[a-z]{3}$/', $country)) {
            $filters[] = [
                'dimension' => 'country',
                'operator' => 'equals',
                'expression' => $country,
            ];
        }

        return [['filters' => $filters]];
    }

    /**
     * @return list<string>
     */
    private function coverageGaps(): array
    {
        $clusters = array_keys(config('blog_ai.clusters', []));
        $covered = BlogPostAnalytics::query()
            ->whereNotNull('cluster')
            ->distinct()
            ->pluck('cluster')
            ->all();

        $gaps = array_values(array_diff($clusters, $covered));

        return array_slice($gaps, 0, 6);
    }

    /**
     * High-value GSC queries for keyword research + next-post ideas.
     *
     * @return list<array{query: string, bucket: string, impressions: int, clicks: int, position: float|null, slug: string|null, hint: string|null}>
     */
    public function gscKeywordSeeds(int $limit = 12): array
    {
        if (! Schema::hasTable('blog_gsc_query_metrics')) {
            return [];
        }

        return BlogGscQueryMetric::query()
            ->whereIn('bucket', [
                BlogGscQueryMetric::BUCKET_STRIKING,
                BlogGscQueryMetric::BUCKET_FIX_CTR,
                BlogGscQueryMetric::BUCKET_DEFEND,
                BlogGscQueryMetric::BUCKET_BURIED,
            ])
            ->orderByDesc('opportunity_score')
            ->orderByDesc('impressions_28d')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (BlogGscQueryMetric $row) => [
                'query' => (string) $row->query,
                'bucket' => (string) $row->bucket,
                'impressions' => (int) $row->impressions_28d,
                'clicks' => (int) $row->clicks_28d,
                'position' => $row->position_28d !== null ? (float) $row->position_28d : null,
                'slug' => $row->slug,
                'hint' => $row->improvement_hint,
                'opportunity_score' => (float) ($row->opportunity_score ?? 0),
            ])
            ->filter(fn (array $row) => trim($row['query']) !== '')
            ->values()
            ->all();
    }

    /**
     * Deterministic “what to write next” suggestions for admins + AI.
     *
     * @param  list<string>  $recommendedClusters
     * @param  list<string>  $coverageGaps
     * @param  list<string>  $winningKeywords
     * @param  list<array{query?: string, bucket?: string, hint?: string|null, slug?: string|null}>  $gscSeeds
     * @return list<array{cluster: string, angle: string, seed_topic: string, suggested_title: string, reason: string}>
     */
    private function buildNextPostIdeas(
        array $recommendedClusters,
        array $coverageGaps,
        array $winningKeywords,
        array $gscSeeds = [],
    ): array {
        $ideas = [];
        $existingFocus = BlogPost::query()
            ->whereNotNull('focus_keyword')
            ->pluck('focus_keyword')
            ->map(fn ($k) => mb_strtolower(trim((string) $k)))
            ->filter()
            ->all();

        $seenSeeds = [];

        foreach ($gscSeeds as $seedRow) {
            if (count($ideas) >= 5) {
                break;
            }
            $query = trim((string) ($seedRow['query'] ?? ''));
            if ($query === '') {
                continue;
            }
            $key = mb_strtolower($query);
            if (isset($seenSeeds[$key])) {
                continue;
            }
            $seenSeeds[$key] = true;

            $bucket = (string) ($seedRow['bucket'] ?? 'gsc');
            $cluster = $this->inferCluster($query, $query);
            $title = match ($bucket) {
                BlogGscQueryMetric::BUCKET_FIX_CTR => $query.' — ক্লিক বাড়ানোর টাইটেল ও মেটা',
                BlogGscQueryMetric::BUCKET_DEFEND => $query.' — র‍্যাঙ্ক ধরে রাখার আপডেট',
                BlogGscQueryMetric::BUCKET_BURIED => $query.' — নতুন লং-টেল গাইড',
                default => $query.' — পজিশন ১–১০ এ তোলার গাইড',
            };

            if (in_array($key, $existingFocus, true)) {
                $title = $query.' — কনটেন্ট রিফ্রেশ ও FAQ আপডেট';
            }

            $ideas[] = [
                'cluster' => $cluster,
                'angle' => 'gsc_'.$bucket,
                'seed_topic' => $query,
                'suggested_title' => $title,
                'reason' => 'gsc_'.$bucket,
            ];
        }

        $angleCycle = ['howto', 'checklist', 'comparison', 'myth', 'roi'];

        foreach (array_values(array_unique([...$coverageGaps, ...$recommendedClusters])) as $i => $cluster) {
            if (count($ideas) >= 5) {
                break;
            }
            $seeds = config('blog_ai.cluster_seed_queries.'.$cluster, []);
            $seed = is_array($seeds) && $seeds !== []
                ? (string) $seeds[0]
                : (string) config('blog_ai.clusters.'.$cluster, $cluster);
            $seedKey = mb_strtolower($seed);
            if (isset($seenSeeds[$seedKey])) {
                continue;
            }
            $seenSeeds[$seedKey] = true;

            $angle = $angleCycle[$i % count($angleCycle)];
            $title = match ($angle) {
                'checklist' => $seed.' — ধাপে ধাপে চেকলিস্ট',
                'comparison' => $seed.' vs সাধারণ উপায়: কোনটা ভালো?',
                'myth' => $seed.' নিয়ে ভুল ধারণাগুলো',
                'roi' => $seed.' দিয়ে কীভাবে লস কমাবেন',
                default => 'কিভাবে '.$seed.' কাজে লাগাবেন',
            };

            if (in_array($seedKey, $existingFocus, true)) {
                $title = $seed.' — নতুন আপডেট ও ব্যবহারিক টিপস';
            }

            $ideas[] = [
                'cluster' => $cluster,
                'angle' => $angle,
                'seed_topic' => $seed,
                'suggested_title' => $title,
                'reason' => in_array($cluster, $coverageGaps, true)
                    ? 'coverage_gap'
                    : 'recommended_cluster',
            ];
        }

        foreach ($winningKeywords as $kw) {
            if (count($ideas) >= 5) {
                break;
            }
            $kw = trim((string) $kw);
            if ($kw === '') {
                continue;
            }
            $kwKey = mb_strtolower($kw);
            if (isset($seenSeeds[$kwKey])) {
                continue;
            }
            $seenSeeds[$kwKey] = true;

            $ideas[] = [
                'cluster' => $recommendedClusters[0] ?? 'general',
                'angle' => 'howto',
                'seed_topic' => $kw,
                'suggested_title' => $kw.' — বাস্তব কেস ও করণীয়',
                'reason' => 'winning_keyword_expansion',
            ];
        }

        return array_slice($ideas, 0, 5);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function summarizeBn(array $payload): string
    {
        $clusters = collect($payload['recommended_clusters'] ?? [])->take(3)->implode(', ');
        $kw = collect($payload['winning_keywords'] ?? [])->take(5)->implode(' · ');
        $gaps = collect($payload['coverage_gaps'] ?? [])->take(3)->implode(', ');
        $next = collect($payload['next_post_ideas'] ?? [])->take(2)->pluck('suggested_title')->filter()->implode(' | ');

        return trim(implode(' ', array_filter([
            $clusters !== '' ? "ভালো পারফর্ম করছে ক্লাস্টার: {$clusters}." : null,
            $kw !== '' ? "শক্তিশালী কীওয়ার্ড: {$kw}." : null,
            $gaps !== '' ? "কভারেজ গ্যাপ: {$gaps}." : 'কভারেজ গ্যাপ কম।',
            $next !== '' ? "পরের আইডিয়া: {$next}." : null,
        ])));
    }

    private function expectedCtrForPosition(float $position): float
    {
        return match (true) {
            $position <= 1 => 0.28,
            $position <= 2 => 0.15,
            $position <= 3 => 0.11,
            $position <= 4 => 0.08,
            $position <= 5 => 0.06,
            $position <= 7 => 0.04,
            $position <= 10 => 0.025,
            $position <= 15 => 0.015,
            $position <= 20 => 0.01,
            default => 0.005,
        };
    }

    private function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            BlogGscQueryMetric::BUCKET_STRIKING => 'Striking distance',
            BlogGscQueryMetric::BUCKET_FIX_CTR => 'Fix CTR',
            BlogGscQueryMetric::BUCKET_DEFEND => 'Defend winner',
            BlogGscQueryMetric::BUCKET_BURIED => 'Buried',
            BlogGscQueryMetric::BUCKET_CANNIBALIZED => 'Cannibalized',
            default => 'Other',
        };
    }

    private function slugFromBlogUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (! preg_match('#/blog/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#i', $path, $m)) {
            return null;
        }

        return Str::lower($m[1]);
    }
}
