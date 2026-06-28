<?php

namespace App\Http\Middleware;

use App\LogHelper;
use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\DomainNormalizer;
use App\Traits\ApiResponseTrait;
use App\Traits\Util;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates plugin package renewal routes even when the access token is expired.
 */
class AuthenticatePackageRenewal
{
    use ApiResponseTrait, Util;

    public function handle(Request $request, Closure $next): Response
    {
        $domainNormalizer = app(DomainNormalizer::class);

        try {
            $token = $request->bearerToken();
            $accessToken = AccessToken::findToken($token);
            $frontendDomain = $this->getRequestDomain();

            if (! $frontendDomain) {
                LogHelper::saveLog('Origin domain missing from header', $token);

                return $this->errorResponse('Origin domain missing from header');
            }

            if (! $accessToken) {
                LogHelper::saveLog('Invalid Token from AuthenticatePackageRenewal', $token);

                return $this->errorResponse('Invalid Token', 401);
            }

            $accessToken->update([
                'last_used_at' => now(),
            ]);

            if (! $accessToken->status) {
                LogHelper::saveLog('Disabled token access', $token);

                return $this->errorResponse('Unauthenticated', 401, [
                    'token' => 'Token is disabled',
                ]);
            }

            if ($accessToken->tokenable_type !== User::class) {
                return $this->errorResponse('Invalid Token', 401);
            }

            $user = User::findForApiAccess((int) $accessToken->tokenable_id);

            if (! $user) {
                LogHelper::saveLog('Inactive or trashed user API access', $token);

                return $this->errorResponse('Unauthenticated', 401, [
                    'user' => 'Account is disabled or deleted',
                ]);
            }

            $host = $this->getDomainFromUrl($accessToken->domain);
            if ($frontendDomain !== $host) {
                return $this->errorResponse('Invalid domain', 401);
            }

            $userPackages = UserPackage::where('user_id', $accessToken->tokenable_id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (UserPackage $package) => $domainNormalizer->matches(
                    $package->domain,
                    $accessToken->domain
                ));

            if ($userPackages->isNotEmpty()) {
                foreach ($userPackages as $package) {
                    if ($package->total_order_can_handle - $package->total_order_handled == 0) {
                        $package->update([
                            'remaining_order' => 0,
                            'is_active' => 0,
                        ]);
                    }
                }
            }

            Auth::setUser($user);
            $request->attributes->set('access_token', $accessToken);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Middleware AuthenticatePackageRenewal', $th->getMessage());

            return $this->errorResponse('Unauthenticated', 401);
        }

        return $next($request);
    }
}
