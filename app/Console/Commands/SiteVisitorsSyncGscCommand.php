<?php

namespace App\Console\Commands;

use App\Services\Analytics\SiteSeoGscSyncService;
use Illuminate\Console\Command;

class SiteVisitorsSyncGscCommand extends Command
{
    protected $signature = 'site-visitors:sync-gsc';

    protected $description = 'Sync site-wide Google Search Console keywords and page metrics for Visitors SEO';

    public function handle(SiteSeoGscSyncService $sync): int
    {
        $result = $sync->sync();

        if (! empty($result['skipped']) && ($result['error'] ?? null) === 'missing_table') {
            $this->warn('Missing site GSC tables — run migrations.');

            return self::FAILURE;
        }

        if (empty($result['configured'])) {
            $this->warn('GSC is not configured. Connect Search Console in admin maintenance.');

            return self::SUCCESS;
        }

        if (! empty($result['error'])) {
            $this->error('Sync error: '.$result['error']);
        }

        $this->info(sprintf(
            'Synced %d query×page rows and %d landing pages.',
            (int) ($result['queries_synced'] ?? 0),
            (int) ($result['pages_synced'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
