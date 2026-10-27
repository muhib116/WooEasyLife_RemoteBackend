<?php

namespace App\Console\Commands;

use App\Services\FraudCheck\MerchantBulkWaitingLane;
use Illuminate\Console\Command;

class FillWaitingBulkFraudRowsCommand extends Command
{
    protected $signature = 'fraud-check:fill-waiting {--seconds=50 : How long to keep asking couriers}';

    protected $description = 'Ask the couriers that bulk fraud rows still wait for, each when it has room';

    public function handle(MerchantBulkWaitingLane $lane): int
    {
        $stats = $lane->run(max(1, min(300, (int) $this->option('seconds'))));

        if ($stats['asked'] > 0) {
            $this->info("Asked {$stats['asked']} couriers. {$stats['finished']} rows stopped waiting.");
        }

        return self::SUCCESS;
    }
}
