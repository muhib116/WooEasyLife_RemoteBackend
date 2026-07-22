<?php

namespace App\Services\Analytics;

use App\Models\SiteVisitorDailyStat;
use App\Models\SiteVisitorEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SiteVisitorReportingService
{
    public function __construct(
        private SiteSeoGscSyncService $seoGsc,
    ) {}

    /**
     * Light report payload (overview + table rows). Heavy insight/SEO panels are separate.
     *
     * @return array{overview: array, rows: array<int, array>, type: string, meta: array}
     */
    public function report(string $type, Carbon $from, Carbon $to, ?string $path = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $overview = $this->overview($from, $to, $path);
        $resolvedType = $type === 'overview' ? 'by_path' : $type;

        $rows = match ($resolvedType) {
            'by_path' => $this->enrichPathRows($this->byPath($from, $to, $path), $overview),
            'by_source' => $this->enrichSourceRows($this->bySource($from, $to, $path), $overview),
            'engagement' => $this->enrichEngagementRows($this->engagement($from, $to, $path), $overview),
            'actions' => $this->actions($from, $to, $path),
            'devices' => $this->byDevice($from, $to, $path),
            'daily' => $this->dailyTrend($from, $to, $path),
            'referrers' => $this->topReferrers($from, $to, $path, 50),
            'keywords' => $this->firstPartyKeywords($from, $to, $path, 50),
            default => $this->enrichPathRows($this->byPath($from, $to, $path), $overview),
        };

        return [
            'overview' => $overview,
            'rows' => $rows,
            'type' => in_array($resolvedType, ['by_path', 'by_source', 'engagement', 'actions', 'devices', 'daily', 'referrers', 'keywords'], true)
                ? $resolvedType
                : 'by_path',
            'meta' => [
                'by_path_source' => $this->hasDailyStatsCoverage($from, $to, $path) ? 'daily_stats' : 'events',
                'range_days' => max(1, (int) round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())) + 1),
            ],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function overview(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $rollup = $this->overviewFromDailyStats($from, $to, $path);

        $base = SiteVisitorEvent::query()
            ->whereBetween('created_at', [$from, $to]);

        if ($path) {
            $base->where('path', $path);
        }

        $pageviews = $rollup['pageviews'] ?? (clone $base)
            ->where('event_type', SiteVisitorEvent::TYPE_PAGE_VIEW)
            ->count();
        $ctaClicks = $rollup['cta_clicks'] ?? (clone $base)
            ->where('event_type', SiteVisitorEvent::TYPE_CTA_CLICK)
            ->count();

        $visitors = (clone $base)->where('event_type', SiteVisitorEvent::TYPE_PAGE_VIEW)
            ->whereNotNull('visitor_hash')
            ->distinct('visitor_hash')
            ->count('visitor_hash');
        $sessions = (clone $base)->where('event_type', SiteVisitorEvent::TYPE_PAGE_VIEW)
            ->whereNotNull('session_hash')
            ->distinct('session_hash')
            ->count('session_hash');

        $toolActions = (clone $base)->where('event_type', SiteVisitorEvent::TYPE_TOOL_ACTION)->count();
        $scroll50 = (clone $base)
            ->where('event_type', SiteVisitorEvent::TYPE_SCROLL)
            ->where('scroll_pct', '>=', 50)
            ->count();
        $pagesTracked = (clone $base)
            ->where('event_type', SiteVisitorEvent::TYPE_PAGE_VIEW)
            ->distinct('path')
            ->count('path');

        $ctaRate = $pageviews > 0 ? round(($ctaClicks / $pageviews) * 100, 2) : 0.0;
        $toolRate = $pageviews > 0 ? round(($toolActions / $pageviews) * 100, 2) : 0.0;
        $scroll50Rate = $pageviews > 0 ? round(($scroll50 / $pageviews) * 100, 2) : 0.0;
        $pagesPerVisitor = $visitors > 0 ? round($pageviews / $visitors, 2) : 0.0;
        $pagesPerSession = $sessions > 0 ? round($pageviews / $sessions, 2) : 0.0;

        return [
            'visitors' => $visitors,
            'pageviews' => $pageviews,
            'sessions' => $sessions,
            'avg_engaged_ms' => $this->averageMaxEngagedMs($from, $to, $path),
            'cta_clicks' => $ctaClicks,
            'tool_actions' => $toolActions,
            'scroll_50' => $scroll50,
            'pages_tracked' => $pagesTracked,
            'cta_rate' => $ctaRate,
            'tool_rate' => $toolRate,
            'scroll_50_rate' => $scroll50Rate,
            'pages_per_visitor' => $pagesPerVisitor,
            'pages_per_session' => $pagesPerSession,
        ];
    }

    /**
     * Heavy dashboard panels (sources/devices/daily/etc). SEO is loaded via seoPanel().
     *
     * @param  array<string, int|float>|null  $overview
     * @return array<string, mixed>
     */
    public function insights(Carbon $from, Carbon $to, ?string $path, ?array $overview = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $overview ??= $this->overview($from, $to, $path);

        $sources = $this->enrichSourceRows($this->bySource($from, $to, $path), $overview);
        $devices = $this->byDevice($from, $to, $path);
        $daily = $this->dailyTrend($from, $to, $path);
        $referrers = $this->topReferrers($from, $to, $path, 8);
        $campaigns = $this->topCampaigns($from, $to, $path, 8);
        $topActions = array_slice($this->actions($from, $to, $path), 0, 8);
        $topPaths = array_slice(
            $this->enrichPathRows($this->byPath($from, $to, $path), $overview),
            0,
            5,
        );

        return [
            'sources' => $sources,
            'devices' => $devices,
            'daily' => $daily,
            'referrers' => $referrers,
            'campaigns' => $campaigns,
            'top_actions' => $topActions,
            'top_paths' => $topPaths,
            'first_party_keywords' => $this->firstPartyKeywords($from, $to, $path, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function seoPanel(?string $path = null, int $limit = 20): array
    {
        return $this->seoGsc->seoPanel($path, $limit);
    }

    /**
     * Keywords captured from utm_term / rare referrer query params on pageviews.
     *
     * @return array<int, array>
     */
    public function firstPartyKeywords(Carbon $from, Carbon $to, ?string $path = null, int $limit = 25): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                'search_keyword',
                DB::raw('COUNT(*) as hits'),
                DB::raw('COUNT(DISTINCT visitor_hash) as unique_visitors'),
                DB::raw('COUNT(DISTINCT path) as pages'),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->where('event_type', SiteVisitorEvent::TYPE_PAGE_VIEW)
            ->whereNotNull('search_keyword')
            ->where('search_keyword', '!=', '')
            ->groupBy('search_keyword')
            ->orderByDesc('hits')
            ->limit($limit);

        if ($path) {
            $q->where('path', $path);
        }

        return $q->get()->map(fn ($row) => [
            'keyword' => $row->search_keyword,
            'hits' => (int) $row->hits,
            'unique_visitors' => (int) $row->unique_visitors,
            'pages' => (int) $row->pages,
        ])->all();
    }

    /**
     * @return array<int, array>
     */
    public function byPath(Carbon $from, Carbon $to, ?string $path = null): array
    {
        if ($this->hasDailyStatsCoverage($from, $to, $path)) {
            return $this->byPathFromDailyStats($from, $to, $path);
        }

        return $this->byPathFromEvents($from, $to, $path);
    }

    /**
     * @return array<int, array>
     */
    public function bySource(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                DB::raw("COALESCE(source_channel, 'other') as source_channel"),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
                DB::raw("SUM(CASE WHEN event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks"),
                DB::raw("SUM(CASE WHEN event_type = 'tool_action' THEN 1 ELSE 0 END) as tool_actions"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("COALESCE(source_channel, 'other')"))
            ->orderByDesc('pageviews');

        if ($path) {
            $q->where('path', $path);
        }

        return $q->get()->map(fn ($row) => [
            'source_channel' => $row->source_channel,
            'pageviews' => (int) $row->pageviews,
            'unique_visitors' => (int) $row->unique_visitors,
            'cta_clicks' => (int) $row->cta_clicks,
            'tool_actions' => (int) $row->tool_actions,
        ])->all();
    }

    /**
     * @return array<int, array>
     */
    public function byDevice(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                DB::raw("COALESCE(device_type, 'desktop') as device_type"),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
                DB::raw("SUM(CASE WHEN event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("COALESCE(device_type, 'desktop')"))
            ->orderByDesc('pageviews');

        if ($path) {
            $q->where('path', $path);
        }

        $rows = $q->get();
        $totalPv = max(1, (int) $rows->sum('pageviews'));

        return $rows->map(fn ($row) => [
            'device_type' => $row->device_type,
            'pageviews' => (int) $row->pageviews,
            'unique_visitors' => (int) $row->unique_visitors,
            'cta_clicks' => (int) $row->cta_clicks,
            'share' => round(((int) $row->pageviews / $totalPv) * 100, 1),
        ])->all();
    }

    /**
     * @return array<int, array>
     */
    public function dailyTrend(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
                DB::raw("SUM(CASE WHEN event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks"),
                DB::raw("SUM(CASE WHEN event_type = 'tool_action' THEN 1 ELSE 0 END) as tool_actions"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date');

        if ($path) {
            $q->where('path', $path);
        }

        $byDate = $q->get()->keyBy(fn ($row) => (string) $row->date);
        $out = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $row = $byDate->get($key);
            $out[] = [
                'date' => $key,
                'pageviews' => (int) ($row->pageviews ?? 0),
                'unique_visitors' => (int) ($row->unique_visitors ?? 0),
                'cta_clicks' => (int) ($row->cta_clicks ?? 0),
                'tool_actions' => (int) ($row->tool_actions ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array>
     */
    public function topReferrers(Carbon $from, Carbon $to, ?string $path = null, int $limit = 20): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                DB::raw("COALESCE(NULLIF(referrer_host, ''), '(direct)') as referrer_host"),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->where('event_type', SiteVisitorEvent::TYPE_PAGE_VIEW)
            ->groupBy(DB::raw("COALESCE(NULLIF(referrer_host, ''), '(direct)')"))
            ->orderByDesc('pageviews')
            ->limit($limit);

        if ($path) {
            $q->where('path', $path);
        }

        return $q->get()->map(fn ($row) => [
            'referrer_host' => $row->referrer_host,
            'pageviews' => (int) $row->pageviews,
            'unique_visitors' => (int) $row->unique_visitors,
        ])->all();
    }

    /**
     * @return array<int, array>
     */
    public function topCampaigns(Carbon $from, Carbon $to, ?string $path = null, int $limit = 20): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                DB::raw("COALESCE(NULLIF(utm_campaign, ''), '(none)') as utm_campaign"),
                DB::raw("COALESCE(NULLIF(utm_source, ''), '—') as utm_source"),
                DB::raw("COALESCE(NULLIF(utm_medium, ''), '—') as utm_medium"),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
                DB::raw("SUM(CASE WHEN event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($query) {
                $query->whereNotNull('utm_campaign')
                    ->orWhereNotNull('utm_source')
                    ->orWhereNotNull('utm_medium');
            })
            ->groupBy(
                DB::raw("COALESCE(NULLIF(utm_campaign, ''), '(none)')"),
                DB::raw("COALESCE(NULLIF(utm_source, ''), '—')"),
                DB::raw("COALESCE(NULLIF(utm_medium, ''), '—')"),
            )
            ->orderByDesc('pageviews')
            ->limit($limit);

        if ($path) {
            $q->where('path', $path);
        }

        return $q->get()->map(fn ($row) => [
            'utm_campaign' => $row->utm_campaign,
            'utm_source' => $row->utm_source,
            'utm_medium' => $row->utm_medium,
            'pageviews' => (int) $row->pageviews,
            'unique_visitors' => (int) $row->unique_visitors,
            'cta_clicks' => (int) $row->cta_clicks,
        ])->all();
    }

    /**
     * @return array<int, array>
     */
    public function engagement(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                'path',
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("SUM(CASE WHEN event_type = 'scroll_depth' AND scroll_pct >= 25 THEN 1 ELSE 0 END) as scroll_25"),
                DB::raw("SUM(CASE WHEN event_type = 'scroll_depth' AND scroll_pct >= 50 THEN 1 ELSE 0 END) as scroll_50"),
                DB::raw("SUM(CASE WHEN event_type = 'scroll_depth' AND scroll_pct >= 75 THEN 1 ELSE 0 END) as scroll_75"),
                DB::raw("SUM(CASE WHEN event_type = 'scroll_depth' AND scroll_pct >= 90 THEN 1 ELSE 0 END) as scroll_90"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('path')
            ->orderBy('path');

        if ($path) {
            $q->where('path', $path);
        }

        $rows = $q->get();
        $engagedByPath = $this->maxEngagedByPath($from, $to, $path);

        return $rows->map(function ($row) use ($engagedByPath) {
            $pathKey = (string) $row->path;
            $engaged = $engagedByPath[$pathKey] ?? ['avg' => 0, 'max' => 0];
            $pv = max(1, (int) $row->pageviews);

            return [
                'path' => $pathKey,
                'pageviews' => (int) $row->pageviews,
                'avg_engaged_ms' => (int) $engaged['avg'],
                'max_engaged_ms' => (int) $engaged['max'],
                'scroll_25' => (int) $row->scroll_25,
                'scroll_50' => (int) $row->scroll_50,
                'scroll_75' => (int) $row->scroll_75,
                'scroll_90' => (int) $row->scroll_90,
                'scroll_25_rate' => round(((int) $row->scroll_25 / $pv) * 100, 1),
                'scroll_50_rate' => round(((int) $row->scroll_50 / $pv) * 100, 1),
                'scroll_75_rate' => round(((int) $row->scroll_75 / $pv) * 100, 1),
                'scroll_90_rate' => round(((int) $row->scroll_90 / $pv) * 100, 1),
            ];
        })->sortByDesc('avg_engaged_ms')->values()->all();
    }

    /**
     * @return array<int, array>
     */
    public function actions(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                'path',
                'event_type',
                DB::raw("COALESCE(cta_label, action_name, '—') as label"),
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(DISTINCT visitor_hash) as unique_visitors'),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('event_type', [
                SiteVisitorEvent::TYPE_CTA_CLICK,
                SiteVisitorEvent::TYPE_TOOL_ACTION,
            ])
            ->groupBy('path', 'event_type', DB::raw("COALESCE(cta_label, action_name, '—')"))
            ->orderByDesc('total');

        if ($path) {
            $q->where('path', $path);
        }

        return $q->get()->map(fn ($row) => [
            'path' => $row->path,
            'event_type' => $row->event_type,
            'label' => $row->label,
            'total' => (int) $row->total,
            'unique_visitors' => (int) $row->unique_visitors,
        ])->all();
    }

    /**
     * @param  array<int, array>  $rows
     * @param  array<string, int|float>  $overview
     * @return array<int, array>
     */
    private function enrichPathRows(array $rows, array $overview): array
    {
        return array_map(function (array $row) {
            $pv = max(1, (int) ($row['pageviews'] ?? 0));
            $visitors = max(1, (int) ($row['unique_visitors'] ?? 0));

            return array_merge($row, [
                'cta_rate' => round(((int) ($row['cta_clicks'] ?? 0) / $pv) * 100, 1),
                'scroll_50_rate' => round(((int) ($row['scroll_50'] ?? 0) / $pv) * 100, 1),
                'pages_per_visitor' => round($pv / $visitors, 2),
            ]);
        }, $rows);
    }

    /**
     * @param  array<int, array>  $rows
     * @param  array<string, int|float>  $overview
     * @return array<int, array>
     */
    private function enrichSourceRows(array $rows, array $overview): array
    {
        $totalPv = max(1, (int) ($overview['pageviews'] ?? 0));
        if ($totalPv === 1 && $rows !== []) {
            $totalPv = max(1, (int) array_sum(array_column($rows, 'pageviews')));
        }

        return array_map(function (array $row) use ($totalPv) {
            $pv = max(1, (int) ($row['pageviews'] ?? 0));

            return array_merge($row, [
                'share' => round(((int) ($row['pageviews'] ?? 0) / $totalPv) * 100, 1),
                'cta_rate' => round(((int) ($row['cta_clicks'] ?? 0) / $pv) * 100, 1),
            ]);
        }, $rows);
    }

    /**
     * @param  array<int, array>  $rows
     * @param  array<string, int|float>  $overview
     * @return array<int, array>
     */
    private function enrichEngagementRows(array $rows, array $overview): array
    {
        return $rows;
    }

    /**
     * @return array{pageviews:int,cta_clicks:int}|null
     */
    private function overviewFromDailyStats(Carbon $from, Carbon $to, ?string $path): ?array
    {
        if (! $this->hasDailyStatsCoverage($from, $to, $path)) {
            return null;
        }

        $q = SiteVisitorDailyStat::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($path) {
            $q->where('path', $path);
        }

        $row = $q->selectRaw('COALESCE(SUM(pageviews), 0) as pageviews, COALESCE(SUM(cta_clicks), 0) as cta_clicks')
            ->first();

        return [
            'pageviews' => (int) ($row->pageviews ?? 0),
            'cta_clicks' => (int) ($row->cta_clicks ?? 0),
        ];
    }

    private function hasDailyStatsCoverage(Carbon $from, Carbon $to, ?string $path): bool
    {
        $q = SiteVisitorDailyStat::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($path) {
            $q->where('path', $path);
        }

        return $q->exists();
    }

    /**
     * @return array<int, array>
     */
    private function byPathFromDailyStats(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $q = SiteVisitorDailyStat::query()
            ->select([
                'path',
                DB::raw('SUM(pageviews) as pageviews'),
                DB::raw('SUM(cta_clicks) as cta_clicks'),
                DB::raw('SUM(scroll_50_count) as scroll_50'),
                DB::raw('AVG(NULLIF(avg_engaged_ms, 0)) as avg_engaged_ms'),
            ])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('path')
            ->orderByDesc('pageviews');

        if ($path) {
            $q->where('path', $path);
        }

        $rollupRows = $q->get()->keyBy('path');
        $identity = $this->pathIdentityFromEvents($from, $to, $path);
        $engaged = $this->maxEngagedByPath($from, $to, $path);

        $paths = $rollupRows->keys()->merge(collect($identity)->keys())->unique()->values();

        return $paths->map(function ($pathKey) use ($rollupRows, $identity, $engaged) {
            $roll = $rollupRows->get($pathKey);
            $id = $identity[$pathKey] ?? ['unique_visitors' => 0, 'sessions' => 0];
            $eng = $engaged[$pathKey] ?? ['avg' => 0, 'max' => 0];

            return [
                'path' => $pathKey,
                'pageviews' => (int) ($roll->pageviews ?? 0),
                'unique_visitors' => (int) $id['unique_visitors'],
                'sessions' => (int) $id['sessions'],
                'cta_clicks' => (int) ($roll->cta_clicks ?? 0),
                'scroll_50' => (int) ($roll->scroll_50 ?? 0),
                'avg_engaged_ms' => (int) ($eng['avg'] ?: round((float) ($roll->avg_engaged_ms ?? 0))),
            ];
        })->sortByDesc('pageviews')->values()->all();
    }

    /**
     * @return array<int, array>
     */
    private function byPathFromEvents(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                'path',
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN session_hash END) as sessions"),
                DB::raw("SUM(CASE WHEN event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks"),
                DB::raw("SUM(CASE WHEN event_type = 'scroll_depth' AND scroll_pct >= 50 THEN 1 ELSE 0 END) as scroll_50"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('path')
            ->orderByDesc('pageviews');

        if ($path) {
            $q->where('path', $path);
        }

        $engaged = $this->maxEngagedByPath($from, $to, $path);

        return $q->get()->map(function ($row) use ($engaged) {
            $pathKey = (string) $row->path;
            $eng = $engaged[$pathKey] ?? ['avg' => 0, 'max' => 0];

            return [
                'path' => $pathKey,
                'pageviews' => (int) $row->pageviews,
                'unique_visitors' => (int) $row->unique_visitors,
                'sessions' => (int) $row->sessions,
                'cta_clicks' => (int) $row->cta_clicks,
                'scroll_50' => (int) $row->scroll_50,
                'avg_engaged_ms' => (int) $eng['avg'],
            ];
        })->all();
    }

    /**
     * @return array<string, array{unique_visitors:int,sessions:int}>
     */
    private function pathIdentityFromEvents(Carbon $from, Carbon $to, ?string $path): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                'path',
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN session_hash END) as sessions"),
            ])
            ->whereBetween('created_at', [$from, $to])
            ->where('event_type', SiteVisitorEvent::TYPE_PAGE_VIEW)
            ->groupBy('path');

        if ($path) {
            $q->where('path', $path);
        }

        $out = [];
        foreach ($q->get() as $row) {
            $out[(string) $row->path] = [
                'unique_visitors' => (int) $row->unique_visitors,
                'sessions' => (int) $row->sessions,
            ];
        }

        return $out;
    }

    private function averageMaxEngagedMs(Carbon $from, Carbon $to, ?string $path): int
    {
        $byPath = $this->maxEngagedByPath($from, $to, $path);
        if ($byPath === []) {
            return 0;
        }

        $avg = collect($byPath)->avg('avg');

        return (int) round((float) $avg);
    }

    /**
     * @return array<string, array{avg:int,max:int}>
     */
    private function maxEngagedByPath(Carbon $from, Carbon $to, ?string $path): array
    {
        $q = SiteVisitorEvent::query()
            ->select([
                'path',
                'session_hash',
                DB::raw('MAX(engaged_ms) as max_engaged_ms'),
            ])
            ->where('event_type', SiteVisitorEvent::TYPE_HEARTBEAT)
            ->whereNotNull('engaged_ms')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('path', 'session_hash');

        if ($path) {
            $q->where('path', $path);
        }

        $grouped = [];
        foreach ($q->get() as $row) {
            $pathKey = (string) $row->path;
            $grouped[$pathKey][] = (int) $row->max_engaged_ms;
        }

        $out = [];
        foreach ($grouped as $pathKey => $values) {
            $out[$pathKey] = [
                'avg' => (int) round(array_sum($values) / max(1, count($values))),
                'max' => (int) max($values),
            ];
        }

        return $out;
    }
}
