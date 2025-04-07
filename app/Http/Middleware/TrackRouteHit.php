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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $error = null;
        $response = null;

        try {
            // $response->status();
            $response = $next($request);
        } catch (\Throwable $e) {
            $error = $e->getMessage(); // Capture error
            $response = response()->json(['error' => 'Server Error'], 500);
        }

        try {
            $userId = Auth::id() ?? 0;
            // $group = $request->route()?->getAction('prefix') ?? null;
            $path = $request->path();
            $domain = $this->getRequestDomain();
            $status = $response->getStatusCode();
            $date = Carbon::today()->toDateString();

            RouteHit::updateOrInsert(
                [
                    // 'group'   => $group,
                    'path'    => $path,
                    'domain'  => $domain,
                    'status'  => $status,
                    'created_at'    => $date,
                ],
                [
                    'user_id' => $userId,
                    'hit_count' => DB::raw('hit_count + 1'),
                    'updated_at' => now(),
                    'error' => $error,
                ]
            );
            // dd($rec);
        } catch (\Throwable $e) {
            // dd($e->getMessage());
            // Optional: log failure to insert/update
        }

        return $response;
    }
}
