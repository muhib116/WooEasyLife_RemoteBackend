<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Default api-group throttle that skips WordPress plugin update endpoints.
 *
 * Those routes use DedicatedThrottleRequests (alias throttle.named) with
 * plugin_download / plugin_metadata limiters so fraud/get-user traffic on a
 * shared hosting IP cannot 429 plugin updates.
 */
class ApiThrottleUnlessPluginUpdate extends ThrottleRequests
{
    /** @var list<string> */
    private const SKIP_PATHS = [
        'download-plugins',
        'get-metadata',
    ];

    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if ($request->is(...self::SKIP_PATHS)) {
            return $next($request);
        }

        // Preserve previous Kernel behavior: throttle:api (named limiter "api").
        return parent::handle($request, $next, 'api');
    }
}
