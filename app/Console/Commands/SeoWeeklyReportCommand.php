<?php

namespace App\Console\Commands;

use App\Services\BlogService;
use App\Services\Seo\GoogleSearchConsoleClient;
use App\Services\SeoMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoWeeklyReportCommand extends Command
{
    protected $signature = 'seo:weekly-report';

    protected $description = 'Weekly SEO health check (sitemap URLs) and optional Google Search Console snapshot';

    public function handle(SeoMetaService $seo, BlogService $blog, GoogleSearchConsoleClient $gsc): int
    {
        $lines = [];
        $lines[] = '# WooEasyLife weekly SEO report';
        $lines[] = 'Generated: '.now()->toDateTimeString();
        $lines[] = '';

        $paths = collect(config('seo.sitemap.paths', []))
            ->pluck('path')
            ->merge(collect($blog->all())->map(fn (array $p) => '/blog/'.($p['slug'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $ok = 0;
        $fail = 0;
        $lines[] = '## Sitemap URL health';

        foreach ($paths as $path) {
            $url = $seo->absoluteUrl((string) $path);
            try {
                $response = Http::timeout(12)->get($url);
                $status = $response->status();
            } catch (\Throwable $e) {
                $status = 0;
                $lines[] = "- FAIL {$url} ({$e->getMessage()})";
                $fail++;
                continue;
            }

            if ($status >= 200 && $status < 400) {
                $lines[] = "- OK {$status} {$url}";
                $ok++;
            } else {
                $lines[] = "- FAIL {$status} {$url}";
                $fail++;
            }
        }

        $lines[] = '';
        $lines[] = "Summary: {$ok} ok, {$fail} failed";
        $lines[] = '';

        $lines[] = '## Google Search Console';
        $lines[] = $this->fetchGscSnapshot($gsc);
        $lines[] = '';
        $lines[] = \App\Support\SeoAuthorityMetrics::reportMarkdown();
        $lines[] = '';
        $lines[] = '## Manual checklist (sitewide)';
        $lines[] = '- Review GSC → Performance → Queries (ফ্রড চেকার, BD fraud checker, SteadFast)';
        $lines[] = '- Request indexing for new URLs if needed';
        $lines[] = '- Confirm APP_URL is production HTTPS';
        $lines[] = '- Authority Sunday SOP: see Step 9 section above (cluster only — no Pathao expand)';

        $dir = storage_path('app/seo');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/weekly-report-'.now()->format('Y-m-d').'.md';
        File::put($file, implode("\n", $lines)."\n");

        Log::info('SEO weekly report written', ['file' => $file, 'ok' => $ok, 'fail' => $fail]);
        $this->info("Report written: {$file}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fetchGscSnapshot(GoogleSearchConsoleClient $gsc): string
    {
        if (! $gsc->configured()) {
            $status = $gsc->configurationStatus();

            return 'Skipped — configure Search Console OAuth: '
                .'SEO_GSC_SITE_URL + GOOGLE_CLIENT_ID + GOOGLE_CLIENT_SECRET + SEO_GSC_REFRESH_TOKEN '
                .'(or legacy SEO_GSC_ACCESS_TOKEN). '
                .'Status: site='.($status['has_site_url'] ? 'yes' : 'no')
                .', client_id='.($status['has_client_id'] ? 'yes' : 'no')
                .', refresh='.($status['has_refresh_token'] ? 'yes' : 'no')
                .', static_token='.($status['has_static_access_token'] ? 'yes' : 'no').'.';
        }

        try {
            $payload = $gsc->searchAnalytics([
                'startDate' => now()->subDays(28)->toDateString(),
                'endDate' => now()->subDay()->toDateString(),
                'dimensions' => ['query'],
                'rowLimit' => 15,
            ]);

            $rows = $payload['rows'] ?? [];
            if ($rows === []) {
                return 'GSC returned 0 query rows for the last 28 days.';
            }

            $out = ['Top queries (28d):'];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $q = $row['keys'][0] ?? '';
                $clicks = $row['clicks'] ?? 0;
                $impr = $row['impressions'] ?? 0;
                $pos = isset($row['position']) ? round((float) $row['position'], 1) : '—';
                $out[] = "- {$q} — clicks {$clicks}, impressions {$impr}, pos {$pos}";
            }

            $cluster = $this->fetchClusterPageSnapshot($gsc);
            if ($cluster !== '') {
                $out[] = '';
                $out[] = $cluster;
            }

            return implode("\n", $out);
        } catch (\Throwable $e) {
            return 'GSC request failed: '.$e->getMessage();
        }
    }

    private function fetchClusterPageSnapshot(GoogleSearchConsoleClient $gsc): string
    {
        $paths = \App\Support\SeoAuthorityMetrics::trackedPaths();
        if ($paths === []) {
            return '';
        }

        try {
            $payload = $gsc->searchAnalytics([
                'startDate' => now()->subDays(28)->toDateString(),
                'endDate' => now()->subDay()->toDateString(),
                'dimensions' => ['page'],
                'rowLimit' => 50,
            ]);
        } catch (\Throwable) {
            return '';
        }

        $want = array_fill_keys($paths, true);
        $matched = [];
        foreach ($payload['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $page = (string) ($row['keys'][0] ?? '');
            $path = parse_url($page, PHP_URL_PATH) ?: '';
            if ($path === '' || ! isset($want[$path])) {
                continue;
            }
            $matched[] = sprintf(
                '- `%s` — clicks %s, impressions %s, pos %s',
                $path,
                $row['clicks'] ?? 0,
                $row['impressions'] ?? 0,
                isset($row['position']) ? round((float) $row['position'], 1) : '—'
            );
        }

        if ($matched === []) {
            return 'Cluster pages (live GSC): no rows matched tracked SteadFast paths yet.';
        }

        return "Cluster pages (live GSC, tracked):\n".implode("\n", $matched);
    }
}
