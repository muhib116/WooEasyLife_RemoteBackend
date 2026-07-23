<?php

namespace App\Console\Commands;

use App\Services\Seo\GoogleAnalyticsClient;
use Illuminate\Console\Command;

class SeoGaStatusCommand extends Command
{
    protected $signature = 'seo:ga-status {--probe : Call GA4 Data API for sample sessions}';

    protected $description = 'Show Google Analytics (GA4) API configuration and optionally probe the API';

    public function handle(GoogleAnalyticsClient $ga): int
    {
        $status = $ga->configurationStatus();

        $this->table(
            ['Key', 'Value'],
            [
                ['property_id', $status['property_id'] ?: '(missing)'],
                ['auth_mode', $status['auth_mode']],
                ['refresh_token_source', $status['refresh_token_source'] ?: 'missing'],
                ['GOOGLE_CLIENT_ID / SEO_GA_CLIENT_ID', $status['has_client_id'] ? 'set' : 'missing'],
                ['GOOGLE_CLIENT_SECRET / SEO_GA_CLIENT_SECRET', $status['has_client_secret'] ? 'set' : 'missing'],
                ['refresh_token', $status['has_refresh_token'] ? 'set' : 'missing'],
                ['SEO_GA_ACCESS_TOKEN (legacy)', $status['has_static_access_token'] ? 'set' : 'missing'],
                ['ready', $status['ready'] ? 'yes' : 'no'],
            ]
        );

        if (! $status['ready']) {
            $this->warn('Not ready. Preferred setup:');
            $this->line('  1) Save GA4 Property ID in Admin → Blog AI Settings (or SEO & Learning)');
            $this->line('  2) GOOGLE_CLIENT_ID + GOOGLE_CLIENT_SECRET (already used for merchant Google login)');
            $this->line('  3) Admin → Maintenance → Connect Google Analytics (saves refresh token automatically)');
            $this->line('  Optional: SEO_GA_PROPERTY_ID / SEO_GA_REFRESH_TOKEN in .env (only when no admin-saved values)');
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
            $payload = $ga->runReport([
                'dateRanges' => [[
                    'startDate' => '7daysAgo',
                    'endDate' => 'yesterday',
                ]],
                'dimensions' => [['name' => 'date']],
                'metrics' => [
                    ['name' => 'sessions'],
                    ['name' => 'activeUsers'],
                ],
                'limit' => 3,
                'orderBys' => [[
                    'dimension' => ['dimensionName' => 'date'],
                    'desc' => true,
                ]],
            ]);
            $rows = $payload['rows'] ?? [];
            $this->info('Probe OK — '.count($rows).' date row(s) in last 7 days.');
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $date = $row['dimensionValues'][0]['value'] ?? '?';
                $sessions = $row['metricValues'][0]['value'] ?? '0';
                $users = $row['metricValues'][1]['value'] ?? '0';
                $this->line("- {$date}: sessions {$sessions}, users {$users}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Probe failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
