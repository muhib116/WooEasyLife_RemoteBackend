<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PaperflyFraudChecker
{
    private string $baseUrl = 'https://go-app.paperfly.com.bd/merchant/api/react';

    public function isConfigured(): bool
    {
        return filled(config('fraud-checker-bd-courier.paperfly.user'))
            && filled(config('fraud-checker-bd-courier.paperfly.password'));
    }

    public function expireToken(): bool
    {
        $cacheKey = $this->tokenCacheKey();
        $hadToken = Cache::has($cacheKey);
        Cache::forget($cacheKey);

        return $hadToken;
    }

    public function check(string $phone): array
    {
        if (!$this->isConfigured()) {
            LogHelper::saveLog('Paperfly fraud check skipped', 'PAPERFLY_USER and PAPERFLY_PASSWORD are not configured.');

            return CourierReportFormatter::emptyReport();
        }

        try {
            return $this->fetchSmartCheck($phone);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Paperfly fraud check error', $th->getMessage());

            return CourierReportFormatter::emptyReport();
        }
    }

    private function fetchSmartCheck(string $phone, bool $forceRefresh = false): array
    {
        $response = Http::timeout(30)
            ->withToken($this->getToken($forceRefresh))
            ->acceptJson()
            ->post("{$this->baseUrl}/smart-check/list.php", [
                'search_text' => $phone,
                'limit' => 50,
                'page' => 1,
            ]);

        if ($response->status() === 401 && !$forceRefresh) {
            return $this->fetchSmartCheck($phone, true);
        }

        if (!$response->successful()) {
            LogHelper::saveLog('Paperfly fraud check error', 'Smart check failed with status ' . $response->status());

            return CourierReportFormatter::emptyReport();
        }

        return CourierReportFormatter::fromPaperfly($response->json() ?? []);
    }

    private function tokenCacheKey(): string
    {
        return 'fraud_check_paperfly_token_' . md5((string) config('fraud-checker-bd-courier.paperfly.user'));
    }

    private function getToken(bool $forceRefresh = false): string
    {
        $cacheKey = $this->tokenCacheKey();

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(30), function () {
            $response = Http::timeout(30)->post("{$this->baseUrl}/authentication/login_using_password.php", [
                'username' => config('fraud-checker-bd-courier.paperfly.user'),
                'password' => config('fraud-checker-bd-courier.paperfly.password'),
            ]);

            $token = $response->json('token');

            if (!$response->successful() || empty($token)) {
                throw new \RuntimeException('Paperfly login failed.');
            }

            return $token;
        });
    }
}
