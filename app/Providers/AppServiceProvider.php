<?php

namespace App\Providers;

use App\Services\OrderIntelligence\FraudCheckRuntimeConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // MySQL utf8mb4 index limit (1000 bytes on older MariaDB/MySQL)
        Schema::defaultStringLength(191);

        Vite::prefetch(3);

        try {
            $this->app->make(FraudCheckRuntimeConfig::class)->applyOverrides();
        } catch (\Throwable) {
            // Table may not exist yet during early migrate / install.
        }
    }
}
