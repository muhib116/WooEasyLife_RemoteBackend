<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RbacService;
use App\Services\Seo\GoogleSearchConsoleClient;
use App\Services\Seo\GscCredentialStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleSearchConsoleOAuthController extends Controller
{
    public const SESSION_STATE = 'seo.gsc.oauth_state';

    public const CACHE_PREFIX = 'seo.gsc.oauth.';

    public function connect(Request $request, GoogleSearchConsoleClient $gsc): RedirectResponse
    {
        if (! filled($gsc->clientId()) || ! filled($gsc->clientSecret())) {
            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET before connecting Search Console.');
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
            'client_id' => $gsc->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(
        Request $request,
        GoogleSearchConsoleClient $gsc,
        GscCredentialStore $store,
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
                ->with('error', 'Search Console connect failed: invalid or expired OAuth state. Try Connect again.');
        }

        if ($userId > 0) {
            $user = User::query()->find($userId);
            if (! $user || $user->role !== 'admin' || ! $user->canAccessPlatform() || ! $rbac->hasPermission($user, 'roles.manage')) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Search Console connect failed: admin access no longer valid.');
            }

            if (! Auth::check() || (int) Auth::id() !== $user->id) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        } elseif (! Auth::check()) {
            return redirect()
                ->route('login')
                ->with('error', 'Search Console connect failed: please sign in and try again.');
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
                ->with('error', 'Search Console connect failed: missing authorization code.');
        }

        if (! filled($gsc->clientId()) || ! filled($gsc->clientSecret())) {
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
                    'client_id' => $gsc->clientId(),
                    'client_secret' => $gsc->clientSecret(),
                    'redirect_uri' => $this->redirectUri(),
                    'grant_type' => 'authorization_code',
                ]);
        } catch (\Throwable $e) {
            Log::warning('GSC OAuth token exchange failed', ['message' => $e->getMessage()]);

            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Could not reach Google token endpoint. Try again.');
        }

        if (! $response->successful()) {
            Log::warning('GSC OAuth token exchange HTTP error', [
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
            $gsc->forgetCachedAccessToken();
            $store->putRefreshToken($refreshToken);
            $gsc->forgetCachedAccessToken();
        } catch (\Throwable $e) {
            Log::error('GSC refresh token save failed', ['message' => $e->getMessage()]);

            return redirect()
                ->route('maintenance.index')
                ->with('error', 'Connected, but saving the token failed: '.$e->getMessage());
        }

        Log::info('GSC OAuth connected', ['user_id' => $request->user()?->id]);

        $message = 'Google Search Console connected. Token saved automatically.';
        if (! filled($gsc->siteUrl())) {
            $message .= ' Still set SEO_GSC_SITE_URL in .env to your verified property URL.';
        }

        return redirect()
            ->route('maintenance.index')
            ->with('success', $message);
    }

    public function disconnect(
        Request $request,
        GoogleSearchConsoleClient $gsc,
        GscCredentialStore $store,
    ): RedirectResponse {
        $gsc->forgetCachedAccessToken();
        $store->clearRefreshToken();
        $gsc->forgetCachedAccessToken();

        Log::info('GSC OAuth disconnected', ['user_id' => $request->user()?->id]);

        return redirect()
            ->route('maintenance.index')
            ->with('success', 'Search Console disconnected. Saved refresh token cleared.');
    }

    private function redirectUri(): string
    {
        $configured = trim((string) config('seo.gsc.oauth_redirect', ''));
        if ($configured !== '') {
            return $configured;
        }

        return route('maintenance.gsc.callback', absolute: true);
    }
}
