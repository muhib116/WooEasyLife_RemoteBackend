<?php

namespace App\Console\Commands;

use App\Services\Seo\GoogleSearchConsoleClient;
use Illuminate\Console\Command;

class SeoGscStatusCommand extends Command
{
    protected $signature = 'seo:gsc-status {--probe : Call Search Console for 1 query row}';

    protected $description = 'Show Google Search Console API configuration and optionally probe the API';

    public function handle(GoogleSearchConsoleClient $gsc): int
    {
        $status = $gsc->configurationStatus();

        $this->table(
            ['Key', 'Value'],
            [
                ['site_url', $status['site_url'] ?: '(missing)'],
                ['auth_mode', $status['auth_mode']],
                ['refresh_token_source', $status['refresh_token_source'] ?: 'missing'],
                ['GOOGLE_CLIENT_ID / SEO_GSC_CLIENT_ID', $status['has_client_id'] ? 'set' : 'missing'],
                ['GOOGLE_CLIENT_SECRET / SEO_GSC_CLIENT_SECRET', $status['has_client_secret'] ? 'set' : 'missing'],
                ['refresh_token', $status['has_refresh_token'] ? 'set' : 'missing'],
                ['SEO_GSC_ACCESS_TOKEN (legacy)', $status['has_static_access_token'] ? 'set' : 'missing'],
                ['ready', $status['ready'] ? 'yes' : 'no'],
            ]
        );

        if (! $status['ready']) {
            $this->warn('Not ready. Preferred setup:');
            $this->line('  1) SEO_GSC_SITE_URL=https://your-verified-property/');
            $this->line('  2) GOOGLE_CLIENT_ID + GOOGLE_CLIENT_SECRET (already used for merchant Google login)');
            $this->line('  3) Admin → Maintenance → Connect Search Console (saves refresh token automatically)');
            $this->line('  Optional: SEO_GSC_REFRESH_TOKEN in .env (only used when no Connect-saved token exists)');
        }

        if (! $this->option('probe')) {
            // Diagnostic only — incomplete env is reported above, not a hard failure.
            return self::SUCCESS;
        }

        if (! $status['ready']) {
            $this->warn('Cannot probe — configuration incomplete.');

            return self::SUCCESS;
        }

        try {
            $payload = $gsc->searchAnalytics([
                'startDate' => now()->subDays(7)->toDateString(),
                'endDate' => now()->subDay()->toDateString(),
                'dimensions' => ['query'],
                'rowLimit' => 3,
            ]);
            $rows = $payload['rows'] ?? [];
            $this->info('Probe OK — '.count($rows).' query row(s) in last 7 days.');
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $this->line('- '.($row['keys'][0] ?? '?').' (clicks '.($row['clicks'] ?? 0).')');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Probe failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
