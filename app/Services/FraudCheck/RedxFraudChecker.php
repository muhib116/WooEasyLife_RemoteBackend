<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class RedxFraudChecker
{
    private const LOGIN_URL = 'https://api.redx.com.bd/v4/auth/login';

    private const STATS_URL = 'https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate';

    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
    ) {}

    public function isConfigured(): bool
    {
        return $this->credentials->isConfigured('redx');
    }

    public function expireSession(): bool
    {
        return $this->credentials->forgetSessionCaches('redx') > 0;
    }

    public function check(string $phone): array
    {
        if (! $this->isConfigured()) {
            LogHelper::saveLog('RedX fraud check skipped', 'No RedX credentials configured in admin or .env.');

            return CourierReportFormatter::emptyReport();
        }

        try {
            $auth = $this->resolveToken();
            if ($auth === null) {
                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => 'RedX login failed with configured credentials.',
                ]);
            }

            $response = $this->client()
                ->withToken($auth['token'])
                ->get(self::STATS_URL, [
                    'phoneNumber' => '88'.$phone,
                ]);

            if ($response->status() === 401) {
                Cache::forget($auth['cache_key']);
                $auth = $this->resolveToken(force: true);
                if ($auth === null) {
                    return CourierReportFormatter::emptyReport([
                        'unavailable' => true,
                        'message' => 'RedX token expired and re-login failed.',
                    ]);
                }

                $response = $this->client()
                    ->withToken($auth['token'])
                    ->get(self::STATS_URL, [
                        'phoneNumber' => '88'.$phone,
                    ]);
            }

            if (! $response->successful()) {
                LogHelper::saveLog('RedX fraud check error', 'Stats failed with status '.$response->status());

                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => 'RedX stats request failed (HTTP '.$response->status().').',
                ]);
            }

            $this->credentials->markSuccess($auth['credential_id']);

            $data = $response->json('data', []);
            $success = (int) ($data['deliveredParcels'] ?? 0);
            $total = (int) ($data['totalParcels'] ?? 0);
            $cancel = max(0, $total - $success);

            return array_merge(CourierReportFormatter::fromCounts($success, $cancel), [
                'customer_segment' => $data['customerSegment'] ?? null,
                'return_percentage' => $data['returnPercentage'] ?? null,
                'api_success' => true,
            ]);
        } catch (Throwable $e) {
            LogHelper::saveLog('RedX fraud check error', $e->getMessage());

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{token: string, cache_key: string, credential_id: int|null}|null
     */
    private function resolveToken(bool $force = false): ?array
    {
        if (! $force) {
            foreach ($this->credentials->candidates('redx') as $candidate) {
                $cacheKey = $this->credentials->sessionCacheKey('redx', $candidate['identifier']);
                if ($cached = Cache::get($cacheKey)) {
                    return [
                        'token' => (string) $cached,
                        'cache_key' => $cacheKey,
                        'credential_id' => $candidate['id'],
                    ];
                }
            }
        }

        // Session expired / no cache: pick a random active credential, then failover.
        foreach ($this->credentials->loginCandidates('redx') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('redx', $candidate['identifier']);

            if ($force) {
                Cache::forget($cacheKey);
            }

            $response = $this->client()->post(self::LOGIN_URL, [
                'phone' => '88'.$candidate['identifier'],
                'password' => $candidate['password'],
            ]);

            $token = $response->json('data.accessToken');

            if (! $response->successful() || ! filled($token)) {
                $this->credentials->markFailure(
                    $candidate['id'],
                    'Login failed: '.substr($response->body(), 0, 200),
                );
                LogHelper::saveLog('RedX fraud check error', 'Login failed for '.$candidate['identifier'].': '.substr($response->body(), 0, 300));

                continue;
            }

            Cache::put($cacheKey, $token, now()->addMinutes(50));
            $this->credentials->markUsed($candidate['id']);

            return [
                'token' => (string) $token,
                'cache_key' => $cacheKey,
                'credential_id' => $candidate['id'],
            ];
        }

        return null;
    }

    private function client()
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->retry(2, 800, throw: false)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://redx.com.bd',
                'Referer' => 'https://redx.com.bd/',
            ]);
    }
}
