<?php

namespace App\Http\Middleware;

use App\Services\MerchantPortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourierHistoryEnabled
{
    public function __construct(private MerchantPortalContext $portal) {}

    public function handle(Request $request, Closure $next): Response
    {
        $merchant = $this->portal->resolveMerchant($request->user());

        if ($merchant->courier_history_enabled) {
            return $next($request);
        }

        $message = 'Courier history is not enabled for this account.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()
            ->route('portal.dashboard')
            ->with('error', $message);
    }
}
