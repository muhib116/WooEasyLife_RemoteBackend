<?php

namespace App\Console\Commands;

use App\Services\BlogService;
use App\Services\SeoMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoWeeklyReportCommand extends Command
{
    protected $signature = 'seo:weekly-report';

    protected $description = 'Weekly SEO health check (sitemap URLs) and optional Google Search Console snapshot';

    public function handle(SeoMetaService $seo, BlogService $blog): int
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

        $gsc = $this->fetchGscSnapshot();
        $lines[] = '## Google Search Console';
        $lines[] = $gsc;
        $lines[] = '';
        $lines[] = '## Manual checklist';
        $lines[] = '- Review GSC → Performance → Queries (ফ্রড চেকার, BD fraud checker)';
        $lines[] = '- Request indexing for new URLs if needed';
        $lines[] = '- Confirm APP_URL is production HTTPS';

        $dir = storage_path('app/seo');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/weekly-report-'.now()->format('Y-m-d').'.md';
        File::put($file, implode("\n", $lines)."\n");

        Log::info('SEO weekly report written', ['file' => $file, 'ok' => $ok, 'fail' => $fail]);
        $this->info("Report written: {$file}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fetchGscSnapshot(): string
    {
        $siteUrl = config('seo.gsc.site_url');
        $token = config('seo.gsc.access_token');

        if (! filled($siteUrl) || ! filled($token)) {
            return 'Skipped — set SEO_GSC_SITE_URL and SEO_GSC_ACCESS_TOKEN to pull query metrics automatically.';
        }

        $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
            .rawurlencode((string) $siteUrl)
            .'/searchAnalytics/query';

        try {
            $response = Http::withToken((string) $token)
                ->timeout(20)
                ->post($endpoint, [
                    'startDate' => now()->subDays(28)->toDateString(),
                    'endDate' => now()->subDay()->toDateString(),
                    'dimensions' => ['query'],
                    'rowLimit' => 15,
                ]);

            if (! $response->successful()) {
                return 'GSC API error HTTP '.$response->status().': '.$response->body();
            }

            $rows = $response->json('rows') ?? [];
            if ($rows === []) {
                return 'GSC returned 0 query rows for the last 28 days.';
            }

            $out = ["Top queries (28d):"];
            foreach ($rows as $row) {
                $q = $row['keys'][0] ?? '';
                $clicks = $row['clicks'] ?? 0;
                $impr = $row['impressions'] ?? 0;
                $out[] = "- {$q} — clicks {$clicks}, impressions {$impr}";
            }

            return implode("\n", $out);
        } catch (\Throwable $e) {
            return 'GSC request failed: '.$e->getMessage();
        }
    }
}
