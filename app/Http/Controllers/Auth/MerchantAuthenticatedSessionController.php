<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Services\MerchantPortalContext;
use App\Services\MerchantSocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class MerchantAuthenticatedSessionController extends Controller
{
    public function __construct(
        protected MerchantPortalContext $portalContext
    ) {
    }

    /**
     * Display the merchant portal login view.
     */
    public function create(Request $request): Response
    {
        $redirect = $request->query('redirect');
        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            session(['url.intended' => $redirect]);
        }

        return Inertia::render('Auth/MerchantLogin', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'socialProviders' => MerchantSocialAuthService::enabledProviders(),
        ]);
    }

    /**
     * Handle an incoming merchant authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if (! $user || ! $user->canAccessPlatform()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This account is disabled.']);
        }

        if (! in_array($user->role, ['user', 'merchant_staff'], true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This sign-in page is for merchant accounts only.']);
        }

        if ($user->role === 'merchant_staff' && ! $this->portalContext->canAccessPortal($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Your employee portal access is inactive.']);
        }

        $request->session()->regenerate();

        if ($user->must_change_password ?? false) {
            return redirect()->route('portal.password.force');
        }

        return redirect()->intended(RouteServiceProvider::PORTAL_HOME);
    }
}
