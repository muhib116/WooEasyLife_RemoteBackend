<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\MerchantSocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class MerchantSocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(Request $request, string $provider): SymfonyRedirectResponse|RedirectResponse
    {
        $this->ensureProviderSupported($provider);
        $this->ensureProviderConfigured($provider);

        if ($response = $this->redirectIfSocialiteMissing()) {
            return $response;
        }

        $this->rememberRedirect($request);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(
        Request $request,
        string $provider,
        MerchantSocialAuthService $socialAuth
    ): RedirectResponse {
        $this->ensureProviderSupported($provider);
        $this->ensureProviderConfigured($provider);

        if ($response = $this->redirectIfSocialiteMissing()) {
            return $response;
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return redirect()
                ->route('merchant.login')
                ->withErrors(['email' => 'Social sign-in was cancelled or failed. Please try again.']);
        }

        try {
            $user = $socialAuth->authenticate($provider, $socialUser);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('merchant.login')
                ->withErrors($exception->errors());
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::PORTAL_HOME);
    }

    private function ensureProviderSupported(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }
    }

    private function ensureProviderConfigured(string $provider): void
    {
        if (! in_array($provider, MerchantSocialAuthService::enabledProviders(), true)) {
            abort(503, ucfirst($provider).' sign-in is not configured.');
        }
    }

    private function rememberRedirect(Request $request): void
    {
        $redirect = $request->query('redirect');

        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            session(['url.intended' => $redirect]);
        }
    }

    private function redirectIfSocialiteMissing(): ?RedirectResponse
    {
        if (class_exists('Laravel\Socialite\Facades\Socialite')) {
            return null;
        }

        return redirect()
            ->route('merchant.login')
            ->withErrors(['email' => 'Social login is not available on the server yet. Please sign in with email or contact support.']);
    }
}
