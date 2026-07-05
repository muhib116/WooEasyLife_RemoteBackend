<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public const ADMIN_LOGIN_UNLOCK_SESSION_KEY = 'admin_login_unlocked';

    public const ADMIN_LOGIN_REQUIRED_CLICKS = 10;

    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $redirect = $request->query('redirect');
        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            session(['url.intended' => $redirect]);
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'adminLoginUnlocked' => (bool) $request->session()->get(self::ADMIN_LOGIN_UNLOCK_SESSION_KEY),
            'adminLoginRequiredClicks' => self::ADMIN_LOGIN_REQUIRED_CLICKS,
        ]);
    }

    /**
     * Reveal the admin login form after the client-side click gate.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $request->session()->put(self::ADMIN_LOGIN_UNLOCK_SESSION_KEY, true);

        return redirect()->route('login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! $request->session()->get(self::ADMIN_LOGIN_UNLOCK_SESSION_KEY)) {
            return back()->withErrors([
                'email' => 'Admin sign-in is not available.',
            ]);
        }

        $request->authenticate();

        $user = $request->user();

        if (! $user || ! $user->canAccessPlatform()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This account is disabled.']);
        }

        if ($user->role !== 'admin') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This sign-in page is for admin accounts only. Use the merchant portal to sign in.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
