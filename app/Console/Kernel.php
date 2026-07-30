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
        $schedule->command('messenger:retry-webhook-forwards')->everyMinute();
        $schedule->command('seo:weekly-report')
            ->weeklyOn(0, '09:00') // Sunday — Step 9 authority metrics (Asia/Dhaka)
            ->timezone('Asia/Dhaka');
        $schedule->command('blog:build-learning-insights')
            ->dailyAt('02:15')
            ->timezone('Asia/Dhaka');
        $schedule->command('site-visitors:rollup')
            ->dailyAt('02:30')
            ->timezone('Asia/Dhaka');
        $schedule->command('site-visitors:sync-gsc')
            ->dailyAt('02:45')
            ->timezone('Asia/Dhaka');
        $schedule->command('courier:sync-public-rates')
            ->dailyAt('03:10')
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
