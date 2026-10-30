<?php

namespace App\Http\Middleware;

use App\Support\PanelSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartitionPanelSession
{
    /**
     * Give the merchant portal its own session cookie so an admin session
     * in the same browser is left signed in.
     */
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.cookie' => PanelSession::cookieNameFor($request),
        ]);

        return $next($request);
    }
}
