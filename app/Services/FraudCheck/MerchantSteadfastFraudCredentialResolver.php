<?php

namespace App\Services\FraudCheck;

use App\Models\AccessToken;
use App\Services\Courier\CourierConfigurationResolver;
use App\Models\User;
use Illuminate\Http\Request;

class MerchantSteadfastFraudCredentialResolver
{
    private const REQUEST_RESOLVED_FLAG = 'merchant_steadfast_fraud_credentials_resolved';

    private const REQUEST_CREDENTIALS_KEY = 'merchant_steadfast_fraud_credentials';

    public function resolveFromCurrentRequest(): ?array
    {
        $request = request();

        if (! $request instanceof Request) {
            return null;
        }

        return $this->resolveFromRequest($request);
    }

    /**
     * Portal email/password only. API keys on the same courier row are not a login.
     *
     * @return array{username: string, password: string}|null
     */
    public function resolvePortalFromCurrentRequest(): ?array
    {
        $credentials = $this->resolveFromCurrentRequest();

        if (! is_array($credentials)) {
            return null;
        }

        return $this->credentialsFromSettings($credentials);
    }

    public function resolveFromRequest(Request $request): ?array
    {
        if ($request->attributes->get(self::REQUEST_RESOLVED_FLAG, false)) {
            return $request->attributes->get(self::REQUEST_CREDENTIALS_KEY);
        }

        $credentials = $this->lookupFromRequest($request);

        $request->attributes->set(self::REQUEST_RESOLVED_FLAG, true);
        $request->attributes->set(self::REQUEST_CREDENTIALS_KEY, $credentials);

        return $credentials;
    }

    private function lookupFromRequest(Request $request): ?array
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = AccessToken::findToken($token);

        if (! $accessToken || $accessToken->tokenable_type !== User::class) {
            return null;
        }

        return $this->resolveForUserId((int) $accessToken->tokenable_id);
    }

    public function resolveForUserId(int $userId): ?array
    {
        $configuration = app(\App\Services\Courier\CourierLicenseSyncService::class)
            ->bookingConfiguration($userId, 'steadfast');

        if (! $configuration) {
            return null;
        }

        $credentials = $this->credentialsFromSettings(
            is_array($configuration->settings) ? $configuration->settings : []
        ) ?? [];

        $apiKey = trim((string) $configuration->api_key);
        $secretKey = trim((string) $configuration->secret_key);

        if ($apiKey !== '' && $secretKey !== '') {
            $credentials['api_key'] = $apiKey;
            $credentials['secret_key'] = $secretKey;
        }

        return $credentials === [] ? null : $credentials;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{username: string, password: string}|null
     */
    public function credentialsFromSettings(array $settings): ?array
    {
        $username = trim((string) ($settings['username'] ?? ''));
        $password = trim((string) ($settings['password'] ?? ''));

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    public function hasCredentialsForRequest(Request $request): bool
    {
        return $this->resolveFromRequest($request) !== null;
    }
}
