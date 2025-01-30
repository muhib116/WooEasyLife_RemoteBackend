<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Traits\ApiResponseTrait;
use App\Traits\Util;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateTokenDomain
{
    use Util, ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        try {
            // if (!env('APP_SKIP_DOMAIN')) {
            // }
            $token = $request->bearerToken();
            $accessToken = AccessToken::findToken($token);
            if (!$accessToken->domain) {
                return $next($request);
            }
            // return response()->json($accessToken);
            if (!$accessToken) {
                return $this->errorResponse('Invalid Token');
            }

            $token = $request->bearerToken();
            $host = $this->getDomainFromUrl($accessToken->domain);
            $userPackage = UserPackage::where('user_id', $accessToken->tokenable_id)
                ->where('is_active', true)
                ->sum('remaining_order');
            if ($userPackage <= 0) {
                return $this->errorResponse('Your order limit is over.');
            }
            $requestDomain = $this->getDomainFromUrl($request->url());
            if ($host !== $requestDomain) {
                return $this->errorResponse('Invalid domain');
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        return $next($request);
    }
}
