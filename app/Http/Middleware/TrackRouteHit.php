<?php

namespace App\Http\Middleware;

use App\Models\RouteHit;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackRouteHit
{
    use \App\Traits\Util;

    /**
     * Handle an incoming request.
     *
     * API / plugin traffic skips the sync route_hits write (hot path).
     * Web analytics still record after the response is sent to the client.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $error = null;
        $response = null;

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Never turn robots/sitemap into JSON 500 — Googlebot needs plain failure or success.
            if ($this->shouldSkipTracking($request)) {
                throw $e;
            }
            $error = $e->getMessage();
            $response = response()->json(['error' => 'Server Error'], 500);
        }

        if ($this->shouldSkipTracking($request)) {
            return $response;
        }

        $userId = Auth::id() ?? null;
        $path = $request->path();
        $domain = $this->getRequestDomain();
        $status = $response->getStatusCode();
        $date = Carbon::today()->toDateString();

        // Defer DB write until after the response is sent (web only).
        dispatch(function () use ($userId, $path, $domain, $status, $date, $error) {
            try {
                RouteHit::updateOrInsert(
                    [
                        'path' => $path,
                        'domain' => $domain,
                        'status' => $status,
                        'created_at' => $date,
                    ],
                    [
                        'user_id' => $userId,
                        'hit_count' => DB::raw('hit_count + 1'),
                        'updated_at' => now(),
                        'error' => $error,
                    ]
                );
            } catch (\Throwable) {
                // Optional: analytics must never break the request.
            }
        })->afterResponse();

        return $response;
    }

    private function shouldSkipTracking(Request $request): bool
    {
        // Plugin + public JSON APIs: never pay analytics write cost on the hot path.
        return $request->is('api', 'api/*')
            || $request->is('public/*')
            || $request->is('api/webhooks/*')
            || $request->is('robots.txt', 'sitemap.xml', 'llms.txt');
    }
}
