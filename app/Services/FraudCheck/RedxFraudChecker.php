<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
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

    private const SAMPLE_STREAK_KEY = 'fraud_check_redx_sample_streak';

    private const SAMPLE_SKIP_KEY = 'fraud_check_redx_sample_skip';

    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
        private CourierLoginGuard $loginGuard = new CourierLoginGuard,
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

            return CourierReportFormatter::emptyReport([
                'retryable' => false,
                'message' => 'RedX is not set up for fraud checks.',
            ]);
        }

        if ($this->skippingSample()) {
            return $this->sampleReport();
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
                $this->loginGuard->forgetToken($auth['cache_key'], $auth['token']);
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
                $this->noteSample();

                return $this->sampleReport();
            }

            if (! $stats['ok']) {
                LogHelper::saveLog('RedX fraud check error', 'Stats failed with status '.$response->status());

                return CourierReportFormatter::emptyReport([
                    'unavailable' => true,
                    'message' => $stats['message'] ?? ('RedX stats request failed (HTTP '.$response->status().').'),
                ]);
            }

            $this->credentials->markSuccess($auth['credential_id']);
            $this->noteRealAnswer();

            if (! empty($stats['empty'])) {
                return CourierReportFormatter::emptyReport([
                    'api_success' => true,
                    'message' => 'No delivery history found on RedX.',
                ]);
            }

            return $this->mapStats($stats['data']);
        } catch (Throwable $e) {
            LogHelper::saveLog('RedX fraud check error', CourierTransport::safeMessage($e));

            return CourierReportFormatter::emptyReport([
                'unavailable' => true,
                'message' => 'RedX did not answer this time. This number is checked again automatically.',
            ]);
        }
    }

    /**
     * Pool the stats call when a RedX token is already cached.
     * Login and the skipped-sample answer stay on check().
     *
     * @return array{send: callable, read: callable}|null
     */
    public function poolPlan(string $phone, ?array $credentials = null): ?array
    {
        if (! $this->isConfigured() || $this->skippingSample()) {
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
                if (in_array($response->status(), [401, 403], true)) {
                    $this->loginGuard->forgetToken($auth['cache_key'], $auth['token']);

                    return null;
                }

                $stats = $this->readStats($response);
                if (! empty($stats['placeholder'])) {
                    $this->noteSample();

                    return $this->sampleReport();
                }

                if (! $stats['ok']) {
                    LogHelper::saveLog('RedX fraud check error', 'Stats failed with status '.$response->status());

                    return null;
                }

                $this->credentials->markSuccess($auth['credential_id']);
                $this->noteRealAnswer();

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

    private function sampleReport(): array
    {
        return CourierReportFormatter::emptyReport([
            'unavailable' => true,
            'retryable' => false,
            'static_sample' => true,
            'message' => RedxStaticSample::MESSAGE,
        ]);
    }

    private function skippingSample(): bool
    {
        try {
            return Cache::has(self::SAMPLE_SKIP_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * RedX sends the same sample for every number while its history is off.
     * After a few in a row it is not asked for a while.
     */
    private function noteSample(): void
    {
        $settings = (array) config('fraud_check.redx_sample', []);
        $minutes = (int) ($settings['skip_minutes'] ?? 30);

        if ($minutes <= 0) {
            return;
        }

        $after = max(1, (int) ($settings['after_samples'] ?? 3));

        try {
            Cache::add(self::SAMPLE_STREAK_KEY, 0, now()->addHour());

            if ((int) Cache::increment(self::SAMPLE_STREAK_KEY) < $after) {
                return;
            }

            Cache::forget(self::SAMPLE_STREAK_KEY);
            Cache::put(self::SAMPLE_SKIP_KEY, true, now()->addMinutes($minutes));
            LogHelper::saveLog(
                'Courier pace',
                'RedX sent its fixed sample '.$after.' times in a row, so RedX is not asked for '.$minutes.' minutes.',
                'courier-pace.log',
            );
        } catch (Throwable) {
            // Without the cache every number still asks RedX, as before.
        }
    }

    private function noteRealAnswer(): void
    {
        try {
            Cache::forget(self::SAMPLE_STREAK_KEY);
        } catch (Throwable) {
            //
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
        // A rejected token was already dropped, so a token found here is one
        // another worker just saved.
        foreach ($this->credentials->loginCandidates('redx') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('redx', $candidate['identifier']);

            $token = $this->loginGuard->login(
                'redx',
                $candidate,
                function () use ($cacheKey): ?string {
                    $cached = Cache::get($cacheKey);

                    return is_string($cached) && $cached !== '' ? $cached : null;
                },
                fn () => $this->loginWithPassword($candidate, $cacheKey),
            );

            if ($token === null) {
                continue;
            }

            return [
                'token' => (string) $token,
                'cache_key' => $cacheKey,
                'credential_id' => $candidate['id'],
            ];
        }

        return null;
    }

    /**
     * @param  array{id: int|null, identifier: string, password: string}  $candidate
     */
    private function loginWithPassword(array $candidate, string $cacheKey): ?string
    {
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
            $this->loginGuard->restAfter('redx', $candidate, $response->status());

            return null;
        }

        Cache::put($cacheKey, $token, now()->addMinutes(50));
        $this->credentials->markUsed($candidate['id']);

        return (string) $token;
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
            ->retry(2, 800, fn (Throwable $exception) => CourierTransport::shouldRetry($exception), throw: false)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://redx.com.bd',
                'Referer' => 'https://redx.com.bd/',
            ]);
    }
}
