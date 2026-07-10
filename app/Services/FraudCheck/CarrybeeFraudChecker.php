<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CarrybeeFraudChecker
{
    public function isConfigured(): bool
    {
        return filled(config('courier-checker.carrybee.phone'))
            && filled(config('courier-checker.carrybee.password'));
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
            LogHelper::saveLog('Carrybee fraud check skipped', 'CARRYBEE_PHONE / CARRYBEE_PASSWORD are not configured.');

            return CourierReportFormatter::emptyReport();
        }

        try {
            $auth = $this->authenticate();
            if ($auth === null) {
                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => 'Carrybee login failed with configured credentials.',
                ]);
            }

            // Correct endpoint (package used a broken businesses/{id}/fraud-check path).
            $response = $this->client()
                ->withToken($auth['accessToken'])
                ->get('https://api-merchant.carrybee.com/api/v2/fraud-check/'.$phone);

            if ($response->status() === 401) {
                Cache::forget($this->cacheKey());
                $auth = $this->authenticate(force: true);
                if ($auth === null) {
                    return CourierReportFormatter::emptyReport([
                        'unavailable' => true,
                        'message' => 'Carrybee token expired and re-login failed.',
                    ]);
                }

                $response = $this->client()
                    ->withToken($auth['accessToken'])
                    ->get('https://api-merchant.carrybee.com/api/v2/fraud-check/'.$phone);
            }

            if (! $response->successful() || $response->json('error')) {
                LogHelper::saveLog('Carrybee fraud check error', substr($response->body(), 0, 300));

                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => 'Carrybee fraud-check failed (HTTP '.$response->status().').',
                ]);
            }

            $count = (int) ($response->json('data.count') ?? 0);

            return array_merge(CourierReportFormatter::emptyReport([
                'data_type' => 'fraud_reports',
                'frauds_count' => $count,
                'api_success' => true,
                'success_rate' => $count > 0
                    ? "{$count} fraud report(s)"
                    : 'No fraud reports',
            ]), [
                // Carrybee public fraud API currently returns report count only.
                'total_order' => 0,
                'confirmed' => 0,
                'cancel' => 0,
            ]);
        } catch (Throwable $e) {
            LogHelper::saveLog('Carrybee fraud check error', $e->getMessage());

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{accessToken: string, businessId: string}|null
     */
    private function authenticate(bool $force = false): ?array
    {
        if (! $force && ($cached = Cache::get($this->cacheKey()))) {
            if (is_array($cached) && filled($cached['accessToken'] ?? null)) {
                return $cached;
            }
        }

        $jar = new CookieJar();
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Referer' => 'https://merchant.carrybee.com/login',
            'Origin' => 'https://merchant.carrybee.com',
        ];

        $csrf = $this->client()
            ->withOptions(['cookies' => $jar])
            ->withHeaders($headers)
            ->get('https://merchant.carrybee.com/api/auth/csrf');

        $csrfToken = $csrf->json('csrfToken');
        if (! $csrf->successful() || ! filled($csrfToken)) {
            return null;
        }

        $phone = (string) config('courier-checker.carrybee.phone');
        $loginPhone = str_starts_with($phone, '+88') ? $phone : '+88'.$phone;

        $this->client()
            ->withOptions(['cookies' => $jar])
            ->withHeaders($headers)
            ->asForm()
            ->post('https://merchant.carrybee.com/api/auth/callback/login?', [
                'phone' => $loginPhone,
                'password' => config('courier-checker.carrybee.password'),
                'csrfToken' => $csrfToken,
                'callbackUrl' => 'https://merchant.carrybee.com/login',
            ]);

        $session = $this->client()
            ->withOptions(['cookies' => $jar])
            ->withHeaders($headers)
            ->get('https://merchant.carrybee.com/api/auth/session');

        $accessToken = $session->json('accessToken');
        $businessId = (string) ($session->json('user.selectedBusinessId') ?? '');

        if (! $session->successful() || ! filled($accessToken)) {
            LogHelper::saveLog('Carrybee fraud check error', 'Session missing token: '.substr($session->body(), 0, 300));

            return null;
        }

        $payload = [
            'accessToken' => (string) $accessToken,
            'businessId' => $businessId,
        ];

        Cache::put($this->cacheKey(), $payload, now()->addMinutes(50));

        return $payload;
    }

    private function cacheKey(): string
    {
        return 'fraud_check_carrybee_token_'.md5((string) config('courier-checker.carrybee.phone'));
    }

    private function client()
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->retry(2, 800, throw: false)
            ->acceptJson();
    }
}
