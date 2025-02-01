<?php

namespace App\Http\Middleware;

use App\Models\RouteHit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackRouteVisit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $status = null;
        $exceptionMessage = null;

        try {
            /** @var Response $response */
            $response = $next($request);
            $status = $response instanceof Response ? $response->getStatusCode() : 200;
        } catch (Throwable $e) {
            $status = 500;
            $exceptionMessage = $e->getMessage();
        }

        if (Auth::check()) {
            $user = Auth::user();
            $path = $request->path();
            $method = $request->method();

            // Find existing record for user and path
            $routeHit = RouteHit::where('user_id', $user->id)
                ->where('path', $path)
                ->first();

            if ($routeHit) {
                // Increment hit count
                $routeHit->increment('hit_count');
                $routeHit->update(['status' => $status, 'exception' => $exceptionMessage]);
            } else {
                // Create new record
                RouteHit::create([
                    'user_id' => $user->id,
                    'group' => null, // Will be set later
                    'path' => $path,
                    'status' => $status,
                    'method' => $method,
                    'hit_count' => 1,
                    'exception' => $exceptionMessage,
                ]);
            }
        }

        return isset($response) ? $response : response()->json(['error' => 'Internal Server Error'], 500);
    }
}
