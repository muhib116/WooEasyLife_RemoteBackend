<?php

namespace App\Http\Middleware;

use App\LogHelper;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\FraudCheck\PluginFraudCheckFreeQuota;
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
        private PluginFraudCheckFreeQuota $freeQuota,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $requestDomain = $this->getRequestDomain();

        if (! $requestDomain) {
            LogHelper::saveLog('Fraud check domain blocked', 'Origin or Referer header is missing');

            return $this->errorResponse('Origin domain missing from header', 403);
        }

        if ($this->whitelistedDomainService->isAllowed($requestDomain)) {
            return $next($request);
        }

        if ($this->steadfastCredentialResolver->hasCredentialsForRequest($request)) {
            return $next($request);
        }

        // Landing-style free tier: courier history without Steadfast credentials.
        $needed = $this->freeQuota->countPhonesInRequest($request);
        if ($this->freeQuota->hasRemaining($request, $needed)) {
            return $next($request);
        }

        LogHelper::saveLog(
            'Fraud check free quota blocked',
            "Domain {$requestDomain} needs Steadfast credentials (needed={$needed})",
        );

        return response()->json($this->freeQuota->denyPayload($request, $needed), 429);
    }
}
