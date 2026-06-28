<?php

namespace App\Console\Commands;

use App\Services\SubscriptionExpiryService;
use Illuminate\Console\Command;

class ApplySubscriptionExpiryCommand extends Command
{
    protected $signature = 'subscriptions:apply-expiry
                            {--dry-run : Report what would change without updating records}';

    protected $description = 'Disable expired license tokens and deactivate expired or exhausted subscription plans';

    public function handle(SubscriptionExpiryService $expiryService): int
    {
        if ($this->option('dry-run')) {
            $this->warn('Dry run is not implemented; run without --dry-run to apply changes.');

            return self::SUCCESS;
        }

        $result = $expiryService->apply();

        $this->info('Subscription expiry enforcement complete.');
        $this->table(
            ['Action', 'Count'],
            [
                ['Tokens disabled', $result['tokens_disabled']],
                ['Plans deactivated', $result['plans_deactivated']],
            ]
        );

        return self::SUCCESS;
    }
}
