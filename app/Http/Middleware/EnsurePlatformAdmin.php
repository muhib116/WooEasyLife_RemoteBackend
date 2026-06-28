<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->role, ['user', 'merchant_staff'], true)) {
            return redirect()->route('portal.dashboard');
        }

        if (! $user || $user->role !== 'admin') {
            abort(403, 'Platform admin access required.');
        }

        return $next($request);
    }
}
