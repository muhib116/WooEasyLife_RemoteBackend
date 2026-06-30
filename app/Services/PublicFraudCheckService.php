<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class PublicFraudCheckService
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('landing.fraud_check.enabled', true);
    }

    public function dailyFreeLimit(): int
    {
        return max(1, (int) config('landing.fraud_check.daily_free_limit', 5));
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(?string $ip = null): array
    {
        $limit = $this->dailyFreeLimit();
        $used = $ip ? $this->ipUsageCount($ip) : 0;
        $dailyCount = $this->dailySearchCount();

        return [
            'enabled' => $this->isEnabled(),
            'daily_free_limit' => $limit,
            'used_searches' => $used,
            'remaining_searches' => max(0, $limit - $used),
            'daily_search_count' => $dailyCount,
            'daily_search_label' => $this->formatCountLabel($dailyCount),
            'daily_search_phrase' => 'আজকে '.$this->formatCountLabel($dailyCount).' বার সার্চ হয়েছে',
            'free_search_note' => 'রেজিস্ট্রেশন ছাড়াই প্রতিদিন '.$limit.'টি ফ্রি সার্চ',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function check(string $ip, string $phone): array
    {
        if (! $this->isEnabled()) {
            throw new InvalidArgumentException('Public fraud check is currently unavailable.');
        }

        if ($this->ipUsageCount($ip) >= $this->dailyFreeLimit()) {
            return [
                'limited' => true,
                'message' => 'আপনি আজকের জন্য '.$this->dailyFreeLimit().' টি সার্চ সীমা পৌঁছে গেছেন। আগামীকাল আবার চেষ্টা করুন অথবা সাবস্ক্রিপশন নিন।',
                'meta' => $this->meta($ip),
            ];
        }

        $normalizedPhone = $this->fraudCheckService->normalizePhone($phone);
        $report = $this->fraudCheckService->getReport($normalizedPhone);

        $this->incrementIpUsage($ip);
        $this->incrementDailySearchCount();

        return [
            'limited' => false,
            'phone' => $normalizedPhone,
            'phone_masked' => $this->maskPhone($normalizedPhone),
            'risk_label' => $this->riskLabel($report),
            'risk_tone' => $this->riskTone($report),
            'report' => $report,
            'meta' => $this->meta($ip),
        ];
    }

    public function dailySearchCount(): int
    {
        return (int) Cache::get($this->dailyCountKey(), 0);
    }

    public function formatCountLabel(int $count): string
    {
        if ($count >= 100000) {
            return number_format($count / 100000, 1).' লক্ষ';
        }

        if ($count >= 1000) {
            $formatted = number_format($count / 1000, 1);

            return rtrim(rtrim($formatted, '0'), '.').'K+';
        }

        return (string) $count;
    }

    private function ipUsageCount(string $ip): int
    {
        return (int) Cache::get($this->ipUsageKey($ip), 0);
    }

    private function incrementIpUsage(string $ip): void
    {
        $this->incrementWithDailyTtl($this->ipUsageKey($ip));
    }

    private function incrementDailySearchCount(): void
    {
        $this->incrementWithDailyTtl($this->dailyCountKey());
    }

    private function incrementWithDailyTtl(string $key): void
    {
        if (! Cache::has($key)) {
            Cache::put($key, 1, now()->endOfDay());

            return;
        }

        Cache::increment($key);
    }

    private function dailyCountKey(): string
    {
        return 'landing_fraud_check:daily:'.now()->toDateString();
    }

    private function ipUsageKey(string $ip): string
    {
        return 'landing_fraud_check:ip:'.now()->toDateString().':'.$ip;
    }

    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 3).'********';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function riskLabel(array $report): string
    {
        $rate = (string) ($report['success_rate'] ?? '');
        $percent = $this->extractPercent($rate);
        $total = (int) ($report['total_order'] ?? 0);

        if ($total === 0 || str_contains(strtolower($rate), 'no order')) {
            return 'ইতিহাস নেই';
        }

        if ($percent === null) {
            if (str_contains(strtolower($rate), 'excellent') || str_contains(strtolower($rate), 'good')) {
                return 'নিরাপদ গ্রাহক';
            }

            if (str_contains(strtolower($rate), 'poor') || str_contains(strtolower($rate), 'risky')) {
                return 'ঝুঁকিপূর্ণ গ্রাহক';
            }

            return 'সতর্কতা প্রয়োজন';
        }

        if ($percent >= 75) {
            return 'নিরাপদ গ্রাহক';
        }

        if ($percent >= 45) {
            return 'সতর্কতা প্রয়োজন';
        }

        return 'ঝুঁকিপূর্ণ গ্রাহক';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function riskTone(array $report): string
    {
        return match ($this->riskLabel($report)) {
            'নিরাপদ গ্রাহক' => 'safe',
            'ঝুঁকিপূর্ণ গ্রাহক' => 'risky',
            'ইতিহাস নেই' => 'neutral',
            default => 'caution',
        };
    }

    private function extractPercent(string $rate): ?int
    {
        if (! preg_match('/(\d+)/', $rate, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
