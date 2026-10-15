<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\MerchantPortalContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAuthenticatedUser
{
    public function __construct(
        protected MerchantPortalContext $portalContext
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedUser = $request->user();

        if (! $authenticatedUser) {
            return $next($request);
        }

        $user = User::withTrashed()->find($authenticatedUser->id);

        if (! $user || $user->trashed() || ! $user->status) {
            return $this->logout($request, 'Your account has been deactivated.');
        }

        if ($user->role === 'merchant_staff' && ! $this->portalContext->canAccessPortal($user)) {
            return $this->logout($request, 'Your employee portal access is no longer active.');
        }

        return $next($request);
    }

    private function logout(Request $request, string $message): Response
    {
        $merchant = in_array($request->user()?->role, ['user', 'merchant_staff'], true);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route($merchant ? 'merchant.login' : 'login')
            ->with('error', $message);
    }
}
