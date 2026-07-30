<?php

namespace App\Support;

use App\Models\SiteGscPageMetric;
use App\Models\SiteGscQueryMetric;
use Illuminate\Support\Facades\Schema;

/**
 * Step 9 Sunday metrics helpers for the active authority cluster.
 */
final class SeoAuthorityMetrics
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return (array) config('seo_authority_metrics', []);
    }

    /**
     * @return list<string>
     */
    public static function trackedPaths(): array
    {
        return array_values(array_filter(array_map(
            static fn ($p) => trim((string) $p),
            self::config()['tracked_paths'] ?? []
        )));
    }

    /**
     * @return list<string>
     */
    public static function sundayChecklist(): array
    {
        return array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            self::config()['sunday_checklist'] ?? []
        )));
    }

    /**
     * @return list<array{key: string, label: string, goal: string}>
     */
    public static function metricGoals(): array
    {
        $out = [];
        foreach (self::config()['metrics'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'key' => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'goal' => (string) ($row['goal'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Markdown section for seo:weekly-report (DB snapshot when synced).
     */
    public static function reportMarkdown(): string
    {
        $cfg = self::config();
        $lines = [];
        $lines[] = '## Step 9 — Authority cluster metrics (SteadFast)';
        $lines[] = 'Pillar: '.($cfg['pillar_path'] ?? '/steadfast-fraud-check');
        $lines[] = 'Window: last '.(int) ($cfg['compare_window_days'] ?? 28).' days (trend vs prior week — not daily ranks)';
        $lines[] = '';

        $lines[] = '### Metric goals';
        foreach (self::metricGoals() as $m) {
            if ($m['label'] === '') {
                continue;
            }
            $lines[] = '- **'.$m['label'].':** '.$m['goal'];
        }
        $lines[] = '';

        $lines[] = '### Sunday checklist';
        foreach (self::sundayChecklist() as $item) {
            $lines[] = '- [ ] '.$item;
        }
        $lines[] = '';

        $pageBlock = self::pageMetricsMarkdown();
        if ($pageBlock !== '') {
            $lines[] = $pageBlock;
        }

        $queryBlock = self::queryMetricsMarkdown();
        if ($queryBlock !== '') {
            $lines[] = $queryBlock;
        }

        $lines[] = '### Actions';
        foreach ($cfg['actions'] ?? [] as $key => $text) {
            $lines[] = '- **'.$key.':** '.$text;
        }
        $lines[] = '';
        $lines[] = '_Do not expand to Pathao until Step 10 win. Stay inside SteadFast cluster._';

        return implode("\n", $lines);
    }

    private static function pageMetricsMarkdown(): string
    {
        if (! Schema::hasTable('site_gsc_page_metrics')) {
            return "### Cluster pages (GSC DB)\nSkipped — `site_gsc_page_metrics` missing. Run migrations + `site-visitors:sync-gsc`.\n";
        }

        $paths = self::trackedPaths();
        if ($paths === []) {
            return '';
        }

        $rows = SiteGscPageMetric::query()
            ->whereIn('path', $paths)
            ->orderByDesc('clicks_28d')
            ->limit(25)
            ->get(['path', 'clicks_28d', 'impressions_28d', 'ctr_28d', 'position_28d']);

        if ($rows->isEmpty()) {
            return "### Cluster pages (GSC DB)\nNo synced rows yet for tracked paths. Run `php artisan site-visitors:sync-gsc` after GSC connect.\n";
        }

        $lines = ['### Cluster pages (GSC DB, 28d)', '| Path | Clicks | Impr | CTR | Pos |', '|---|---:|---:|---:|---:|'];
        foreach ($rows as $row) {
            $ctr = $row->ctr_28d !== null ? round((float) $row->ctr_28d * 100, 1).'%' : '—';
            $pos = $row->position_28d !== null ? round((float) $row->position_28d, 1) : '—';
            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s |',
                $row->path,
                $row->clicks_28d ?? 0,
                $row->impressions_28d ?? 0,
                $ctr,
                $pos
            );
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    private static function queryMetricsMarkdown(): string
    {
        if (! Schema::hasTable('site_gsc_query_metrics')) {
            return "### Cluster queries (GSC DB)\nSkipped — `site_gsc_query_metrics` missing.\n";
        }

        $needles = array_values(array_filter(array_map(
            static fn ($n) => mb_strtolower(trim((string) $n)),
            self::config()['query_needles'] ?? []
        )));

        if ($needles === []) {
            return '';
        }

        $query = SiteGscQueryMetric::query()->orderByDesc('clicks_28d')->limit(200);
        $matched = $query->get()->filter(function (SiteGscQueryMetric $row) use ($needles) {
            $q = mb_strtolower((string) $row->query);
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($q, $needle)) {
                    return true;
                }
            }

            return false;
        })->take(15);

        if ($matched->isEmpty()) {
            return "### Cluster queries (GSC DB)\nNo matching SteadFast/fraud queries in synced keywords yet.\n";
        }

        $lines = ['### Cluster queries (GSC DB, matched needles)', '| Query | Clicks | Impr | Pos |', '|---|---:|---:|---:|'];
        foreach ($matched as $row) {
            $pos = $row->position_28d !== null ? round((float) $row->position_28d, 1) : '—';
            $lines[] = sprintf(
                '| %s | %s | %s | %s |',
                str_replace('|', '/', (string) $row->query),
                $row->clicks_28d ?? 0,
                $row->impressions_28d ?? 0,
                $pos
            );
        }
        $lines[] = '';

        return implode("\n", $lines);
    }
}
