<?php

namespace App\Console\Commands;

use App\Services\FraudCheck\MerchantBulkFraudCheck;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AdvancePendingBulkFraudCommand extends Command
{
    protected $signature = 'fraud-check:advance-pending {--seconds=50 : How long to keep checking numbers} {--worker : Check inside this process}';

    protected $description = 'Check bulk fraud numbers whose page is no longer open';

    public function handle(MerchantBulkFraudCheck $bulk): int
    {
        $seconds = max(1, min(300, (int) $this->option('seconds')));

        if ($this->option('worker') || app()->runningUnitTests() || $bulk->workers() < 2) {
            return $this->check($bulk, $seconds);
        }

        if (! $bulk->hasUnattendedWork()) {
            return self::SUCCESS;
        }

        $processes = [];

        for ($worker = 0; $worker < $bulk->workers(); $worker++) {
            $process = new Process([
                PHP_BINARY,
                base_path('artisan'),
                'fraud-check:advance-pending',
                '--seconds='.$seconds,
                '--worker',
            ], base_path());
            $process->setTimeout($seconds + 180);
            $process->start();
            $processes[] = $process;
        }

        $failed = false;

        foreach ($processes as $process) {
            $process->wait();

            if (! $process->isSuccessful()) {
                $failed = true;
                $this->error(trim($process->getErrorOutput()."\n".$process->getOutput()));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(MerchantBulkFraudCheck $bulk, int $seconds): int
    {
        $stats = $bulk->runUnattended($seconds);

        if ($stats['checked'] > 0) {
            $this->info("Checked {$stats['checked']} numbers.");
        }

        return self::SUCCESS;
    }
}
