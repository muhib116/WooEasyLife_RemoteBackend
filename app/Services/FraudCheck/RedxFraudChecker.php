<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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

            $stats = $this->readStats($response);
            if (! empty($stats['placeholder'])) {
                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => RedxStaticSample::MESSAGE,
                ]);
            }

            if (! $stats['ok']) {
                LogHelper::saveLog('RedX fraud check error', 'Stats failed with status '.$response->status());

                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => $stats['message'] ?? ('RedX stats request failed (HTTP '.$response->status().').'),
                ]);
            }

            $this->credentials->markSuccess($auth['credential_id']);

            if (! empty($stats['empty'])) {
                return CourierReportFormatter::emptyReport([
                    'api_success' => true,
                    'message' => 'No delivery history found on RedX.',
                ]);
            }

            return $this->mapStats($stats['data']);
        } catch (Throwable $e) {
            LogHelper::saveLog('RedX fraud check error', $e->getMessage());

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pool the stats call when a RedX token is already cached.
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
        foreach ($this->credentials->candidates('redx') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('redx', $candidate['identifier']);
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

        return [
            'send' => function (Pool $pool, string $name) use ($auth, $phone): void {
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
                        'Origin' => 'https://redx.com.bd',
                        'Referer' => 'https://redx.com.bd/',
                    ])
                    ->withToken($auth['token'])
                    ->get(self::STATS_URL, [
                        'phoneNumber' => '88'.$phone,
                    ]);
            },
            'read' => function (Response $response) use ($auth): ?array {
                if ($response->status() === 401) {
                    return null;
                }

                $stats = $this->readStats($response);
                if (! empty($stats['placeholder'])) {
                    return CourierReportFormatter::emptyReport([
                        'unavailable' => true,
                        'message' => RedxStaticSample::MESSAGE,
                    ]);
                }

                if (! $stats['ok']) {
                    LogHelper::saveLog('RedX fraud check error', 'Stats failed with status '.$response->status());

                    return CourierReportFormatter::emptyReport([
                        'unavailable' => true,
                        'message' => $stats['message'] ?? ('RedX stats request failed (HTTP '.$response->status().').'),
                    ]);
                }

                $this->credentials->markSuccess($auth['credential_id']);

                if (! empty($stats['empty'])) {
                    return CourierReportFormatter::emptyReport([
                        'api_success' => true,
                        'message' => 'No delivery history found on RedX.',
                    ]);
                }

                return $this->mapStats($stats['data']);
            },
        ];
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

    /**
     * RedX answers this stats call with HTTP 400 even when the JSON envelope
     * is a success (`code` 200, `isError` false, parcel counts present).
     *
     * @return array{ok: bool, empty?: bool, data?: array<string, mixed>, message?: string}
     */
    private function readStats(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return [
                'ok' => false,
                'message' => 'RedX stats request failed (HTTP '.$response->status().').',
            ];
        }

        if (($json['isError'] ?? false) === true) {
            return [
                'ok' => false,
                'message' => $this->statsFailureMessage($response, $json),
            ];
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : null;
        $hasCounts = is_array($data) && (
            array_key_exists('totalParcels', $data) || array_key_exists('deliveredParcels', $data)
        );

        if ($hasCounts && RedxStaticSample::matchesApi($data)) {
            return [
                'ok' => false,
                'placeholder' => true,
                'message' => RedxStaticSample::MESSAGE,
            ];
        }
        $envelopeOk = array_key_exists('code', $json)
            && (int) $json['code'] === 200
            && ($json['isError'] ?? false) === false;

        if ($response->status() === 401) {
            return [
                'ok' => false,
                'message' => 'RedX token expired and re-login failed.',
            ];
        }

        if ($response->successful()) {
            if (! $hasCounts) {
                return ['ok' => true, 'empty' => true];
            }

            return ['ok' => true, 'data' => $data];
        }

        if ($envelopeOk && $hasCounts) {
            return ['ok' => true, 'data' => $data];
        }

        return [
            'ok' => false,
            'message' => $this->statsFailureMessage($response, $json),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapStats(array $data): array
    {
        $delivered = (int) ($data['deliveredParcels'] ?? 0);
        $total = (int) ($data['totalParcels'] ?? 0);
        if ($total < $delivered) {
            $total = $delivered;
        }
        $cancel = max(0, $total - $delivered);

        $report = array_merge(CourierReportFormatter::fromCounts($delivered, $cancel), [
            'customer_segment' => filled($data['customerSegment'] ?? null) ? (string) $data['customerSegment'] : null,
            'return_percentage' => $data['returnPercentage'] ?? null,
            'api_success' => true,
        ]);

        if ($delivered === 0 && $cancel === 0) {
            $report['message'] = 'No delivery history found on RedX.';
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function statsFailureMessage(Response $response, array $json): string
    {
        $message = trim((string) ($json['message'] ?? ''));
        if (
            $message !== ''
            && strlen($message) <= 160
            && ! str_contains($message, '<')
            && ! preg_match('/(?:\+?88)?01[3-9]\d{8}/', $message)
        ) {
            return 'RedX stats request failed: '.$message;
        }

        return 'RedX stats request failed (HTTP '.$response->status().').';
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
            ->retry(2, 800, fn (Throwable $exception) => $exception instanceof ConnectionException, throw: false)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://redx.com.bd',
                'Referer' => 'https://redx.com.bd/',
            ]);
    }
}
