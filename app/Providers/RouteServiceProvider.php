<?php

namespace App\Providers;

use App\Http\Controllers\App\CachedBuildAssetController;
use App\Http\Controllers\App\RobotsController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    public const PORTAL_HOME = '/portal';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // No session/cookies middleware — keep Cache-Control intact for hashed Vite assets.
            Route::get('/build/assets/{file}', CachedBuildAssetController::class)
                ->where('file', '[A-Za-z0-9._\-]+')
                ->name('build.assets.show');

            // Googlebot polls robots.txt independently of page crawls. Keep it off the
            // web/Inertia/session stack so a slow DB or deploy cookie layer cannot 5xx it.
            Route::get('/robots.txt', RobotsController::class)
                ->name('robots');

            Route::middleware('api')
                // ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
