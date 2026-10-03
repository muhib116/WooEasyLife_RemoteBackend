<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Dedicated throttle class for plugin update routes.
 *
 * Kept as a subclass (not the stock ThrottleRequests alias) so it remains
 * distinct from the api-group limiter wiring.
 */
class NamedThrottleRequests extends ThrottleRequests
{
}
