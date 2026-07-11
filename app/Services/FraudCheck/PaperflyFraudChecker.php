<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PaperflyFraudChecker
{
    private string $baseUrl = 'https://go-app.paperfly.com.bd/merchant/api/react';

    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
    ) {}

    public function isConfigured(): bool
    {
        return $this->credentials->isConfigured('paperfly');
    }

    public function expireToken(): bool
    {
        return $this->credentials->forgetSessionCaches('paperfly') > 0;
    }

    public function check(string $phone): array
    {
        if (! $this->isConfigured()) {
            LogHelper::saveLog('Paperfly fraud check skipped', 'No Paperfly credentials configured in admin or .env.');

            return CourierReportFormatter::emptyReport();
        }

        try {
            return $this->fetchSmartCheck($phone);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Paperfly fraud check error', $th->getMessage());

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => $th->getMessage(),
            ]);
        }
    }

    private function fetchSmartCheck(string $phone, bool $forceRefresh = false): array
    {
        $auth = $this->resolveToken($forceRefresh);
        if ($auth === null) {
            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => 'Paperfly login failed with configured credentials.',
            ]);
        }

        $response = Http::timeout(30)
            ->withToken($auth['token'])
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Origin' => 'https://go-app.paperfly.com.bd',
                'Referer' => 'https://go-app.paperfly.com.bd/',
            ])
            ->post("{$this->baseUrl}/smart-check/list.php", [
                'search_text' => $phone,
                'limit' => 50,
                'page' => 1,
            ]);

        if ($response->status() === 401 && ! $forceRefresh) {
            Cache::forget($auth['cache_key']);

            return $this->fetchSmartCheck($phone, true);
        }

        if (! $response->successful()) {
            LogHelper::saveLog('Paperfly fraud check error', 'Smart check failed with status '.$response->status());

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => 'Paperfly smart-check failed (HTTP '.$response->status().').',
            ]);
        }

        $this->credentials->markSuccess($auth['credential_id']);

        $payload = $response->json() ?? [];
        $report = CourierReportFormatter::fromPaperfly($payload);

        if (($report['total_order'] ?? 0) > 0) {
            return $report;
        }

        $totalRecords = (int) ($payload['totalRecords'] ?? $payload['total_records'] ?? 0);
        $records = $payload['records'] ?? null;
        $recordsEmpty = ! is_array($records) || $records === [];

        if ($totalRecords > 0 && $recordsEmpty) {
            return CourierReportFormatter::emptyReport([
                'api_success' => true,
                'data_type' => 'delivery',
                'message' => 'Paperfly found matches but returned no delivery detail rows for this merchant account.',
            ]);
        }

        return CourierReportFormatter::emptyReport([
            'api_success' => true,
            'data_type' => 'delivery',
            'message' => 'No delivery history found on Paperfly.',
        ]);
    }

    /**
     * @return array{token: string, cache_key: string, credential_id: int|null}|null
     */
    private function resolveToken(bool $forceRefresh = false): ?array
    {
        if (! $forceRefresh) {
            foreach ($this->credentials->candidates('paperfly') as $candidate) {
                $cacheKey = $this->credentials->sessionCacheKey('paperfly', $candidate['identifier']);
                if ($cached = Cache::get($cacheKey)) {
                    return [
                        'token' => (string) $cached,
                        'cache_key' => $cacheKey,
                        'credential_id' => $candidate['id'],
                    ];
                }
            }
        }

        // Session expired / no cache: random active credential, then failover.
        foreach ($this->credentials->loginCandidates('paperfly') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('paperfly', $candidate['identifier']);
            Cache::forget($cacheKey);

            $response = Http::timeout(30)->post("{$this->baseUrl}/authentication/login_using_password.php", [
                'username' => $candidate['identifier'],
                'password' => $candidate['password'],
            ]);

            $token = $response->json('token');

            if (! $response->successful() || empty($token)) {
                $this->credentials->markFailure($candidate['id'], 'Login failed');
                LogHelper::saveLog('Paperfly fraud check error', 'Login failed for '.$candidate['identifier']);

                continue;
            }

            Cache::put($cacheKey, $token, now()->addMinutes(30));
            $this->credentials->markUsed($candidate['id']);

            return [
                'token' => (string) $token,
                'cache_key' => $cacheKey,
                'credential_id' => $candidate['id'],
            ];
        }

        return null;
    }
}
