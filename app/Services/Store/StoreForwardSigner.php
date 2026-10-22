<?php

namespace App\Services\Store;

use App\Models\AccessToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * HMAC key for hub → store forwards.
 *
 * The plugin verifies with its per-site hub_forward_secret. That value arrives
 * as store_forward_secret on courier webhook sync. Stores that have not synced
 * yet still receive a license-key signature, which older plugins accept.
 */
class StoreForwardSigner
{
    public function remember(AccessToken $token, string $secret): bool
    {
        $secret = trim($secret);
        if (strlen($secret) < 32 || ! $this->columnExists()) {
            return false;
        }

        $current = $this->plain($token);
        if (is_string($current) && hash_equals($current, $secret)) {
            return false;
        }

        $token->store_forward_secret = Crypt::encryptString($secret);
        $token->save();

        return true;
    }

    public function plain(?AccessToken $token): ?string
    {
        if (! $token || ! $this->columnExists()) {
            return null;
        }

        $stored = trim((string) ($token->store_forward_secret ?? ''));
        if ($stored === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($stored);
        } catch (\Throwable $exception) {
            return null;
        }

        $plain = trim((string) $plain);

        return strlen($plain) >= 32 ? $plain : null;
    }

    public function signingKey(?AccessToken $token): ?string
    {
        $secret = $this->plain($token);
        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        if (! $token || ! $token->access_key) {
            return null;
        }

        $license = decodeToken($token->access_key);

        return is_string($license) && $license !== '' ? $license : null;
    }

    private function columnExists(): bool
    {
        static $exists = false;
        if ($exists) {
            return true;
        }

        try {
            $exists = Schema::hasColumn('personal_access_tokens', 'store_forward_secret');
        } catch (\Throwable $exception) {
            $exists = false;
        }

        return $exists;
    }
}
