<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateTokenDomain
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!env('APP_SKIP_DOMAIN')) {
                $token = $request->bearerToken();
                $requestDomain = $request->getUri();
                $user = Auth::user();
                $parsedUrl = parse_url($requestDomain);
                $requestDomain = @$parsedUrl['host'];
                $domain = @$user->currentAccessToken()->domain ?? '';
                $tokenDomain = @parse_url($domain)['host'];
                if ($domain && $tokenDomain != $requestDomain) {
                    return response()->json([
                        'message' => 'Authenticated'
                    ], 401);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        return $next($request);
    }
}
