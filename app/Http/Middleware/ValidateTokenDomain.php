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
            // if (!$accessToken->domain) {
            //     return $next($request);
            // }
            if (!$accessToken) {
                LogHelper::saveLog('Invalid Token from ValidateTokenDomain', $token);
                return $this->errorResponse('Invalid Token', 401);
            }

            $host = $this->getTokenDomain();
            $userPackage = UserPackage::where('user_id', $accessToken->tokenable_id)
                ->where('domain', $accessToken->domain)
                ->where('is_active', true)
                ->get();
            if ($userPackage) {
                foreach ($userPackage as $package) {
                    if ($package->total_order_can_handle - $package->total_order_handled == 0) {
                        $package->update([
                            'remaining_order' => 0,
                            'is_active' => 0
                        ]);
                    }
                }
            }
            $frontendDomain = $request->headers->get('origin') ?? $request->headers->get('referer');
            $requestDomain = $this->getRequestDomain();
            if ($requestDomain !== $host) {
                return $this->errorResponse('Invalid domain', 401);
            }
        } catch (\Throwable $th) {
            LogHelper::saveLog('Middleware ValidateTokenDomain', $th->getMessage());
            return $this->errorResponse('Unauthenticated', 401);
        }

        return $next($request);
    }
}
