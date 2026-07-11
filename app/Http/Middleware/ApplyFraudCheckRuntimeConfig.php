<?php

namespace App\Http\Middleware;

use App\Services\OrderIntelligence\FraudCheckRuntimeConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyFraudCheckRuntimeConfig
{
    public function __construct(
        private FraudCheckRuntimeConfig $runtimeConfig,
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
