<?php

namespace App\Services\BlogAi;

use App\Models\BlogContentEvent;
use App\Models\BlogLearningInsight;
use App\Models\BlogPost;
use App\Models\BlogPostAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Roll up engagement + optional GSC page metrics, then distill AI learning insights.
 */
class BlogLearningService
{
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
     */
    public function syncGscPageMetrics(): array
    {
        $siteUrl = config('seo.gsc.site_url');
        $token = config('seo.gsc.access_token');

        if (! filled($siteUrl) || ! filled($token)) {
            return ['synced' => 0, 'skipped' => true];
        }

        $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
            .rawurlencode((string) $siteUrl)
            .'/searchAnalytics/query';

        try {
            $response = Http::withToken((string) $token)
                ->timeout(30)
                ->post($endpoint, [
                    'startDate' => now()->subDays(28)->toDateString(),
                    'endDate' => now()->subDay()->toDateString(),
                    'dimensions' => ['page'],
                    'rowLimit' => 100,
                    'dimensionFilterGroups' => [[
                        'filters' => [[
                            'dimension' => 'page',
                            'operator' => 'contains',
                            'expression' => '/blog/',
                        ]],
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Blog GSC sync failed', ['message' => $e->getMessage()]);

            return ['synced' => 0, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['synced' => 0, 'error' => 'HTTP '.$response->status()];
        }

        $synced = 0;
        foreach ($response->json('rows') ?? [] as $row) {
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

        return ['synced' => $synced, 'skipped' => false];
    }

    public function buildInsights(): BlogLearningInsight
    {
        $this->rollupAnalytics();
        $this->syncGscPageMetrics();

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
        $nextIdeas = $this->buildNextPostIdeas($recommendedClusters, $coverageGaps, $winningKeywords);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'market' => 'Bangladesh',
            'recommended_clusters' => $recommendedClusters,
            'cluster_performance' => $clusterWins,
            'winning_keywords' => $winningKeywords,
            'winning_titles' => $winningTitles,
            'underperforming_topics' => $underperformers,
            'cta_labels_that_convert' => array_slice($ctaWinners, 0, 8, true),
            'next_post_ideas' => $nextIdeas,
            'writing_guidance' => [
                'Prefer clusters and angles from winning_titles / recommended_clusters / next_post_ideas.',
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

        return BlogLearningInsight::query()->create([
            'scope' => 'global',
            'payload_json' => $payload,
            'summary_bn' => $summary,
            'posts_analyzed' => $top->count() + $bottom->count(),
            'events_analyzed' => $eventsAnalyzed,
            'generated_at' => now(),
        ]);
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
            return [
                'status' => 'cold_start',
                'note' => 'No learning snapshot yet — use BD seller pain topics (fake order, fraud checker, courier). Run System Maintenance → Blog learning insights.',
                'recommended_clusters' => ['fake_order', 'fraud_checker', 'courier'],
                'next_post_ideas' => $this->buildNextPostIdeas(
                    ['fake_order', 'fraud_checker', 'courier'],
                    ['facebook_ads', 'ai_orders', 'checkout_protection'],
                    [],
                ),
                'coverage_gaps' => ['facebook_ads', 'ai_orders', 'checkout_protection'],
            ];
        }

        $payload = $insight->payload_json ?? [];

        return [
            'status' => 'ready',
            'generated_at' => optional($insight->generated_at)?->toIso8601String(),
            'summary_bn' => $insight->summary_bn,
            'recommended_clusters' => $payload['recommended_clusters'] ?? [],
            'winning_keywords' => array_slice($payload['winning_keywords'] ?? [], 0, 10),
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
            'writing_guidance' => $payload['writing_guidance'] ?? [],
            'cta_labels_that_convert' => $payload['cta_labels_that_convert'] ?? [],
        ];
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

        return [
            'insight' => $insight ? [
                'generated_at' => optional($insight->generated_at)?->toIso8601String(),
                'summary_bn' => $insight->summary_bn,
                'posts_analyzed' => $insight->posts_analyzed,
                'events_analyzed' => $insight->events_analyzed,
                'payload' => $insight->payload_json,
            ] : null,
            'top_posts' => $top,
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
     * Deterministic “what to write next” suggestions for admins + AI.
     *
     * @param  list<string>  $recommendedClusters
     * @param  list<string>  $coverageGaps
     * @param  list<string>  $winningKeywords
     * @return list<array{cluster: string, angle: string, seed_topic: string, suggested_title: string, reason: string}>
     */
    private function buildNextPostIdeas(array $recommendedClusters, array $coverageGaps, array $winningKeywords): array
    {
        $ideas = [];
        $existingFocus = BlogPost::query()
            ->whereNotNull('focus_keyword')
            ->pluck('focus_keyword')
            ->map(fn ($k) => mb_strtolower(trim((string) $k)))
            ->filter()
            ->all();

        $angleCycle = ['howto', 'checklist', 'comparison', 'myth', 'roi'];

        foreach (array_values(array_unique([...$coverageGaps, ...$recommendedClusters])) as $i => $cluster) {
            if (count($ideas) >= 5) {
                break;
            }
            $seeds = config('blog_ai.cluster_seed_queries.'.$cluster, []);
            $seed = is_array($seeds) && $seeds !== []
                ? (string) $seeds[0]
                : (string) config('blog_ai.clusters.'.$cluster, $cluster);
            $angle = $angleCycle[$i % count($angleCycle)];
            $title = match ($angle) {
                'checklist' => $seed.' — ধাপে ধাপে চেকলিস্ট',
                'comparison' => $seed.' vs সাধারণ উপায়: কোনটা ভালো?',
                'myth' => $seed.' নিয়ে ভুল ধারণাগুলো',
                'roi' => $seed.' দিয়ে কীভাবে লস কমাবেন',
                default => 'কিভাবে '.$seed.' কাজে লাগাবেন',
            };

            if (in_array(mb_strtolower($seed), $existingFocus, true)) {
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

        // Fill remaining slots from winning keywords with a fresh angle.
        foreach ($winningKeywords as $kw) {
            if (count($ideas) >= 5) {
                break;
            }
            $ideas[] = [
                'cluster' => $recommendedClusters[0] ?? 'general',
                'angle' => 'howto',
                'seed_topic' => (string) $kw,
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
