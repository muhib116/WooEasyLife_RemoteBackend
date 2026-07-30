<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RbacService;
use App\Services\Seo\GaCredentialStore;
use App\Services\Seo\GoogleAnalyticsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAnalyticsOAuthController extends Controller
{
    public const SESSION_STATE = 'seo.ga.oauth_state';

    public const CACHE_PREFIX = 'seo.ga.oauth.';

    public function connect(Request $request, GoogleAnalyticsClient $ga): RedirectResponse
    {
        if (! filled($ga->clientId()) || ! filled($ga->clientSecret())) {
            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET before connecting Google Analytics.');
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE, $state);
        // Survive session expiry during Google consent (common in production).
        Cache::put(self::CACHE_PREFIX.$state, [
            'user_id' => $user->id,
        ], now()->addMinutes(20));

        $query = http_build_query([
            'client_id' => $ga->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(
        Request $request,
        GoogleAnalyticsClient $ga,
        GaCredentialStore $store,
        RbacService $rbac,
    ): RedirectResponse {
        $state = (string) $request->query('state', '');
        $pending = $state !== '' ? Cache::pull(self::CACHE_PREFIX.$state) : null;
        $sessionState = (string) $request->session()->pull(self::SESSION_STATE, '');

        $userId = is_array($pending) ? (int) ($pending['user_id'] ?? 0) : 0;
        $stateOk = $userId > 0 || ($sessionState !== '' && hash_equals($sessionState, $state));

        if ($state === '' || ! $stateOk) {
            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Google Analytics connect failed: invalid or expired OAuth state. Try Connect again.');
        }

        if ($userId > 0) {
            $user = User::query()->find($userId);
            if (! $user || $user->role !== 'admin' || ! $user->canAccessPlatform() || ! $rbac->hasPermission($user, 'roles.manage')) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Google Analytics connect failed: admin access no longer valid.');
            }

            if (! Auth::check() || (int) Auth::id() !== $user->id) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        } elseif (! Auth::check()) {
            return redirect()
                ->route('login')
                ->with('error', 'Google Analytics connect failed: please sign in and try again.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Google denied access: '.$request->string('error'));
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Google Analytics connect failed: missing authorization code.');
        }

        if (! filled($ga->clientId()) || ! filled($ga->clientSecret())) {
            return redirect()
                ->route('maintenance.index')
                ->with('error', 'GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are not configured.');
        }

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->retry(2, 200)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => $code,
                    'client_id' => $ga->clientId(),
                    'client_secret' => $ga->clientSecret(),
                    'redirect_uri' => $this->redirectUri(),
                    'grant_type' => 'authorization_code',
                ]);
        } catch (\Throwable $e) {
            Log::warning('GA OAuth token exchange failed', ['message' => $e->getMessage()]);

            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Could not reach Google token endpoint. Try again.');
        }

        if (! $response->successful()) {
            Log::warning('GA OAuth token exchange HTTP error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 300),
            ]);

            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Google token exchange failed (HTTP '.$response->status().'). Check Client ID/Secret and redirect URI.');
        }

        $refreshToken = trim((string) $response->json('refresh_token', ''));
        if ($refreshToken === '') {
            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Google did not return a refresh token. Revoke app access at myaccount.google.com/permissions, then Connect again.');
        }

        try {
            $ga->forgetCachedAccessToken();
            $store->putRefreshToken($refreshToken);
            $ga->forgetCachedAccessToken();
        } catch (\Throwable $e) {
            Log::error('GA refresh token save failed', ['message' => $e->getMessage()]);

            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Connected, but saving the token failed. Check logs and try again.');
        }

        Log::info('GA OAuth connected', ['user_id' => $request->user()?->id]);

        $message = 'Google Analytics connected. Token saved automatically.';
        if (! filled($ga->propertyId())) {
            $message .= ' Still set the GA4 Property ID under Blog AI Settings or SEO & Learning.';
        }

        return redirect()
            ->route('maintenance.index')
            ->with('success', $message);
    }

    public function updateProperty(
        Request $request,
        GoogleAnalyticsClient $ga,
        GaCredentialStore $store,
    ): RedirectResponse|\Illuminate\Http\JsonResponse {
        $validated = $request->validate([
            'property_id' => ['nullable', 'string', 'max:64'],
        ]);

        $raw = trim((string) ($validated['property_id'] ?? ''));
        $resolved = $ga->resolvePropertyIdInput($raw === '' ? null : $raw);
        if ($resolved['error'] !== null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $resolved['error']], 422);
            }

            return redirect()
                ->back()
                ->with('error', $resolved['error']);
        }

        $previous = $ga->propertyId();

        try {
            $store->putPropertyId($resolved['property_id']);
            $ga->forgetCachedAccessToken();
            if ($previous) {
                Cache::forget('seo:ga:realtime:'.$previous);
            }
            if ($resolved['property_id']) {
                Cache::forget('seo:ga:realtime:'.$resolved['property_id']);
            }
        } catch (\Throwable $e) {
            Log::error('GA property ID save failed', ['message' => $e->getMessage()]);
            $message = 'Could not save GA property ID. Check logs and try again.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }

        Log::info('GA property ID updated', [
            'user_id' => $request->user()?->id,
            'property_id' => $ga->propertyId(),
            'source' => $ga->propertyIdSource(),
            'from_measurement' => $resolved['from_measurement'],
        ]);

        $status = $ga->configurationStatus();
        if ($resolved['property_id'] === null) {
            $message = 'GA4 property ID cleared.';
        } elseif ($resolved['from_measurement']) {
            $message = 'Resolved Measurement ID to property '.$resolved['property_id'].' and saved.';
        } else {
            $message = 'GA4 property ID saved.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'ga_status' => $status,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    public function updateMeasurement(
        Request $request,
        GoogleAnalyticsClient $ga,
        GaCredentialStore $store,
    ): RedirectResponse|\Illuminate\Http\JsonResponse {
        $validated = $request->validate([
            'measurement_id' => ['nullable', 'string', 'max:32'],
            'enabled' => ['nullable'],
        ]);

        $resolved = $ga->resolvePublicMeasurementInput(
            $validated['measurement_id'] ?? null,
            array_key_exists('enabled', $validated) ? $validated['enabled'] : true,
        );

        if ($resolved['error'] !== null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $resolved['error']], 422);
            }

            return redirect()->back()->with('error', $resolved['error']);
        }

        try {
            $store->putMeasurementId($resolved['measurement_id']);
            $store->putMeasurementEnabled($resolved['enabled']);
        } catch (\Throwable $e) {
            Log::error('GA measurement ID save failed', ['message' => $e->getMessage()]);
            $message = 'Could not save public Measurement ID. Check logs and try again.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }

        Log::info('GA public measurement updated', [
            'user_id' => $request->user()?->id,
            'measurement_id' => $ga->publicMeasurementId(),
            'enabled' => $ga->publicMeasurementEnabled(),
            'source' => $ga->publicMeasurementIdSource(),
        ]);

        $status = $ga->configurationStatus();
        if (! $resolved['enabled']) {
            $message = 'Public gtag disabled. Site will not load Google Analytics.';
        } elseif ($resolved['measurement_id'] === null) {
            $message = 'Cleared saved Measurement ID — falling back to SEO_GA_MEASUREMENT_ID / config default when enabled.';
        } else {
            $message = 'Public Measurement ID saved ('.$resolved['measurement_id'].').';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'ga_status' => $status,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function disconnect(
        Request $request,
        GoogleAnalyticsClient $ga,
        GaCredentialStore $store,
    ): RedirectResponse {
        $ga->forgetCachedAccessToken();
        $store->clearRefreshToken();
        $ga->forgetCachedAccessToken();

        Log::info('GA OAuth disconnected', ['user_id' => $request->user()?->id]);

        return redirect()
            ->route('maintenance.index')
            ->with('success', 'Google Analytics disconnected. Saved refresh token cleared.');
    }

    private function redirectUri(): string
    {
        $configured = trim((string) config('seo.ga.oauth_redirect', ''));
        if ($configured !== '') {
            return $configured;
        }

        return route('maintenance.ga.callback', absolute: true);
    }
}
