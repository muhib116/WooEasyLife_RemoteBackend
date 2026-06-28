<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DomainNormalizationService;
use Illuminate\Console\Command;

class NormalizeDomainsCommand extends Command
{
    protected $signature = 'domains:normalize
                            {--user-id= : Normalize domains for a single merchant by ID}
                            {--chunk=100 : Merchants processed per batch}
                            {--dry-run : Report changes without writing to the database}';

    protected $description = 'Normalize legacy domain strings to lowercase hostnames (no scheme/path) across plans, licenses, SMS, and websites';

    public function handle(DomainNormalizationService $normalizationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $userId = $this->option('user-id');

        if ($dryRun) {
            $this->warn('Dry run mode — no database writes will be performed.');
        }

        $totals = [
            'merchants' => 0,
            'packages_updated' => 0,
            'tokens_updated' => 0,
            'businesses_updated' => 0,
            'sms_balances_updated' => 0,
            'sms_recharges_updated' => 0,
            'websites_updated' => 0,
            'websites_merged' => 0,
            'skipped_invalid' => 0,
        ];

        $processUser = function (User $user) use ($normalizationService, $dryRun, &$totals) {
            $stats = $normalizationService->normalizeUser($user, $dryRun);
            $totals['merchants']++;

            foreach ($stats as $key => $value) {
                $totals[$key] += $value;
            }
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
        $this->info('Domain normalization complete.');
        $this->table(
            ['Metric', 'Count'],
            collect($totals)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($dryRun) {
            $this->comment('Re-run without --dry-run to apply these changes.');
        } else {
            $this->comment('Run php artisan domains:audit --severity=high to verify alignment.');
        }

        return self::SUCCESS;
    }
}
