<?php

namespace App\Services\OrderIntelligence;

use Illuminate\Support\Facades\Cache;

class IntelligenceCache
{
    public function key(string $phoneNormalized, ?int $accessTokenId = null): string
    {
        $prefix = (string) config('order_intelligence.cache.prefix', 'order_intel:');

        if ($accessTokenId !== null) {
            return $prefix . $phoneNormalized . ':' . $accessTokenId;
        }

        return $prefix . $phoneNormalized;
    }

    public function get(string $phoneNormalized, ?int $accessTokenId = null): ?array
    {
        if (! config('order_intelligence.cache.enabled', true)) {
            return null;
        }

        $cached = Cache::get($this->key($phoneNormalized, $accessTokenId));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $phoneNormalized, array $payload, ?int $accessTokenId = null): void
    {
        if (! config('order_intelligence.cache.enabled', true)) {
            return;
        }

        Cache::put(
            $this->key($phoneNormalized, $accessTokenId),
            $payload,
            now()->addSeconds((int) config('order_intelligence.cache.ttl_seconds', 900)),
        );
    }

    public function forget(string $phoneNormalized, ?int $accessTokenId = null): void
    {
        Cache::forget($this->key($phoneNormalized, $accessTokenId));

        if ($accessTokenId !== null) {
            Cache::forget($this->key($phoneNormalized));
        }
    }
}
