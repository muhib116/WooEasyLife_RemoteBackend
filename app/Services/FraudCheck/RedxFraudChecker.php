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

    public function isConfigured(): bool
    {
        return filled(config('courier-checker.redx.phone'))
            && filled(config('courier-checker.redx.password'));
    }

    public function expireSession(): bool
    {
        $had = Cache::has($this->cacheKey());
        Cache::forget($this->cacheKey());

        return $had;
    }

    public function check(string $phone): array
    {
        if (! $this->isConfigured()) {
            LogHelper::saveLog('RedX fraud check skipped', 'REDX_PHONE / REDX_PASSWORD are not configured.');

            return CourierReportFormatter::emptyReport();
        }

        try {
            $token = $this->accessToken();
            if ($token === null) {
                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => 'RedX login failed with configured credentials.',
                ]);
            }

            $response = $this->client()
                ->withToken($token)
                ->get(self::STATS_URL, [
                    'phoneNumber' => '88'.$phone,
                ]);

            if ($response->status() === 401) {
                Cache::forget($this->cacheKey());
                $token = $this->accessToken(force: true);
                if ($token === null) {
                    return CourierReportFormatter::emptyReport([
                        'unavailable' => true,
                        'message' => 'RedX token expired and re-login failed.',
                    ]);
                }

                $response = $this->client()
                    ->withToken($token)
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

    private function accessToken(bool $force = false): ?string
    {
        if (! $force && ($cached = Cache::get($this->cacheKey()))) {
            return (string) $cached;
        }

        $response = $this->client()->post(self::LOGIN_URL, [
            'phone' => '88'.config('courier-checker.redx.phone'),
            'password' => config('courier-checker.redx.password'),
        ]);

        $token = $response->json('data.accessToken');

        if (! $response->successful() || ! filled($token)) {
            LogHelper::saveLog('RedX fraud check error', 'Login failed: '.substr($response->body(), 0, 300));

            return null;
        }

        Cache::put($this->cacheKey(), $token, now()->addMinutes(50));

        return (string) $token;
    }

    private function cacheKey(): string
    {
        return 'fraud_check_redx_token_'.md5((string) config('courier-checker.redx.phone'));
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
