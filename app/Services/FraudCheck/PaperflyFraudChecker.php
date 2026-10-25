<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

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

            return CourierReportFormatter::emptyReport([
                'retryable' => false,
                'message' => 'Paperfly is not set up for fraud checks.',
            ]);
        }

        try {
            return $this->fetchSmartCheck($phone);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Paperfly fraud check error', CourierTransport::safeMessage($th));

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => 'Paperfly did not answer this time. This number is checked again automatically.',
            ]);
        }
    }

    /**
     * Pool the smart-check call when a Paperfly token is already cached.
     * Login stays on check().
     *
     * @return array{send: callable, read: callable}|null
     */
    public function poolPlan(string $phone, ?array $credentials = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $auth = null;
        foreach ($this->credentials->candidates('paperfly') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('paperfly', $candidate['identifier']);
            $cached = Cache::get($cacheKey);
            if (! is_string($cached) || $cached === '') {
                continue;
            }

            $auth = [
                'token' => $cached,
                'cache_key' => $cacheKey,
                'credential_id' => $candidate['id'],
            ];
            break;
        }

        if ($auth === null) {
            return null;
        }

        $search = $this->searchPhone($phone);

        return [
            'send' => function (Pool $pool, string $name) use ($auth, $search): void {
                $pool->as($name)
                    ->timeout(30)
                    ->connectTimeout(10)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        ],
                    ])
                    ->acceptJson()
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                        'Accept' => 'application/json, text/plain, */*',
                        'Origin' => 'https://go.paperfly.com.bd',
                        'Referer' => 'https://go.paperfly.com.bd/',
                    ])
                    ->withToken($auth['token'])
                    ->post($this->baseUrl.'/smart-check/smart-check-v2.php?search_text='.rawurlencode($search), [
                        'search_text' => $search,
                    ]);
            },
            'read' => function (Response $response) use ($auth): ?array {
                if (in_array($response->status(), [401, 403], true)) {
                    Cache::forget($auth['cache_key']);

                    return null;
                }

                $stats = $this->readSmartCheck($response);
                if (! $stats['ok']) {
                    LogHelper::saveLog('Paperfly fraud check error', 'Smart check failed with status '.$response->status());

                    return null;
                }

                $this->credentials->markSuccess($auth['credential_id']);

                return $this->mapSmartCheck($stats['data']);
            },
        ];
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

        $phone = $this->searchPhone($phone);
        $response = $this->client()
            ->withToken($auth['token'])
            ->post($this->baseUrl.'/smart-check/smart-check-v2.php?search_text='.rawurlencode($phone), [
                'search_text' => $phone,
            ]);

        if ($response->status() === 401 && ! $forceRefresh) {
            Cache::forget($auth['cache_key']);

            return $this->fetchSmartCheck($phone, true);
        }

        $stats = $this->readSmartCheck($response);
        if (! $stats['ok']) {
            LogHelper::saveLog('Paperfly fraud check error', 'Smart check failed with status '.$response->status());

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => $stats['message'] ?? ('Paperfly smart-check failed (HTTP '.$response->status().').'),
            ]);
        }

        $this->credentials->markSuccess($auth['credential_id']);

        return $this->mapSmartCheck($stats['data']);
    }

    /**
     * Paperfly's current merchant panel reads smart-check-v2. The older list
     * endpoint still answers HTTP 200 with an empty records array.
     *
     * @return array{ok: bool, data?: array<string, mixed>, message?: string}
     */
    private function readSmartCheck(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return [
                'ok' => false,
                'message' => 'Paperfly smart-check failed (HTTP '.$response->status().').',
            ];
        }

        if (($json['isError'] ?? false) === true || ($json['error'] ?? false) === true) {
            return [
                'ok' => false,
                'message' => $this->failureMessage($response, $json),
            ];
        }

        $hasCounts = array_key_exists('delivered', $json)
            || array_key_exists('returned', $json)
            || array_key_exists('total', $json);

        if (! $response->successful() || ! $hasCounts) {
            return [
                'ok' => false,
                'message' => $this->failureMessage($response, $json),
            ];
        }

        return ['ok' => true, 'data' => $json];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapSmartCheck(array $data): array
    {
        $delivered = max(0, (int) ($data['delivered'] ?? 0));
        $returned = max(0, (int) ($data['returned'] ?? 0));
        $partial = max(0, (int) ($data['partial'] ?? 0));

        if ($delivered === 0 && $returned === 0) {
            return CourierReportFormatter::emptyReport([
                'api_success' => true,
                'partial' => $partial,
                'message' => $partial > 0
                    ? 'Paperfly has '.$partial.' partial parcel'.($partial === 1 ? '' : 's').' and no finished delivery or return for this number.'
                    : 'No delivery history found on Paperfly.',
            ]);
        }

        $report = array_merge(CourierReportFormatter::fromCounts($delivered, $returned), [
            'api_success' => true,
            'partial' => $partial,
        ]);

        $rate = Arr::get($data, 'smart_check.delivery_rate');
        if (is_numeric($rate)) {
            $report['success_rate'] = $this->formatPercent((float) $rate);
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function failureMessage(Response $response, array $json): string
    {
        $message = trim((string) ($json['message'] ?? ''));
        if (
            $message !== ''
            && strlen($message) <= 160
            && ! str_contains($message, '<')
            && ! preg_match('/(?:\+?88)?01[3-9]\d{8}/', $message)
        ) {
            return 'Paperfly smart-check failed: '.$message;
        }

        return 'Paperfly smart-check failed (HTTP '.$response->status().').';
    }

    private function formatPercent(float $rate): string
    {
        $formatted = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted).'%';
    }

    private function searchPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->withOptions([
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->retry(2, 800, fn (Throwable $exception) => CourierTransport::shouldRetry($exception), throw: false)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://go.paperfly.com.bd',
                'Referer' => 'https://go.paperfly.com.bd/',
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

            $response = $this->client()->post("{$this->baseUrl}/authentication/login_using_password.php", [
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
