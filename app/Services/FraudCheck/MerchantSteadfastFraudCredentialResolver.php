<?php

namespace App\Services\FraudCheck;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
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
        $configuration = CourierConfiguration::query()
            ->where('user_id', $userId)
            ->where('slug', 'steadfast')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        if (! $configuration) {
            return null;
        }

        return $this->credentialsFromSettings(
            is_array($configuration->settings) ? $configuration->settings : []
        );
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
