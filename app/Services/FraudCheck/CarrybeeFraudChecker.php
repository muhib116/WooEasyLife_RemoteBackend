<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CarrybeeFraudChecker
{
    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
    ) {}

    public function isConfigured(): bool
    {
        return $this->credentials->isConfigured('carrybee');
    }

    public function expireSession(): bool
    {
        return $this->credentials->forgetSessionCaches('carrybee') > 0;
    }

    public function check(string $phone): array
    {
        if (! $this->isConfigured()) {
            LogHelper::saveLog('Carrybee fraud check skipped', 'No Carrybee credentials configured in admin or .env.');

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

            $response = $this->fetchCustomer($phone, $auth['accessToken'], $auth['businessId']);

            if ($response->status() === 401) {
                Cache::forget($auth['cache_key']);
                $auth = $this->authenticate(force: true);
                if ($auth === null) {
                    return CourierReportFormatter::emptyReport([
                        'unavailable' => true,
                        'message' => 'Carrybee token expired and re-login failed.',
                    ]);
                }

                $response = $this->fetchCustomer($phone, $auth['accessToken'], $auth['businessId']);
            }

            if (! $response->successful() || $response->json('error')) {
                LogHelper::saveLog('Carrybee fraud check error', substr($response->body(), 0, 300));

                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => 'Carrybee customer lookup failed (HTTP '.$response->status().').',
                ]);
            }

            $this->credentials->markSuccess($auth['credential_id']);

            $data = $response->json('data');
            if (! is_array($data)) {
                return CourierReportFormatter::emptyReport([
                    'api_success' => true,
                    'message' => 'No delivery history found on Carrybee.',
                ]);
            }

            return $this->mapCustomerPayload($data);
        } catch (Throwable $e) {
            LogHelper::saveLog('Carrybee fraud check error', $e->getMessage());

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapCustomerPayload(array $data): array
    {
        $total = (int) ($data['total_order'] ?? $data['total'] ?? 0);
        $cancel = (int) ($data['cancelled_order'] ?? $data['cancel'] ?? $data['cancelled'] ?? 0);
        $confirmed = (int) ($data['success_order'] ?? $data['delivered'] ?? $data['confirmed'] ?? 0);
        $fraudsCount = (int) ($data['fraud_count'] ?? $data['frauds_count'] ?? 0);
        $rawRate = $data['success_rate'] ?? null;

        // Prefer API success_rate over (total - cancel), which would treat pending as delivered.
        if ($confirmed === 0 && $total > 0 && is_numeric($rawRate)) {
            $confirmed = (int) round($total * ((float) $rawRate / 100));
            $confirmed = max(0, min($confirmed, $total));
        }

        if ($confirmed === 0 && $total > 0 && $cancel > 0) {
            $confirmed = max(0, $total - $cancel);
        }

        // Only invent cancel when the API omitted it entirely.
        $hasExplicitCancel = array_key_exists('cancelled_order', $data)
            || array_key_exists('cancel', $data)
            || array_key_exists('cancelled', $data);

        if (! $hasExplicitCancel && $cancel === 0 && $total > $confirmed) {
            $cancel = $total - $confirmed;
        }

        if ($total === 0 && ($confirmed > 0 || $cancel > 0)) {
            $total = $confirmed + $cancel;
        }

        $successRate = null;
        if (is_numeric($rawRate)) {
            $successRate = ((int) ceil((float) $rawRate)).'%';
        } elseif (is_string($rawRate) && $rawRate !== '') {
            $successRate = $rawRate;
        }

        return CourierReportFormatter::fromCounts($confirmed, $cancel, array_filter([
            'total_order' => $total,
            'success_rate' => $successRate,
            'frauds_count' => $fraudsCount,
            'customer_name' => isset($data['name']) ? (string) $data['name'] : null,
            'api_success' => true,
            'data_type' => 'delivery',
        ], fn ($value) => $value !== null));
    }

    private function fetchCustomer(string $phone, string $accessToken, string $businessId)
    {
        if (! filled($businessId)) {
            throw new \RuntimeException('Carrybee business id missing from session.');
        }

        return $this->client()
            ->withToken($accessToken)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Origin' => 'https://merchant.carrybee.com',
                'Referer' => 'https://merchant.carrybee.com/',
            ])
            ->get('https://api-merchant.carrybee.com/api/v2/businesses/'.$businessId.'/customers/'.$phone);
    }

    /**
     * @return array{accessToken: string, businessId: string, cache_key: string, credential_id: int|null}|null
     */
    private function authenticate(bool $force = false): ?array
    {
        if (! $force) {
            foreach ($this->credentials->candidates('carrybee') as $candidate) {
                $cacheKey = $this->credentials->sessionCacheKey('carrybee', $candidate['identifier']);
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && filled($cached['accessToken'] ?? null) && filled($cached['businessId'] ?? null)) {
                    return [
                        'accessToken' => (string) $cached['accessToken'],
                        'businessId' => (string) $cached['businessId'],
                        'cache_key' => $cacheKey,
                        'credential_id' => $candidate['id'],
                    ];
                }
            }
        }

        // Session expired / no cache: random active credential, then failover.
        foreach ($this->credentials->loginCandidates('carrybee') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('carrybee', $candidate['identifier']);

            if ($force) {
                Cache::forget($cacheKey);
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
                $this->credentials->markFailure($candidate['id'], 'CSRF fetch failed');

                continue;
            }

            $phone = (string) $candidate['identifier'];
            $loginPhone = str_starts_with($phone, '+88') ? $phone : '+88'.$phone;

            $this->client()
                ->withOptions(['cookies' => $jar])
                ->withHeaders($headers)
                ->asForm()
                ->post('https://merchant.carrybee.com/api/auth/callback/login?', [
                    'phone' => $loginPhone,
                    'password' => $candidate['password'],
                    'csrfToken' => $csrfToken,
                    'callbackUrl' => 'https://merchant.carrybee.com/login',
                ]);

            $session = $this->client()
                ->withOptions(['cookies' => $jar])
                ->withHeaders($headers)
                ->get('https://merchant.carrybee.com/api/auth/session');

            $accessToken = $session->json('accessToken');
            $businessId = (string) ($session->json('user.selectedBusinessId') ?? '');

            if (! $session->successful() || ! filled($accessToken) || ! filled($businessId)) {
                $this->credentials->markFailure(
                    $candidate['id'],
                    'Session missing token/business: '.substr($session->body(), 0, 200),
                );
                LogHelper::saveLog('Carrybee fraud check error', 'Session missing token/business: '.substr($session->body(), 0, 300));

                continue;
            }

            $payload = [
                'accessToken' => (string) $accessToken,
                'businessId' => $businessId,
            ];

            Cache::put($cacheKey, $payload, now()->addMinutes(50));
            $this->credentials->markUsed($candidate['id']);

            return [
                'accessToken' => (string) $accessToken,
                'businessId' => $businessId,
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
            ->acceptJson();
    }
}
