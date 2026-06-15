<?php

namespace App\Services\Courier;

use App\Models\AccessToken;
use App\Models\CourierAccount;
use App\Models\CourierConfiguration;
use App\Models\CourierShipment;
use App\Models\LicenseCourierAccount;
use Illuminate\Http\Request;

class CourierAccountService
{
    public function credentialHash(string $partner, CourierConfiguration $config): string
    {
        $partner = strtolower(trim($partner));

        if ($partner === 'redx') {
            return hash('sha256', $partner . ':' . trim((string) $config->secret_key));
        }

        return hash('sha256', $partner . ':' . trim((string) $config->api_key) . ':' . trim((string) $config->secret_key));
    }

    public function environmentFromConfig(CourierConfiguration $config): string
    {
        $settings = is_array($config->settings) ? $config->settings : [];

        return ($settings['environment'] ?? 'live') === 'sandbox' ? 'sandbox' : 'live';
    }

    public function webhookVerifySecret(string $partner, CourierConfiguration $config, ?string $overrideSecret = null): string
    {
        $partner = strtolower(trim($partner));
        $settings = is_array($config->settings) ? $config->settings : [];

        if ($partner === 'pathao') {
            $secret = trim((string) ($overrideSecret ?? ($settings['webhook_secret'] ?? '')));

            return $secret !== '' ? $secret : trim((string) $config->secret_key);
        }

        if ($partner === 'steadfast') {
            return trim((string) $config->api_key);
        }

        return '';
    }

    public function resolveAccessToken(Request $request): ?AccessToken
    {
        $token = $request->bearerToken();

        return $token ? AccessToken::findToken($token) : null;
    }

    public function resolveSiteUrl(Request $request): string
    {
        $origin = trim((string) ($request->headers->get('origin') ?: $request->headers->get('referer')));

        return $origin !== '' ? rtrim($origin, '/') : '';
    }

    public function syncAccountForConfiguration(
        CourierConfiguration $configuration,
        Request $request,
        ?string $webhookSecretOverride = null
    ): array {
        $partner = strtolower(trim((string) $configuration->slug));
        $accessToken = $this->resolveAccessToken($request);
        $credentialHash = $this->credentialHash($partner, $configuration);
        $environment = $this->environmentFromConfig($configuration);
        $previousAccountId = null;
        $credentialsChanged = false;

        if ($accessToken) {
            $currentLink = LicenseCourierAccount::query()
                ->where('access_token_id', $accessToken->id)
                ->where('is_current', true)
                ->whereHas('courierAccount', function ($query) use ($partner) {
                    $query->where('partner', $partner);
                })
                ->with('courierAccount')
                ->first();

            if ($currentLink?->courierAccount) {
                $previousAccountId = $currentLink->courier_account_id;
                if ($currentLink->courierAccount->credential_hash !== $credentialHash
                    || $currentLink->courierAccount->environment !== $environment) {
                    $credentialsChanged = true;
                }
            }
        }

        $account = CourierAccount::query()->updateOrCreate(
            [
                'partner' => $partner,
                'credential_hash' => $credentialHash,
                'environment' => $environment,
            ],
            [
                'user_id' => (int) $configuration->user_id,
                'courier_configuration_id' => $configuration->id,
                'webhook_verify_secret' => $this->webhookVerifySecret($partner, $configuration, $webhookSecretOverride),
                'is_active' => true,
                'retired_at' => null,
            ]
        );

        if ($accessToken) {
            if ($credentialsChanged || !$previousAccountId) {
                LicenseCourierAccount::query()
                    ->where('access_token_id', $accessToken->id)
                    ->where('is_current', true)
                    ->whereHas('courierAccount', function ($query) use ($partner) {
                        $query->where('partner', $partner);
                    })
                    ->update([
                        'is_current' => false,
                        'revoked_at' => now(),
                    ]);
            }

            LicenseCourierAccount::query()->updateOrCreate(
                [
                    'access_token_id' => $accessToken->id,
                    'courier_account_id' => $account->id,
                ],
                [
                    'is_current' => true,
                    'assigned_at' => now(),
                    'revoked_at' => null,
                ]
            );
        }

        $sharedCount = CourierAccount::query()
            ->where('credential_hash', $credentialHash)
            ->where('partner', $partner)
            ->where('environment', $environment)
            ->count();

        $licenseLinks = LicenseCourierAccount::query()
            ->where('courier_account_id', $account->id)
            ->where('is_current', true)
            ->count();

        return [
            'courier_account_id' => $account->id,
            'credentials_changed' => $credentialsChanged,
            'shared_account' => $licenseLinks > 1 || $sharedCount > 1,
            'shared_with_license_count' => max($licenseLinks, 1),
        ];
    }

    public function countInFlightShipments(?AccessToken $accessToken, string $partner, ?int $excludeAccountId = null): int
    {
        if (!$accessToken) {
            return 0;
        }

        $query = CourierShipment::query()
            ->where('access_token_id', $accessToken->id)
            ->where('partner', strtolower(trim($partner)))
            ->whereNotIn('status', ['delivered', 'cancelled', 'partial_delivered', 'returned']);

        if ($excludeAccountId) {
            $query->where('courier_account_id', '!=', $excludeAccountId);
        }

        return (int) $query->count();
    }

    public function parseInvoiceOrderId($invoice): int
    {
        $invoiceStr = trim((string) $invoice);
        if ($invoiceStr === '') {
            return 0;
        }

        if (preg_match('/^[A-Z]{3}\d+-(.+)$/i', $invoiceStr, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/^[A-Z0-9]{3}-(.+)$/i', $invoiceStr, $matches)) {
            return (int) $matches[1];
        }

        if (str_contains($invoiceStr, '-')) {
            $parts = explode('-', $invoiceStr);

            return (int) end($parts);
        }

        return (int) $invoiceStr;
    }

    public function configurationForAccount(int $accountId, int $userId, string $partner): ?CourierConfiguration
    {
        if ($accountId <= 0) {
            return CourierConfiguration::query()
                ->where('user_id', $userId)
                ->where('slug', strtolower(trim($partner)))
                ->first();
        }

        $account = CourierAccount::query()->find($accountId);
        if (!$account) {
            return null;
        }

        if ($account->courier_configuration_id) {
            return CourierConfiguration::query()->find($account->courier_configuration_id);
        }

        return CourierConfiguration::query()
            ->where('user_id', $userId)
            ->where('slug', strtolower(trim($partner)))
            ->first();
    }
}
