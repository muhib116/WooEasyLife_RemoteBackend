<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestScheduler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:scheduler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'A test scheduler that runs every minute';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Log::info('TestScheduler is running at ' . now());
    }
}
