<?php

namespace App\Http\Middleware;

use App\LogHelper;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\WhitelistedDomainService;
use App\Traits\ApiResponseTrait;
use App\Traits\Util;
use Closure;
use Illuminate\Http\Request;

class ValidateWhitelistedFraudDomain
{
    use ApiResponseTrait, Util;

    public function __construct(
        private WhitelistedDomainService $whitelistedDomainService,
        private MerchantSteadfastFraudCredentialResolver $steadfastCredentialResolver,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $requestDomain = $this->getRequestDomain();

        if (!$requestDomain) {
            LogHelper::saveLog('Fraud check domain blocked', 'Origin or Referer header is missing');

            return $this->errorResponse('Origin domain missing from header', 403);
        }

        if (!$this->whitelistedDomainService->isAllowed($requestDomain)) {
            if ($this->steadfastCredentialResolver->hasCredentialsForRequest($request)) {
                return $next($request);
            }

            LogHelper::saveLog('Fraud check domain blocked', "Domain not whitelisted: {$requestDomain}");

            return $this->errorResponse('This domain is not allowed to use fraud check', 403);
        }

        return $next($request);
    }
}
