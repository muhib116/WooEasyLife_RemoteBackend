<?php

namespace App\Services;

use App\Models\AccessToken;
use Illuminate\Http\Request;

/**
 * Request-scoped Sanctum token lookup so check.token + check.tokenDomain
 * (+ controllers) do not hash/query the same bearer token repeatedly.
 */
class ApiAccessTokenResolver
{
    public const REQUEST_ATTRIBUTE = 'api.resolved_access_token';

    public const REQUEST_PLAIN_ATTRIBUTE = 'api.resolved_access_token_plain';

    public function resolve(?string $plainTextToken, ?Request $request = null): ?AccessToken
    {
        if ($plainTextToken === null || $plainTextToken === '') {
            return null;
        }

        $request ??= request();
        if (! $request instanceof Request) {
            return AccessToken::findToken($plainTextToken);
        }

        if ($request->attributes->get(self::REQUEST_PLAIN_ATTRIBUTE) === $plainTextToken
            && $request->attributes->has(self::REQUEST_ATTRIBUTE)
        ) {
            $cached = $request->attributes->get(self::REQUEST_ATTRIBUTE);

            return $cached instanceof AccessToken ? $cached : null;
        }

        $token = AccessToken::findToken($plainTextToken);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $token);
        $request->attributes->set(self::REQUEST_PLAIN_ATTRIBUTE, $plainTextToken);

        return $token;
    }

    /**
     * Throttle last_used_at writes so hot plugin APIs do not UPDATE every call.
     * Token validity checks remain fresh; only the timestamp write is deferred.
     */
    public function touchLastUsed(AccessToken $accessToken, int $throttleSeconds = 300): void
    {
        $lastUsed = $accessToken->last_used_at;
        if ($lastUsed !== null && $lastUsed->gt(now()->subSeconds($throttleSeconds))) {
            return;
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
    }
}
