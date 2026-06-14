<?php

namespace App\Services;

use App\Models\WhitelistedDomain;
use App\Traits\Util;
use Illuminate\Support\Facades\Cache;

class WhitelistedDomainService
{
    use Util;

    private const CACHE_KEY = 'fraud_check_whitelisted_domains';

    public function normalizeDomain(?string $domain): ?string
    {
        if (!$domain) {
            return null;
        }

        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        if (str_contains($domain, '/')) {
            $domain = $this->getDomainFromUrl($domain);
        }

        return $domain ?: null;
    }

    public function isAllowed(?string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);

        if (!$domain) {
            return false;
        }

        if ($this->isDevBypassDomain($domain)) {
            return true;
        }

        return in_array($domain, $this->activeDomains(), true);
    }

    public function isRequestAllowed(): bool
    {
        return $this->isAllowed($this->getRequestDomain());
    }

    public function activeDomains(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            return WhitelistedDomain::query()
                ->where('is_active', true)
                ->pluck('domain')
                ->map(fn (string $domain) => $this->normalizeDomain($domain))
                ->filter()
                ->values()
                ->all();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function isDevBypassDomain(string $domain): bool
    {
        if (!app()->environment('local')) {
            return false;
        }

        return in_array($domain, ['localhost', '127.0.0.1'], true);
    }
}
