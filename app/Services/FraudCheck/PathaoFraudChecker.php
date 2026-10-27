<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class PathaoFraudChecker
{
    private const MERCHANT_TOKEN_MINUTES = 50;

    private const LOGIN_URL = 'https://merchant.pathao.com/api/v1/login';

    private const SUCCESS_URL = 'https://merchant.pathao.com/api/v1/user/success';

    private const ISSUE_TOKEN_URL = 'https://api-hermes.pathao.com/aladdin/api/v1/issue-token';

    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
        private CourierLoginGuard $loginGuard = new CourierLoginGuard,
    ) {}

    /**
     * Merchant logins first, then the Hermes token, then a fresh Hermes token.
     * Every source asks the same merchant success endpoint. A rating-only
     * answer is kept while the next source is asked for counts.
     */
    public function check(string $phone): array
    {
        $hasMerchant = $this->hasMerchantCredentials();
        $hasHermesToken = $this->hasHermesTokenInDatabase();

        if (! $hasMerchant && ! $hasHermesToken) {
            LogHelper::saveLog('Pathao fraud check skipped', 'Pathao credentials are not configured.');

            return CourierReportFormatter::emptyReport([
                'retryable' => false,
                'message' => 'Pathao is not set up for fraud checks.',
            ]);
        }

        $ratingFallback = null;
        $rateLimited = false;
        $hermesTokenRejected = ! $hasHermesToken;

        if ($hasMerchant) {
            $outcome = $this->checkViaMerchantPortal($phone);
            $rateLimited = $outcome['rate_limited'];

            if ($outcome['report'] !== null) {
                if (! CourierReportFormatter::isRatingOnly($outcome['report'])) {
                    return $outcome['report'];
                }

                $ratingFallback = $outcome['report'];
            }
        }

        if (! $rateLimited && $hasHermesToken) {
            $outcome = $this->checkViaHermesToken($phone);
            $rateLimited = $outcome['rate_limited'];
            $hermesTokenRejected = $outcome['unauthorized'];

            if ($outcome['report'] !== null) {
                if (! CourierReportFormatter::isRatingOnly($outcome['report'])) {
                    return $outcome['report'];
                }

                $ratingFallback ??= $outcome['report'];
            }
        }

        if (! $rateLimited && $hermesTokenRejected && $this->hasHermesIssueTokenCredentials()) {
            $outcome = $this->checkViaHermesIssueToken($phone);
            $rateLimited = $outcome['rate_limited'];

            if ($outcome['report'] !== null) {
                if (! CourierReportFormatter::isRatingOnly($outcome['report'])) {
                    return $outcome['report'];
                }

                $ratingFallback ??= $outcome['report'];
            }
        }

        if ($ratingFallback !== null) {
            return $ratingFallback;
        }

        LogHelper::saveLog('Pathao fraud check error', 'No Pathao login answered this lookup.');

        return $this->unavailableReport($rateLimited);
    }

    /**
     * Pool the merchant success call when a Pathao token is already cached.
     * Login, Hermes, and rating-only answers stay on check().
     *
     * @return array{send: callable, read: callable}|null
     */
    public function poolPlan(string $phone, ?array $credentials = null): ?array
    {
        foreach ($this->credentials->candidates('pathao') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('pathao', $candidate['identifier']);
            $token = Cache::get($cacheKey);

            if (! is_string($token) || trim($token) === '') {
                continue;
            }

            return [
                'send' => function (Pool $pool, string $name) use ($phone, $token): void {
                    $pool->as($name)
                        ->timeout(25)
                        ->connectTimeout(8)
                        ->acceptJson()
                        ->withToken($token)
                        ->withOptions([
                            'curl' => [
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                            ],
                        ])
                        ->post(self::SUCCESS_URL, [
                            'phone' => $phone,
                        ]);
                },
                'read' => function (Response $response) use ($candidate, $cacheKey, $token): ?array {
                    $result = $this->interpretMerchantHttp($response);

                    if ($result['unauthorized']) {
                        $this->loginGuard->forgetToken($cacheKey, $token);

                        return null;
                    }

                    if ($result['rate_limited']) {
                        return null;
                    }

                    $report = $result['report'];

                    if ($report === null || CourierReportFormatter::isRatingOnly($report)) {
                        return null;
                    }

                    $this->credentials->markSuccess($candidate['id']);

                    return $report;
                },
            ];
        }

        return null;
    }

    public function expireSession(): bool
    {
        $cleared = false;
        $secretToken = config('pathao-courier.pathao_secret_token');

        if (filled($secretToken)) {
            $updated = DB::table(config('pathao-courier.pathao_db_table_name'))
                ->where('secret_token', $secretToken)
                ->whereNotNull('token')
                ->update([
                    'token' => null,
                    'refresh_token' => null,
                    'expires_in' => null,
                    'updated_at' => now(),
                ]);

            $cleared = $updated > 0;
        }

        if ($this->credentials->forgetSessionCaches('pathao') > 0) {
            $cleared = true;
        }

        return $cleared;
    }

    private function hasHermesTokenInDatabase(): bool
    {
        return $this->hermesDatabaseToken() !== null;
    }

    private function hermesDatabaseToken(): ?string
    {
        $secretToken = config('pathao-courier.pathao_secret_token');

        if (! filled($secretToken)) {
            return null;
        }

        $token = DB::table(config('pathao-courier.pathao_db_table_name'))
            ->where('secret_token', $secretToken)
            ->whereNotNull('token')
            ->value('token');

        return is_string($token) && trim($token) !== '' ? $token : null;
    }

    private function hasHermesIssueTokenCredentials(): bool
    {
        return filled(config('pathao-courier.pathao_client_id'))
            && filled(config('pathao-courier.pathao_client_secret'))
            && $this->hasMerchantCredentials();
    }

    private function hasMerchantCredentials(): bool
    {
        return $this->credentials->isConfigured('pathao');
    }

    /**
     * @return array{report: array<string, mixed>|null, rate_limited: bool}
     */
    private function checkViaMerchantPortal(string $phone): array
    {
        $tried = [];
        $serverError = false;
        $rateLimited = false;
        $ratingFallback = null;

        foreach ($this->credentials->candidates('pathao') as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey('pathao', $candidate['identifier']);
            $token = Cache::get($cacheKey);

            if (! is_string($token) || trim($token) === '') {
                continue;
            }

            $result = $this->askMerchantApi($phone, $token);

            if ($result['unauthorized']) {
                $this->loginGuard->forgetToken($cacheKey, $token);

                continue;
            }

            if ($result['report'] !== null) {
                $this->credentials->markSuccess($candidate['id']);

                if (! CourierReportFormatter::isRatingOnly($result['report'])) {
                    return ['report' => $result['report'], 'rate_limited' => false];
                }

                $ratingFallback ??= $result['report'];
                $tried[$candidate['identifier']] = true;

                continue;
            }

            if ($result['rate_limited']) {
                $rateLimited = true;
                $tried[$candidate['identifier']] = true;

                continue;
            }

            if ($result['server_error']) {
                $serverError = true;

                continue;
            }

            // A dropped connection says nothing about the token, so this
            // account is not logged in again for it. HTTP 200 with no customer
            // payload is a broken token: drop it and log in again below.
            if ($result['status'] === null) {
                $tried[$candidate['identifier']] = true;

                continue;
            }

            if ($result['status'] === 200) {
                $this->loginGuard->forgetToken($cacheKey, $token);

                continue;
            }

            $this->credentials->markFailure($candidate['id'], 'Merchant success API failed (HTTP '.$result['status'].')');
            $tried[$candidate['identifier']] = true;
        }

        // Pathao is failing on its side; fresh logins would not help.
        if ($serverError) {
            return ['report' => $ratingFallback, 'rate_limited' => $rateLimited];
        }

        foreach ($this->credentials->loginCandidates('pathao') as $candidate) {
            if (isset($tried[$candidate['identifier']])) {
                continue;
            }

            $token = $this->loginMerchant($candidate);

            if ($token === null) {
                continue;
            }

            $result = $this->askMerchantApi($phone, $token);

            if ($result['unauthorized']) {
                $this->loginGuard->forgetToken($this->credentials->sessionCacheKey('pathao', $candidate['identifier']), $token);
                $this->credentials->markFailure($candidate['id'], 'Merchant token rejected');

                continue;
            }

            if ($result['report'] !== null) {
                $this->credentials->markSuccess($candidate['id']);

                if (! CourierReportFormatter::isRatingOnly($result['report'])) {
                    return ['report' => $result['report'], 'rate_limited' => false];
                }

                $ratingFallback ??= $result['report'];

                continue;
            }

            if ($result['status'] !== null && ! $result['rate_limited'] && ! $result['server_error']) {
                $this->credentials->markFailure($candidate['id'], 'Merchant success API failed (HTTP '.$result['status'].')');
            }

            if ($result['rate_limited']) {
                $rateLimited = true;

                continue;
            }

            if ($result['server_error']) {
                return ['report' => $ratingFallback, 'rate_limited' => $rateLimited];
            }
        }

        return ['report' => $ratingFallback, 'rate_limited' => $rateLimited];
    }

    /**
     * @return array{report: array<string, mixed>|null, unauthorized: bool, rate_limited: bool}
     */
    private function checkViaHermesToken(string $phone): array
    {
        $token = $this->hermesDatabaseToken();

        if ($token === null) {
            return ['report' => null, 'unauthorized' => true, 'rate_limited' => false];
        }

        $result = $this->askMerchantApi($phone, $token);

        return [
            'report' => $result['report'],
            'unauthorized' => $result['unauthorized'],
            'rate_limited' => $result['rate_limited'],
        ];
    }

    /**
     * The Hermes token only works for lookups on the merchant host.
     * api-hermes.pathao.com/api/v1/user/success answers 404.
     *
     * @return array{report: array<string, mixed>|null, rate_limited: bool}
     */
    private function checkViaHermesIssueToken(string $phone): array
    {
        foreach ($this->credentials->loginCandidates('pathao') as $candidate) {
            if ($this->loginGuard->resting('pathao_hermes', $candidate)) {
                continue;
            }

            try {
                $tokenResponse = $this->client()->post(self::ISSUE_TOKEN_URL, [
                    'client_id' => config('pathao-courier.pathao_client_id'),
                    'client_secret' => config('pathao-courier.pathao_client_secret'),
                    'grant_type' => config('pathao-courier.pathao_grant_type_password'),
                    'username' => $candidate['identifier'],
                    'password' => $candidate['password'],
                ]);
            } catch (Throwable $exception) {
                LogHelper::saveLog('Pathao fraud check error', 'Hermes issue-token unreachable: '.CourierTransport::safeMessage($exception));
                $this->loginGuard->restAfter('pathao_hermes', $candidate, null);

                continue;
            }

            $tokenBody = $tokenResponse->json();
            $accessToken = is_array($tokenBody) ? trim((string) Arr::get($tokenBody, 'access_token')) : '';

            if (! $tokenResponse->successful() || $accessToken === '') {
                $this->credentials->markFailure($candidate['id'], 'Hermes issue-token failed');
                LogHelper::saveLog('Pathao fraud check error', 'Hermes issue-token failed (HTTP '.$tokenResponse->status().').');
                $this->loginGuard->restAfter('pathao_hermes', $candidate, $tokenResponse->status());

                continue;
            }

            $this->persistHermesToken($tokenBody);

            $result = $this->askMerchantApi($phone, $accessToken);

            if ($result['report'] !== null) {
                $this->credentials->markSuccess($candidate['id']);

                return ['report' => $result['report'], 'rate_limited' => false];
            }

            if ($result['rate_limited']) {
                return ['report' => null, 'rate_limited' => true];
            }
        }

        return ['report' => null, 'rate_limited' => false];
    }

    /**
     * @param  array{id: int|null, identifier: string, password: string}  $candidate
     */
    private function loginMerchant(array $candidate): ?string
    {
        $cacheKey = $this->credentials->sessionCacheKey('pathao', $candidate['identifier']);

        return $this->loginGuard->login(
            'pathao',
            $candidate,
            function () use ($cacheKey): ?string {
                $token = Cache::get($cacheKey);

                return is_string($token) && trim($token) !== '' ? $token : null;
            },
            fn () => $this->performMerchantLogin($candidate, $cacheKey),
        );
    }

    /**
     * @param  array{id: int|null, identifier: string, password: string}  $candidate
     */
    private function performMerchantLogin(array $candidate, string $cacheKey): ?string
    {
        try {
            $response = $this->client()->post(self::LOGIN_URL, [
                'username' => $candidate['identifier'],
                'password' => $candidate['password'],
            ]);
        } catch (Throwable $exception) {
            LogHelper::saveLog('Pathao fraud check error', 'Merchant login unreachable: '.CourierTransport::safeMessage($exception));
            $this->loginGuard->restAfter('pathao', $candidate, null);

            return null;
        }

        $token = trim((string) $response->json('access_token'));

        if (! $response->successful() || $token === '') {
            $this->credentials->markFailure($candidate['id'], 'Merchant login failed');
            LogHelper::saveLog('Pathao fraud check error', 'Merchant login failed for '.$candidate['identifier'].' (HTTP '.$response->status().').');
            $this->loginGuard->restAfter('pathao', $candidate, $response->status());

            return null;
        }

        Cache::put($cacheKey, $token, now()->addMinutes(self::MERCHANT_TOKEN_MINUTES));
        $this->credentials->markUsed($candidate['id']);

        return $token;
    }

    /**
     * @return array{report: array<string, mixed>|null, unauthorized: bool, rate_limited: bool, server_error: bool, status: int|null}
     */
    private function askMerchantApi(string $phone, string $token): array
    {
        try {
            $response = $this->client()
                ->withToken($token)
                ->post(self::SUCCESS_URL, [
                    'phone' => $phone,
                ]);
        } catch (Throwable $exception) {
            LogHelper::saveLog('Pathao fraud check error', 'Merchant success API unreachable: '.CourierTransport::safeMessage($exception));

            return [
                'report' => null,
                'unauthorized' => false,
                'rate_limited' => false,
                'server_error' => false,
                'status' => null,
            ];
        }

        return $this->interpretMerchantHttp($response);
    }

    /**
     * A report is returned only when Pathao answered for this number:
     * delivery counts, a confirmed empty history, or a rating.
     *
     * @return array{report: array<string, mixed>|null, unauthorized: bool, rate_limited: bool, server_error: bool, status: int|null}
     */
    private function interpretMerchantHttp(Response $response): array
    {
        $status = $response->status();
        $outcome = [
            'report' => null,
            'unauthorized' => $status === 401,
            'rate_limited' => $status === 429,
            'server_error' => $status >= 500,
            'status' => $status,
        ];

        if (! $response->successful()) {
            if ($status !== 401) {
                LogHelper::saveLog('Pathao fraud check error', 'Merchant success API failed with status '.$status);
            }

            return $outcome;
        }

        $report = CourierReportFormatter::fromPathao([
            'data' => $response->json('data', []),
        ]);

        if (
            CourierReportFormatter::hasDeliveryCounts($report)
            || ! empty($report['api_success'])
            || CourierReportFormatter::isRatingOnly($report)
        ) {
            $outcome['report'] = $report;
        } else {
            LogHelper::saveLog('Pathao fraud check error', 'Merchant success API answered without customer data.');
        }

        return $outcome;
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableReport(bool $rateLimited): array
    {
        return CourierReportFormatter::emptyReport(array_filter([
            'unavailable' => true,
            'rate_limited' => $rateLimited ?: null,
            'message' => $rateLimited
                ? 'Pathao asked us to slow down. This number is checked again shortly.'
                : 'Pathao did not answer this time. This number is checked again automatically.',
        ], fn ($value) => $value !== null));
    }

    private function client(): PendingRequest
    {
        return Http::timeout(25)
            ->connectTimeout(8)
            ->withOptions([
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->retry(3, 500, fn (Throwable $exception) => CourierTransport::shouldRetry($exception), throw: false)
            ->acceptJson();
    }

    private function persistHermesToken(array $tokenBody): void
    {
        $secretToken = config('pathao-courier.pathao_secret_token');

        if (!filled($secretToken)) {
            return;
        }

        $payload = [
            'token' => Arr::get($tokenBody, 'access_token'),
            'refresh_token' => Arr::get($tokenBody, 'refresh_token'),
            'expires_in' => time() + (int) Arr::get($tokenBody, 'expires_in', 0),
            'updated_at' => now(),
        ];

        $table = config('pathao-courier.pathao_db_table_name');
        $existing = DB::table($table)->where('secret_token', $secretToken)->first();

        if ($existing) {
            DB::table($table)->where('secret_token', $secretToken)->update($payload);
            return;
        }

        DB::table($table)->insert(array_merge($payload, [
            'secret_token' => $secretToken,
            'created_at' => now(),
        ]));
    }
}
