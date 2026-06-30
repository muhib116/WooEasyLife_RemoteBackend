<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WebsiteSyncService;
use Illuminate\Database\Seeder;

/**
 * Backfill websites rows and website_id links from existing package, license, and business domains.
 *
 * Idempotent — safe to run on live data and after DemoDataSeeder.
 *
 * Usage:
 *   php artisan db:seed --class=WebsiteSeeder
 *   php artisan websites:backfill
 */
class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $websiteSync = app(WebsiteSyncService::class);

        $totals = [
            'merchants' => 0,
            'websites_created' => 0,
            'websites_existing' => 0,
            'packages_linked' => 0,
            'tokens_linked' => 0,
        ];

        User::query()
            ->where('role', 'user')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($websiteSync, &$totals) {
                foreach ($users as $user) {
                    $stats = $websiteSync->backfillUser($user);
                    $totals['merchants']++;
                    $totals['websites_created'] += $stats['websites_created'];
                    $totals['websites_existing'] += $stats['websites_existing'];
                    $totals['packages_linked'] += $stats['packages_linked'];
                    $totals['tokens_linked'] += $stats['tokens_linked'];
                }
            });

        if (! $this->command) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Website backfill complete.');
        $this->command->table(
            ['Metric', 'Count'],
            collect($totals)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );
    }
}
