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
        // $token = $request->bearerToken();
        // $requestDomain = $request->getUri();
        // $user = Auth::user();
        // $parsedUrl = parse_url($requestDomain);
        // $requestDomain = @$parsedUrl['host'];
        // $domain = @$user->currentAccessToken()->domain ?? '';
        // $tokenDomain = @parse_url($domain)['host'];
        // dd($requestDomain);
        // if ($domain && $tokenDomain != $requestDomain) {
        //     return response()->json([
        //         'message' => 'Authenticated'
        //     ], 401);
        // }


        // Proceed with the request
        return $next($request);
    }
}
