<?php

namespace App\Http\Middleware;

use App\LogHelper;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\ApiAccessTokenResolver;
use App\Services\DomainNormalizer;
use App\Traits\ApiResponseTrait;
use App\Traits\Util;
use Closure;
use Illuminate\Http\Request;

class ValidateTokenDomain
{
    use Util, ApiResponseTrait;

    public function __construct(
        private ApiAccessTokenResolver $tokens,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $domainNormalizer = app(DomainNormalizer::class);

        try {
            $token = $request->bearerToken();
            $accessToken = $this->tokens->resolve($token, $request);
            $frontendDomain = $this->getRequestDomain();
            if (! $frontendDomain) {
                LogHelper::saveLog('Origin domain missing from header', $token);

                return $this->errorResponse('Origin domain missing from header');
            }

            if (! $accessToken) {
                LogHelper::saveLog('Invalid Token from ValidateTokenDomain', $token);

                return $this->errorResponse('Invalid Token', 401);
            }

            $this->tokens->touchLastUsed($accessToken);

            if (! $accessToken->status) {
                LogHelper::saveLog('Disabled token access', $token);

                return $this->errorResponse('Unauthenticated', 401, [
                    'token' => 'Token is disabled',
                ]);
            }

            if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
                return $this->errorResponse('Expired', 401);
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

            $normalizedTokenDomain = $domainNormalizer->normalize($accessToken->domain);
            $userPackages = UserPackage::query()
                ->where('user_id', $accessToken->tokenable_id)
                ->where('is_active', true);

            if ($normalizedTokenDomain) {
                $domainNormalizer->constrainMatchingDomain(
                    $userPackages,
                    'domain',
                    $normalizedTokenDomain
                );
            }

            $userPackage = $userPackages
                ->get()
                ->filter(fn (UserPackage $package) => $domainNormalizer->matches(
                    $package->domain,
                    $accessToken->domain
                ));

            if ($userPackage->isNotEmpty()) {
                foreach ($userPackage as $package) {
                    if ($package->total_order_can_handle - $package->total_order_handled == 0) {
                        $package->update([
                            'remaining_order' => 0,
                            'is_active' => 0,
                        ]);
                    }
                }
            }
        } catch (\Throwable $th) {
            LogHelper::saveLog('Middleware ValidateTokenDomain', $th->getMessage());

            return $this->errorResponse('Unauthenticated', 401);
        }

        return $next($request);
    }
}
