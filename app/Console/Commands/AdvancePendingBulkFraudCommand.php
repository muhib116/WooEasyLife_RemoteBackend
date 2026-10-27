<?php

namespace App\Console\Commands;

use App\Services\FraudCheck\BulkFraudScheduler;
use App\Services\FraudCheck\MerchantBulkFraudCheck;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AdvancePendingBulkFraudCommand extends Command
{
    protected $signature = 'fraud-check:advance-pending {--seconds=50 : How long to keep checking numbers} {--worker : Check inside this process} {--manual : Run even when automatic checking is off}';

    protected $description = 'Check bulk fraud numbers whose page is no longer open';

    public function handle(MerchantBulkFraudCheck $bulk, BulkFraudScheduler $scheduler): int
    {
        $scheduler->beat();

        if (! $scheduler->allows((bool) $this->option('manual'))) {
            $this->info('Automatic background checking is off.');

            return self::SUCCESS;
        }

        $seconds = max(1, min(300, (int) $this->option('seconds')));

        if ($this->option('worker') || app()->runningUnitTests() || $bulk->workers() < 2) {
            return $this->check($bulk, $seconds);
        }

        if (! $bulk->hasUnattendedWork()) {
            return self::SUCCESS;
        }

        $processes = [];

        for ($worker = 0; $worker < $bulk->workers(); $worker++) {
            $command = [
                PHP_BINARY,
                base_path('artisan'),
                'fraud-check:advance-pending',
                '--seconds='.$seconds,
                '--worker',
            ];

            if ($this->option('manual')) {
                $command[] = '--manual';
            }

            $process = new Process($command, base_path());
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
