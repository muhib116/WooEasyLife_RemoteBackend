<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\SiteSeoGscSyncService;
use App\Services\Analytics\SiteVisitorReportingService;
use App\Services\Analytics\SiteVisitorTracker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SiteVisitorsAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Visitors/Index');
    }

    public function report(
        Request $request,
        SiteVisitorReportingService $reporting,
        SiteVisitorTracker $tracker,
    ): JsonResponse {
        [$from, $to, $path] = $this->resolveFilters($request, $tracker);
        $type = $request->validate([
            'type' => ['nullable', 'string', 'in:overview,by_path,by_source,engagement,actions,devices,daily,referrers,keywords'],
        ])['type'] ?? 'by_path';

        $result = $reporting->report($type, $from, $to, $path);

        return response()->json([
            'ok' => true,
            'type' => $result['type'],
            'overview' => $result['overview'],
            'rows' => $result['rows'],
            'meta' => $result['meta'] ?? null,
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'path' => $path,
            ],
        ]);
    }

    public function insights(
        Request $request,
        SiteVisitorReportingService $reporting,
        SiteVisitorTracker $tracker,
    ): JsonResponse {
        [$from, $to, $path] = $this->resolveFilters($request, $tracker);

        return response()->json([
            'ok' => true,
            'insights' => $reporting->insights($from, $to, $path),
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'path' => $path,
            ],
        ]);
    }

    public function seo(
        Request $request,
        SiteVisitorReportingService $reporting,
        SiteVisitorTracker $tracker,
    ): JsonResponse {
        [, , $path] = $this->resolveFilters($request, $tracker);

        return response()->json([
            'ok' => true,
            'seo' => $reporting->seoPanel($path, 20),
            'filters' => [
                'path' => $path,
            ],
        ]);
    }

    public function syncGsc(SiteSeoGscSyncService $sync): JsonResponse
    {
        if (! Cache::add('site-visitors:gsc-sync-lock', 1, now()->addMinutes(2))) {
            return response()->json([
                'ok' => false,
                'result' => [
                    'configured' => true,
                    'queries_synced' => 0,
                    'pages_synced' => 0,
                    'error' => 'Sync already running. Try again in a minute.',
                ],
                'seo' => $sync->seoPanel(null, 20),
            ], 429);
        }

        try {
            $result = $sync->sync();
        } finally {
            Cache::forget('site-visitors:gsc-sync-lock');
        }

        $safeError = null;
        if (! empty($result['error'])) {
            $safeError = ($result['error'] === 'missing_table')
                ? 'missing_table'
                : 'GSC sync failed. Check server logs.';
        }

        return response()->json([
            'ok' => ($result['configured'] ?? false) && $safeError === null,
            'result' => [
                'configured' => (bool) ($result['configured'] ?? false),
                'queries_synced' => (int) ($result['queries_synced'] ?? 0),
                'pages_synced' => (int) ($result['pages_synced'] ?? 0),
                'skipped' => (bool) ($result['skipped'] ?? false),
                'error' => $safeError,
            ],
            'seo' => $sync->seoPanel(null, 20),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string|null}
     */
    private function resolveFilters(Request $request, SiteVisitorTracker $tracker): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'path' => ['nullable', 'string', 'max:500'],
        ]);

        $from = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->subDays(27)->startOfDay();
        $to = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $maxDays = max(1, (int) config('site_visitors.max_report_range_days', 90));
        if ($from->diffInDays($to) + 1 > $maxDays) {
            $from = $to->copy()->subDays($maxDays - 1)->startOfDay();
        }

        $path = null;
        if (! empty($validated['path'])) {
            $path = $tracker->normalizePath($validated['path']);
        }

        return [$from, $to, $path];
    }
}
