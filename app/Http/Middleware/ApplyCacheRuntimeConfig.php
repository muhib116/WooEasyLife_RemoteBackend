<?php

namespace App\Http\Middleware;

use App\Services\CacheRuntimeConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyCacheRuntimeConfig
{
    public function __construct(
        private CacheRuntimeConfig $runtimeConfig,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->runtimeConfig->applyOverrides();
        } catch (\Throwable) {
            // Install/migrate may run before the settings table exists.
        }

        return $next($request);
    }
}
