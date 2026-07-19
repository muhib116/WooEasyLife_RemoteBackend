<?php

namespace App\Console\Commands;

use App\Services\Marketing\CourierPublicRatesService;
use Illuminate\Console\Command;

class SyncCourierPublicRatesCommand extends Command
{
    protected $signature = 'courier:sync-public-rates {--force : Run even if recently synced}';

    protected $description = 'Sync public courier rate cards for the SEO charge calculator (Steadfast daily; Pathao when configured)';

    public function handle(CourierPublicRatesService $rates): int
    {
        $this->info('Syncing public courier rates…');

        $results = $rates->syncAll();

        foreach ($results as $courier => $result) {
            $ok = (bool) ($result['success'] ?? false);
            $msg = (string) ($result['message'] ?? '');
            if ($ok) {
                $this->info(strtoupper($courier).': '.$msg);
            } else {
                $this->warn(strtoupper($courier).': '.$msg);
            }
        }

        $anyOk = collect($results)->contains(fn ($r) => (bool) ($r['success'] ?? false));

        return $anyOk ? self::SUCCESS : self::FAILURE;
    }
}
