<?php

namespace App\Console\Commands;

use App\Services\FraudCheck\BulkFraudScheduler;
use App\Services\FraudCheck\MerchantBulkWaitingLane;
use Illuminate\Console\Command;

class FillWaitingBulkFraudRowsCommand extends Command
{
    protected $signature = 'fraud-check:fill-waiting {--seconds=50 : How long to keep asking couriers} {--manual : Run even when automatic checking is off}';

    protected $description = 'Ask the couriers that bulk fraud rows still wait for, each when it has room';

    public function handle(MerchantBulkWaitingLane $lane, BulkFraudScheduler $scheduler): int
    {
        $scheduler->beat();

        if (! $scheduler->allows((bool) $this->option('manual'))) {
            $this->info('Automatic background checking is off.');

            return self::SUCCESS;
        }

        $stats = $lane->run(max(1, min(300, (int) $this->option('seconds'))));

        if ($this->option('manual')) {
            $scheduler->finish();
        }

        if ($stats['asked'] > 0) {
            $this->info("Asked {$stats['asked']} couriers. {$stats['finished']} rows stopped waiting.");
        }

        return self::SUCCESS;
    }
}
