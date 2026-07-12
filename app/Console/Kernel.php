<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        if (app()->environment('local')) {
            $schedule->command('test:scheduler')->twiceDaily();
        }
        $schedule->command('subscriptions:apply-expiry')->dailyAt('07:55');
        $schedule->command('subscriptions:check-alerts')->dailyAt('08:00');
        $schedule->command('subscriptions:notify')->dailyAt('08:05');
        $schedule->command('courier:retry-webhook-forwards')->everyMinute();
        $schedule->command('seo:weekly-report')
            ->weeklyOn(1, '09:00')
            ->timezone('Asia/Dhaka');
            // ->dailyAt('00:30')
            // ->timezone('Asia/Dhaka');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
