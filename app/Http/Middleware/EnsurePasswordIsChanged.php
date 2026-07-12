<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->must_change_password ?? false)) {
            return $next($request);
        }

        if ($request->routeIs(
            'portal.password.force',
            'portal.password.force.update',
            'logout',
            'password.update',
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You must change your password before continuing.',
            ], 403);
        }

        return redirect()->route('portal.password.force');
    }
}
