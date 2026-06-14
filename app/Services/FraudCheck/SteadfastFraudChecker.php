<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Support\Facades\Cache;

class SteadfastFraudChecker
{
    public function __construct(
        private SteadfastNativeClient $nativeClient,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('fraud-checker-bd-courier.steadfast.user'))
            && filled(config('fraud-checker-bd-courier.steadfast.password'));
    }

    public function check(string $phone): array
    {
        $report = $this->fetchUsingSavedCurl($phone);

        if ($report !== null) {
            return $report;
        }

        $report = $this->fetchUsingCachedSession($phone);

        if ($report !== null) {
            return $report;
        }

        if (!$this->isConfigured()) {
            LogHelper::saveLog('Steadfast fraud check skipped', 'STEADFAST_USER and STEADFAST_PASSWORD are not configured.');

            return CourierReportFormatter::emptyReport(['frauds' => []]);
        }

        return $this->loginOnceAndFetch($phone);
    }

    private function fetchUsingSavedCurl(string $phone): ?array
    {
        if (!file_exists(SteadfastCurlExporter::path())) {
            return null;
        }

        try {
            $raw = SteadfastCurlExporter::run($phone);

            if (!SteadfastCurlExporter::isValid($raw)) {
                return null;
            }

            return CourierReportFormatter::fromSteadfast(json_decode($raw, true) ?? []);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast legacy curl fraud check error', $th->getMessage());
        }

        return null;
    }

    private function fetchUsingCachedSession(string $phone): ?array
    {
        $cached = Cache::get($this->sessionCacheKey());

        if (!is_array($cached) || empty($cached['cookies']) || empty($cached['host'])) {
            return null;
        }

        try {
            $raw = $this->nativeClient->fetchFraudCheckRaw(
                (string) $cached['host'],
                (array) $cached['cookies'],
                $phone,
            );

            return CourierReportFormatter::fromSteadfast(json_decode($raw, true) ?? []);
        } catch (\Throwable $th) {
            $this->forgetSession();
            LogHelper::saveLog('Steadfast fraud check error', 'Cached session expired: ' . $th->getMessage());
        }

        return null;
    }

    private function loginOnceAndFetch(string $phone): array
    {
        $empty = CourierReportFormatter::emptyReport(['frauds' => []]);
        $host = SteadfastNativeClient::DEFAULT_HOST;

        try {
            $cookies = $this->nativeClient->login(
                $host,
                (string) config('fraud-checker-bd-courier.steadfast.user'),
                (string) config('fraud-checker-bd-courier.steadfast.password'),
            );

            $raw = $this->nativeClient->fetchFraudCheckRaw($host, $cookies, $phone);

            $this->storeSession($host, $cookies);
            SteadfastCurlExporter::save($host, $cookies, $phone);

            return CourierReportFormatter::fromSteadfast(json_decode($raw, true) ?? []);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast fraud check error', "Login refresh failed on {$host}: {$th->getMessage()}");
        }

        return $empty;
    }

    private function sessionCacheKey(): string
    {
        return 'fraud_check_steadfast_session_' . md5((string) config('fraud-checker-bd-courier.steadfast.user'));
    }

    private function storeSession(string $host, array $cookies): void
    {
        Cache::put($this->sessionCacheKey(), [
            'host' => $host,
            'cookies' => $cookies,
        ], now()->addMinutes(55));
    }

    private function forgetSession(): void
    {
        Cache::forget($this->sessionCacheKey());
    }
}
