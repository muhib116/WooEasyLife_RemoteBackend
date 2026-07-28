<?php

namespace App\Providers;

use App\Services\BlogAi\BlogAiRuntimeConfig;
use App\Services\CacheRuntimeConfig;
use App\Services\OrderIntelligence\FraudCheckRuntimeConfig;
use Illuminate\Support\Facades\Schema;
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

        // Do not Vite::prefetch() sitewide. Prefetch dumps hundreds of admin/auth
        // chunk URLs into every marketing HTML response; Semrush then flags those
        // URLs as "broken JS/CSS" after each hashed deploy removes the prior build.

        try {
            $this->app->make(FraudCheckRuntimeConfig::class)->applyOverrides();
        } catch (\Throwable) {
            // Table may not exist yet during early migrate / install.
        }

        try {
            $this->app->make(CacheRuntimeConfig::class)->applyOverrides();
        } catch (\Throwable) {
            // Table may not exist yet during early migrate / install.
        }

        try {
            $this->app->make(BlogAiRuntimeConfig::class)->applyOverrides();
        } catch (\Throwable) {
            // Table may not exist yet during early migrate / install.
        }
    }
}
