<?php

namespace App\Http\Middleware;

use App\LogHelper;
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
            $token = $request->bearerToken();
            $accessToken = AccessToken::findToken($token);
            $frontendDomain = $request->headers->get('origin') ?? $request->headers->get('referer');
            if (!$frontendDomain) {
                LogHelper::saveLog('Origin domain missing from header', $token);
                return $this->errorResponse('Origin domain missing from header');
            }
            if (!$accessToken->domain) {
                return $next($request);
            }
            if (!$accessToken) {
                LogHelper::saveLog('Invalid Token from', $token);
                return $this->errorResponse('Invalid Token');
            }

            $host = $this->getDomainFromUrl($accessToken->domain);

            $userPackage = UserPackage::where('user_id', $accessToken->tokenable_id)
                ->where('is_active', true)
                ->sum('remaining_order');
            if ($userPackage <= 0) {
                LogHelper::saveLog('Order limit is over', $accessToken->tokenable_id.'user order limit is over');
                return $this->errorResponse('Your order limit is over.');
            }

            $frontendDomain = $request->headers->get('origin') ?? $request->headers->get('referer');
            $requestDomain = $this->getDomainFromUrl($frontendDomain);
            if ($requestDomain !== $host) {
                return $this->errorResponse('Invalid domain');
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        return $next($request);
    }
}
