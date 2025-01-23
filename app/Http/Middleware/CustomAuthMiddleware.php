<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CustomAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        // Check if the token is present
        if (!$token) {
            return response()->json(['message' => 'Token not found'], 401);
        }

        // Attempt to find the token in the database
        $tokenData = AccessToken::findToken($token);

        // If the token is not found, it's invalid
        if (!$tokenData) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // Check if the token has expired
        if ($tokenData->expires_at && now()->greaterThan($tokenData->expires_at)) {
            return response()->json(['message' => 'Expired'], 401);
        }

        return $next($request);
    }
}
