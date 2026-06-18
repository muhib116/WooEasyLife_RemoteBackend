<?php

namespace App\Services\Courier;

use App\Models\AccessToken;
use App\Models\CourierAccount;
use App\Models\CourierConfiguration;
use App\Models\CourierHubToken;
use App\Models\CourierShipment;
use App\Models\LicenseCourierAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourierWebhookSyncService
{
    public function __construct(
        protected CourierAccountService $accountService,
        protected CourierWebhookSettingsService $settingsService
    ) {
    }

    /**
     * Register / refresh webhook auth + store reachability data from a WordPress store.
     *
     * @return array<string, mixed>
     */
    public function sync(Request $request): array
    {
        $partner = strtolower(trim((string) $request->input('partner', '')));
        $environment = $request->input('environment') === 'sandbox' ? 'sandbox' : 'live';

        $accessToken = $this->accountService->resolveAccessToken($request);
        if (!$accessToken) {
            throw new \InvalidArgumentException('Missing or invalid license token.');
        }

        $this->updateAccessTokenSite($accessToken, $request);

        $account = $this->resolveCourierAccount($accessToken, $partner, $environment, $request);
        $verifySecret = $this->resolveVerifySecret($partner, $request, $account);

        if ($account) {
            if ($verifySecret !== '') {
                $account->webhook_verify_secret = $verifySecret;
            }

            $account->environment = $environment;
            $account->is_active = true;
            $account->retired_at = null;
            $account->save();
        }

        if ($partner === 'redx' && $verifySecret !== '') {
            CourierHubToken::query()->updateOrCreate(
                ['partner' => 'redx'],
                ['token' => $verifySecret]
            );
        }

        $this->refreshShipmentSiteUrls($accessToken, $request);

        $callbackUrl = $this->settingsService->callbackUrl($partner, $environment);
        $redxToken = $partner === 'redx' ? $verifySecret : '';

        if ($partner === 'redx' && $redxToken !== '') {
            $callbackUrl .= (str_contains($callbackUrl, '?') ? '&' : '?') . 'token=' . urlencode($redxToken);
        }

        return [
            'partner' => $partner,
            'environment' => $environment,
            'courier_account_id' => $account?->id,
            'callback_url' => $callbackUrl,
            'store_forward_url' => trim((string) $request->input('store_forward_url', '')),
            'site_url' => trim((string) $request->input('site_url', '')),
            'webhook_verify_secret_set' => $verifySecret !== '',
            'redx_webhook_token' => $redxToken !== '' ? $redxToken : null,
            'hub_ready' => $this->settingsService->shipmentMappingCount($accessToken->id, $partner, $environment) > 0,
        ];
    }

    private function updateAccessTokenSite(AccessToken $accessToken, Request $request): void
    {
        $siteUrl = rtrim(trim((string) $request->input('site_url', '')), '/');
        if ($siteUrl === '') {
            $siteUrl = rtrim(trim($this->accountService->resolveSiteUrl($request)), '/');
        }

        if ($siteUrl === '') {
            return;
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);
        $domain = is_string($host) && $host !== '' ? $host : $siteUrl;

        if (trim((string) $accessToken->domain) !== $domain) {
            $accessToken->domain = $domain;
            $accessToken->save();
        }
    }

    private function resolveCourierAccount(
        AccessToken $accessToken,
        string $partner,
        string $environment,
        Request $request
    ): ?CourierAccount {
        $link = LicenseCourierAccount::query()
            ->where('access_token_id', $accessToken->id)
            ->where('is_current', true)
            ->whereHas('courierAccount', function ($query) use ($partner, $environment) {
                $query->where('partner', $partner)->where('environment', $environment);
            })
            ->with('courierAccount')
            ->first();

        if ($link?->courierAccount) {
            return $link->courierAccount;
        }

        $configuration = CourierConfiguration::query()
            ->where('user_id', $accessToken->tokenable_id)
            ->where('slug', $partner)
            ->first();

        if (!$configuration) {
            return null;
        }

        $sync = $this->accountService->syncAccountForConfiguration(
            $configuration,
            $request,
            $partner === 'pathao' ? trim((string) $request->input('webhook_secret', '')) : null
        );

        $accountId = (int) ($sync['courier_account_id'] ?? 0);

        return $accountId > 0 ? CourierAccount::query()->find($accountId) : null;
    }

    private function resolveVerifySecret(string $partner, Request $request, ?CourierAccount $account): string
    {
        if ($partner === 'pathao') {
            $secret = trim((string) $request->input('webhook_secret', ''));

            return $secret !== '' ? $secret : trim((string) ($account?->webhook_verify_secret ?? ''));
        }

        if ($partner === 'steadfast') {
            $auth = $request->input('webhook_auth');
            $token = is_array($auth)
                ? trim((string) ($auth['bearer_token'] ?? ''))
                : '';

            return $token !== '' ? $token : trim((string) ($account?->webhook_verify_secret ?? ''));
        }

        if ($partner === 'redx') {
            $token = trim((string) $request->input('webhook_token', ''));

            if ($token === '') {
                $token = trim((string) ($account?->webhook_verify_secret ?? ''));
            }

            if ($token === '') {
                $token = Str::random(32);
            }

            return $token;
        }

        return '';
    }

    private function refreshShipmentSiteUrls(AccessToken $accessToken, Request $request): void
    {
        $siteUrl = rtrim(trim((string) $request->input('site_url', '')), '/');
        if ($siteUrl === '') {
            return;
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);
        $domain = is_string($host) && $host !== '' ? $host : $siteUrl;

        CourierShipment::query()
            ->where('access_token_id', $accessToken->id)
            ->where(function ($query) use ($siteUrl) {
                $query->whereNull('site_url')
                    ->orWhere('site_url', '')
                    ->orWhere('site_url', '!=', $siteUrl);
            })
            ->update([
                'site_url' => $siteUrl,
                'site_domain' => $domain,
            ]);
    }
}
