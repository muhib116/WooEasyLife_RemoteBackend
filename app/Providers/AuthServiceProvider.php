<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Auth\PanelSessionGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::extend('session', function ($app, string $name, array $config) {
            $guard = new PanelSessionGuard(
                $name,
                $app['auth']->createUserProvider($config['provider'] ?? null),
                $app['session.store'],
                null,
                null,
                (bool) $app['config']->get('hashing.rehash_on_login', true),
                (int) $app['config']->get('auth.timebox_duration', 200000),
            );

            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            if (isset($config['remember'])) {
                $guard->setRememberDuration($config['remember']);
            }

            return $guard;
        });
    }
}
