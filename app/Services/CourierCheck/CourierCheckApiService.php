<?php

namespace App\Services\CourierCheck;

use App\Models\CourierCheckSite;
use App\Services\PublicFraudCheckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CourierCheckApiService
{
    public const TIMEZONE = 'Asia/Dhaka';

    public function __construct(
        private PublicFraudCheckService $publicFraudCheck,
    ) {}

    /**
     * @return array{site_key: string, domain: string}
     */
    public function register(string $siteUrl): array
    {
        $site = CourierCheckSite::query()->create([
            'site_key' => (string) Str::uuid(),
            'domain' => $this->normalizeDomain($siteUrl),
        ]);

        return [
            'site_key' => (string) $site->site_key,
            'domain' => (string) $site->domain,
        ];
    }

    /**
     * @return array{
     *     couriers: list<array{name: string, total: int, delivered: int, returned: int}>,
     *     summary: array{total: int, delivered: int, returned: int, success_rate: int|float|null},
     *     label: string,
     *     label_text: string,
     *     remaining_today: int
     * }
     */
    public function check(string $phone, string $siteUrl, string $siteKey): array
    {
        if (! $this->publicFraudCheck->isEnabled()) {
            throw new CourierCheckException('unavailable', 503);
        }

        $site = CourierCheckSite::query()->where('site_key', $siteKey)->first();
        if (! $site instanceof CourierCheckSite || $site->revoked_at !== null) {
            throw new CourierCheckException('invalid_site_key', 401);
        }

        $domain = $this->normalizeDomain($siteUrl);
        if ($domain !== $site->domain) {
            throw new CourierCheckException('invalid_site_key', 401);
        }

        $limit = $this->publicFraudCheck->dailyFreeLimit();
        if ($this->usage($this->keyCacheKey($siteKey)) >= $limit || $this->usage($this->domainCacheKey($domain)) >= $limit) {
            throw new CourierCheckException('daily_limit', 429);
        }

        $lookup = $this->publicFraudCheck->lookup($phone, 'bn');
        $report = is_array($lookup['report'] ?? null) ? $lookup['report'] : [];
        $labelText = (string) ($lookup['risk_label'] ?? '');
        $label = $this->riskCode($labelText);

        $keyUsed = $this->increment($this->keyCacheKey($siteKey));
        $domainUsed = $this->increment($this->domainCacheKey($domain));

        $couriers = [];
        foreach ($report['courier'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = is_array($item['report'] ?? null) ? $item['report'] : [];
            $couriers[] = [
                'name' => (string) ($item['title'] ?? ''),
                'total' => (int) ($row['total_order'] ?? 0),
                'delivered' => (int) ($row['confirmed'] ?? 0),
                'returned' => (int) ($row['cancel'] ?? 0),
            ];
        }

        $rate = $label === 'unknown' ? null : $this->numericRate((string) ($report['success_rate'] ?? ''));

        return [
            'couriers' => $couriers,
            'summary' => [
                'total' => (int) ($report['total_order'] ?? 0),
                'delivered' => (int) ($report['confirmed'] ?? 0),
                'returned' => (int) ($report['cancel'] ?? 0),
                'success_rate' => $rate,
            ],
            'label' => $label,
            'label_text' => $labelText,
            'remaining_today' => max(0, $limit - max($keyUsed, $domainUsed)),
        ];
    }

    public function resetsAt(): string
    {
        return now(self::TIMEZONE)->addDay()->startOfDay()->toIso8601String();
    }

    public function normalizeDomain(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if (! preg_match('#^https?://#i', $siteUrl)) {
            throw new CourierCheckException('invalid_site_url', 422);
        }

        $parts = parse_url($siteUrl);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            throw new CourierCheckException('invalid_site_url', 422);
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $allowed = $host === 'localhost'
            || (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $host);

        if (! $allowed || strlen($host) > 253) {
            throw new CourierCheckException('invalid_site_url', 422);
        }

        return $host;
    }

    private function riskCode(string $label): string
    {
        return match ($label) {
            'নিরাপদ গ্রাহক', 'Safe customer' => 'low',
            'সতর্কতা প্রয়োজন', 'Caution advised' => 'medium',
            'ঝুঁকিপূর্ণ গ্রাহক', 'Risky customer' => 'high',
            'ইতিহাস নেই', 'No history' => 'unknown',
            default => 'unknown',
        };
    }

    private function numericRate(string $rate): int|float|null
    {
        if (! preg_match('/(\d+(?:\.\d+)?)/', $rate, $matches)) {
            return null;
        }

        $value = round((float) $matches[1], 1);

        return fmod($value, 1.0) === 0.0 ? (int) $value : $value;
    }

    private function usage(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    private function increment(string $key): int
    {
        $expires = now(self::TIMEZONE)->addDay()->startOfDay();
        if (! Cache::has($key)) {
            Cache::put($key, 1, $expires);

            return 1;
        }

        $next = Cache::increment($key);

        return $next === false ? 1 : (int) $next;
    }

    private function keyCacheKey(string $siteKey): string
    {
        return 'courier_check_api:key:'.$siteKey.':'.$this->day();
    }

    private function domainCacheKey(string $domain): string
    {
        return 'courier_check_api:domain:'.$domain.':'.$this->day();
    }

    private function day(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }
}
