<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use App\Services\MerchantPortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantPortalAccess
{
    public function __construct(
        protected MerchantPortalContext $portalContext
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'admin') {
            return redirect()->route('dashboard');
        }

        if (! $user || ! $this->portalContext->canAccessPortal($user)) {
            abort(403, 'Merchant portal access required.');
        }

        return $next($request);
    }
}
