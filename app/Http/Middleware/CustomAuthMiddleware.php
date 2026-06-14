<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token not found'], 401);
        }

        $tokenData = AccessToken::findToken($token);

        if (! $tokenData) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        if ($tokenData->expires_at && now()->greaterThan($tokenData->expires_at)) {
            return response()->json(['message' => 'Expired'], 401);
        }

        if ($tokenData->tokenable_type !== User::class) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        if (! User::findForApiAccess((int) $tokenData->tokenable_id)) {
            return response()->json(['message' => 'Account is disabled or deleted'], 401);
        }

        return $next($request);
    }
}
