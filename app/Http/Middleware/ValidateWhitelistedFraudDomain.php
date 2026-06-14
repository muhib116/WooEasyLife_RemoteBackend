<?php

namespace App\Http\Middleware;

use App\LogHelper;
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
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $requestDomain = $this->getRequestDomain();

        if (!$requestDomain) {
            LogHelper::saveLog('Fraud check domain blocked', 'Origin or Referer header is missing');

            return $this->errorResponse('Origin domain missing from header', 403);
        }

        if (!$this->whitelistedDomainService->isAllowed($requestDomain)) {
            LogHelper::saveLog('Fraud check domain blocked', "Domain not whitelisted: {$requestDomain}");

            return $this->errorResponse('This domain is not allowed to use fraud check', 403);
        }

        return $next($request);
    }
}
