<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WebsiteSyncService;
use Illuminate\Console\Command;

class BackfillWebsitesCommand extends Command
{
    protected $signature = 'websites:backfill
                            {--user-id= : Backfill a single merchant by ID}
                            {--chunk=100 : Number of merchants processed per batch}
                            {--dry-run : Report changes without writing to the database}';

    protected $description = 'Backfill websites table and website_id links from existing domain data (safe for live, idempotent)';

    public function handle(WebsiteSyncService $websiteSync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $userId = $this->option('user-id');

        if ($dryRun) {
            $this->warn('Dry run mode — no database writes will be performed.');
        }

        $totals = [
            'merchants' => 0,
            'websites_created' => 0,
            'websites_existing' => 0,
            'packages_linked' => 0,
            'tokens_linked' => 0,
        ];

        $processUser = function (User $user) use ($websiteSync, $dryRun, &$totals) {
            $stats = $websiteSync->backfillUser($user, $dryRun);
            $totals['merchants']++;
            $totals['websites_created'] += $stats['websites_created'];
            $totals['websites_existing'] += $stats['websites_existing'];
            $totals['packages_linked'] += $stats['packages_linked'];
            $totals['tokens_linked'] += $stats['tokens_linked'];
        };

        if ($userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                $this->error("Merchant #{$userId} not found.");

                return self::FAILURE;
            }

            $processUser($user);
        } else {
            User::query()
                ->where('role', 'user')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($users) use ($processUser) {
                    foreach ($users as $user) {
                        $processUser($user);
                    }
                });
        }

        $this->newLine();
        $this->info('Backfill complete.');
        $this->table(
            ['Metric', 'Count'],
            collect($totals)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($dryRun) {
            $this->comment('Re-run without --dry-run to apply these changes.');
        }

        return self::SUCCESS;
    }
}
