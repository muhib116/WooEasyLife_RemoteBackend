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

    private const COURIER = 'pathao';

    private const HERMES_LANE = 'hermes';

    public function __construct(
        private FraudPartnerCredentialResolver $credentials,
        private CourierLoginGuard $loginGuard = new CourierLoginGuard,
        private ?CourierPace $pace = null,
    ) {}

    /**
     * Merchant logins first, then the Hermes token, then a fresh Hermes token.
     * Every source asks the same merchant success endpoint within the pace of
     * its account. A rating-only answer is kept while the next source is
     * asked for counts. When no account has room, Pathao is not asked and the
     * report says when it can be.
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
        $hermesTokenRejected = ! $hasHermesToken;
        $state = ['rate_limited' => false, 'retry_after' => 0, 'asked' => false, 'wait' => 0];

        if ($hasMerchant) {
            $outcome = $this->checkViaMerchantPortal($phone);
            $state = $this->mergeOutcome($state, $outcome);

            if ($outcome['report'] !== null) {
                if (! CourierReportFormatter::isRatingOnly($outcome['report'])) {
                    return $outcome['report'];
                }

                $ratingFallback = $outcome['report'];
            }
        }

        if (! $state['rate_limited'] && $hasHermesToken) {
            $outcome = $this->checkViaHermesToken($phone);
            $state = $this->mergeOutcome($state, $outcome);
            $hermesTokenRejected = $outcome['unauthorized'];

            if ($outcome['report'] !== null) {
                if (! CourierReportFormatter::isRatingOnly($outcome['report'])) {
                    return $outcome['report'];
                }

                $ratingFallback ??= $outcome['report'];
            }
        }

        if (! $state['rate_limited'] && $hermesTokenRejected && $this->hasHermesIssueTokenCredentials()) {
            $outcome = $this->checkViaHermesIssueToken($phone);
            $state = $this->mergeOutcome($state, $outcome);

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

        if ($state['rate_limited']) {
            return $this->rateLimitedReport($state['retry_after']);
        }

        if (! $state['asked'] && $state['wait'] > 0) {
            return $this->busyReport($state['wait']);
        }

        LogHelper::saveLog('Pathao fraud check error', 'No Pathao login answered this lookup.');

        return $this->unavailableReport();
    }

    /**
     * Pool the merchant success call when a Pathao token is already cached
     * and its account has room now. Login, Hermes, another account after a
     * rate limit, and rating-only answers stay on check().
     *
     * @return array{send: callable, read: callable}|null
     */
    public function poolPlan(string $phone, ?array $credentials = null): ?array
    {
        foreach ($this->credentials->candidates(self::COURIER) as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey(self::COURIER, $candidate['identifier']);
            $token = Cache::get($cacheKey);

            if (! is_string($token) || trim($token) === '') {
                continue;
            }

            $lane = $this->laneFor($candidate['identifier']);

            if ($this->pace()->waitSeconds(self::COURIER, $lane) > 0) {
                continue;
            }

            return [
                'send' => function (Pool $pool, string $name) use ($phone, $token, $lane): void {
                    if ($this->pace()->claim(self::COURIER, $lane, 0) > 0) {
                        return;
                    }

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
                'read' => function (Response $response) use ($candidate, $cacheKey, $token, $lane): ?array {
                    $result = $this->interpretMerchantHttp($response);

                    if ($result['unauthorized']) {
                        $this->loginGuard->forgetToken($cacheKey, $token);

                        return null;
                    }

                    if ($result['rate_limited']) {
                        $rest = $this->pace()->rateLimited(self::COURIER, $lane, $result['retry_after']);

                        // Another account may still have room; check() asks it.
                        return $this->pace()->shared(self::COURIER) ? $this->rateLimitedReport($rest) : null;
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

    /**
     * Seconds until a lookup of the current kind could be sent on any Pathao
     * account, 0 when one can go now.
     */
    public function nextSlotSeconds(): int
    {
        $lanes = $this->lanes();

        if ($lanes === []) {
            return 0;
        }

        return min(array_map(fn (string $lane): int => $this->pace()->waitSeconds(self::COURIER, $lane), $lanes));
    }

    /**
     * Bulk lookups per minute over every lane a lookup can use (merchant
     * accounts and the Hermes token), and how long every lane still rests.
     *
     * @return array{per_minute: float, resting_seconds: int, shared: bool}
     */
    public function paceStatus(): array
    {
        $lanes = $this->lanes();

        return $this->pace()->status(self::COURIER, $lanes === [] ? [self::HERMES_LANE] : $lanes);
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

        if ($this->credentials->forgetSessionCaches(self::COURIER) > 0) {
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
        return $this->credentials->isConfigured(self::COURIER);
    }

    /**
     * @return array{report: array<string, mixed>|null, rate_limited: bool, retry_after: int, asked: bool, wait: int}
     */
    private function checkViaMerchantPortal(string $phone): array
    {
        $tried = [];
        $serverError = false;
        $rateLimited = false;
        $retryAfter = 0;
        $asked = false;
        $wait = 0;
        $ratingFallback = null;

        foreach ($this->credentials->candidates(self::COURIER) as $candidate) {
            $cacheKey = $this->credentials->sessionCacheKey(self::COURIER, $candidate['identifier']);
            $token = Cache::get($cacheKey);

            if (! is_string($token) || trim($token) === '') {
                continue;
            }

            $result = $this->askMerchantApi($phone, $token, $this->laneFor($candidate['identifier']));

            // No room on this account now. It is not logged in again either.
            if ($result['deferred']) {
                $wait = $this->sooner($wait, $result['wait']);
                $tried[$candidate['identifier']] = true;

                continue;
            }

            $asked = true;

            if ($result['unauthorized']) {
                $this->loginGuard->forgetToken($cacheKey, $token);

                continue;
            }

            if ($result['report'] !== null) {
                $this->credentials->markSuccess($candidate['id']);

                if (! CourierReportFormatter::isRatingOnly($result['report'])) {
                    return $this->merchantOutcome($result['report'], false, 0, true, 0);
                }

                $ratingFallback ??= $result['report'];
                $tried[$candidate['identifier']] = true;

                continue;
            }

            // This account rests now. Another one is asked unless the limit
            // covers every account.
            if ($result['rate_limited']) {
                $rateLimited = true;
                $retryAfter = max($retryAfter, $result['retry_after']);
                $tried[$candidate['identifier']] = true;

                if ($this->pace()->shared(self::COURIER)) {
                    break;
                }

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

        // A rate limit or an outage is Pathao's, so another login would not help.
        if ($serverError || ($rateLimited && $this->pace()->shared(self::COURIER))) {
            return $this->merchantOutcome($ratingFallback, $rateLimited, $retryAfter, $asked, $wait);
        }

        foreach ($this->credentials->loginCandidates(self::COURIER) as $candidate) {
            if (isset($tried[$candidate['identifier']])) {
                continue;
            }

            $lane = $this->laneFor($candidate['identifier']);
            $laneWait = $this->pace()->waitSeconds(self::COURIER, $lane);

            // Logging in is pointless while this account cannot be asked.
            if ($laneWait > $this->pace()->allowance(self::COURIER)) {
                $wait = $this->sooner($wait, $laneWait);

                continue;
            }

            $token = $this->loginMerchant($candidate);

            if ($token === null) {
                continue;
            }

            $result = $this->askMerchantApi($phone, $token, $lane);

            if ($result['deferred']) {
                $wait = $this->sooner($wait, $result['wait']);

                continue;
            }

            $asked = true;

            if ($result['unauthorized']) {
                $this->loginGuard->forgetToken($this->credentials->sessionCacheKey(self::COURIER, $candidate['identifier']), $token);
                $this->credentials->markFailure($candidate['id'], 'Merchant token rejected');

                continue;
            }

            if ($result['report'] !== null) {
                $this->credentials->markSuccess($candidate['id']);

                if (! CourierReportFormatter::isRatingOnly($result['report'])) {
                    return $this->merchantOutcome($result['report'], false, 0, true, 0);
                }

                $ratingFallback ??= $result['report'];

                continue;
            }

            if ($result['status'] !== null && ! $result['rate_limited'] && ! $result['server_error']) {
                $this->credentials->markFailure($candidate['id'], 'Merchant success API failed (HTTP '.$result['status'].')');
            }

            if ($result['rate_limited']) {
                $rateLimited = true;
                $retryAfter = max($retryAfter, $result['retry_after']);

                if ($this->pace()->shared(self::COURIER)) {
                    break;
                }

                continue;
            }

            if ($result['server_error']) {
                break;
            }
        }

        return $this->merchantOutcome($ratingFallback, $rateLimited, $retryAfter, $asked, $wait);
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array{report: array<string, mixed>|null, rate_limited: bool, retry_after: int, asked: bool, wait: int}
     */
    private function merchantOutcome(?array $report, bool $rateLimited, int $retryAfter, bool $asked, int $wait): array
    {
        return [
            'report' => $report,
            'rate_limited' => $rateLimited,
            'retry_after' => $retryAfter,
            'asked' => $asked,
            'wait' => $wait,
        ];
    }

    /**
     * @return array{report: array<string, mixed>|null, unauthorized: bool, rate_limited: bool, retry_after: int, asked: bool, wait: int}
     */
    private function checkViaHermesToken(string $phone): array
    {
        $token = $this->hermesDatabaseToken();

        if ($token === null) {
            return [
                'report' => null,
                'unauthorized' => true,
                'rate_limited' => false,
                'retry_after' => 0,
                'asked' => false,
                'wait' => 0,
            ];
        }

        $result = $this->askMerchantApi($phone, $token, self::HERMES_LANE);

        return [
            'report' => $result['report'],
            'unauthorized' => $result['unauthorized'],
            'rate_limited' => $result['rate_limited'],
            'retry_after' => $result['retry_after'],
            'asked' => ! $result['deferred'],
            'wait' => $result['wait'],
        ];
    }

    /**
     * The Hermes token only works for lookups on the merchant host.
     * api-hermes.pathao.com/api/v1/user/success answers 404.
     *
     * @return array{report: array<string, mixed>|null, rate_limited: bool, retry_after: int, asked: bool, wait: int}
     */
    private function checkViaHermesIssueToken(string $phone): array
    {
        $laneWait = $this->pace()->waitSeconds(self::COURIER, self::HERMES_LANE);

        if ($laneWait > $this->pace()->allowance(self::COURIER)) {
            return ['report' => null, 'rate_limited' => false, 'retry_after' => 0, 'asked' => false, 'wait' => $laneWait];
        }

        $asked = false;

        foreach ($this->credentials->loginCandidates(self::COURIER) as $candidate) {
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

            $result = $this->askMerchantApi($phone, $accessToken, self::HERMES_LANE);

            if ($result['deferred']) {
                return ['report' => null, 'rate_limited' => false, 'retry_after' => 0, 'asked' => $asked, 'wait' => $result['wait']];
            }

            $asked = true;

            if ($result['report'] !== null) {
                $this->credentials->markSuccess($candidate['id']);

                return ['report' => $result['report'], 'rate_limited' => false, 'retry_after' => 0, 'asked' => true, 'wait' => 0];
            }

            if ($result['rate_limited']) {
                return ['report' => null, 'rate_limited' => true, 'retry_after' => $result['retry_after'], 'asked' => true, 'wait' => 0];
            }
        }

        return ['report' => null, 'rate_limited' => false, 'retry_after' => 0, 'asked' => $asked, 'wait' => 0];
    }

    /**
     * @param  array{id: int|null, identifier: string, password: string}  $candidate
     */
    private function loginMerchant(array $candidate): ?string
    {
        $cacheKey = $this->credentials->sessionCacheKey(self::COURIER, $candidate['identifier']);

        return $this->loginGuard->login(
            self::COURIER,
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
            $this->loginGuard->restAfter(self::COURIER, $candidate, null);

            return null;
        }

        $token = trim((string) $response->json('access_token'));

        if (! $response->successful() || $token === '') {
            $this->credentials->markFailure($candidate['id'], 'Merchant login failed');
            LogHelper::saveLog('Pathao fraud check error', 'Merchant login failed for '.$candidate['identifier'].' (HTTP '.$response->status().').');
            $this->loginGuard->restAfter(self::COURIER, $candidate, $response->status());

            return null;
        }

        Cache::put($cacheKey, $token, now()->addMinutes(self::MERCHANT_TOKEN_MINUTES));
        $this->credentials->markUsed($candidate['id']);

        return $token;
    }

    /**
     * Ask the success endpoint once, within the pace of $lane.
     *
     * @return array{report: array<string, mixed>|null, unauthorized: bool, rate_limited: bool, server_error: bool, status: int|null, retry_after: int, deferred: bool, wait: int}
     */
    private function askMerchantApi(string $phone, string $token, string $lane): array
    {
        $outcome = [
            'report' => null,
            'unauthorized' => false,
            'rate_limited' => false,
            'server_error' => false,
            'status' => null,
            'retry_after' => 0,
            'deferred' => false,
            'wait' => 0,
        ];

        $wait = $this->pace()->claim(self::COURIER, $lane);

        if ($wait > 0) {
            return ['deferred' => true, 'wait' => $wait] + $outcome;
        }

        try {
            $response = $this->client()
                ->withToken($token)
                ->post(self::SUCCESS_URL, [
                    'phone' => $phone,
                ]);
        } catch (Throwable $exception) {
            LogHelper::saveLog('Pathao fraud check error', 'Merchant success API unreachable: '.CourierTransport::safeMessage($exception));

            return $outcome;
        }

        $result = $this->interpretMerchantHttp($response);

        if ($result['rate_limited']) {
            $result['retry_after'] = $this->pace()->rateLimited(self::COURIER, $lane, $result['retry_after']);
        }

        return $result + $outcome;
    }

    /**
     * A report is returned only when Pathao answered for this number:
     * delivery counts, a confirmed empty history, or a rating.
     *
     * @return array{report: array<string, mixed>|null, unauthorized: bool, rate_limited: bool, server_error: bool, status: int|null, retry_after: int}
     */
    private function interpretMerchantHttp(Response $response): array
    {
        $status = $response->status();
        $retryAfter = 0;

        if ($status === 429) {
            $header = trim((string) $response->header('Retry-After'));
            $retryAfter = is_numeric($header) ? (int) $header : 0;
        }

        $outcome = [
            'report' => null,
            'unauthorized' => $status === 401,
            'rate_limited' => $status === 429,
            'server_error' => $status >= 500,
            'status' => $status,
            'retry_after' => $retryAfter,
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
     * @param  array{rate_limited: bool, retry_after: int, asked: bool, wait: int}  $state
     * @param  array<string, mixed>  $outcome
     * @return array{rate_limited: bool, retry_after: int, asked: bool, wait: int}
     */
    private function mergeOutcome(array $state, array $outcome): array
    {
        return [
            'rate_limited' => $state['rate_limited'] || ! empty($outcome['rate_limited']),
            'retry_after' => max($state['retry_after'], (int) ($outcome['retry_after'] ?? 0)),
            'asked' => $state['asked'] || ! empty($outcome['asked']),
            'wait' => $this->sooner($state['wait'], (int) ($outcome['wait'] ?? 0)),
        ];
    }

    private function sooner(int $current, int $next): int
    {
        if ($next <= 0) {
            return $current;
        }

        return $current > 0 ? min($current, $next) : $next;
    }

    /**
     * Merchant accounts and the Hermes token, each with its own pace.
     *
     * @return list<string>
     */
    private function lanes(): array
    {
        $lanes = [];

        if ($this->hasMerchantCredentials()) {
            foreach ($this->credentials->candidates(self::COURIER) as $candidate) {
                $lanes[] = $this->laneFor($candidate['identifier']);
            }
        }

        if ($this->hasHermesTokenInDatabase() || $this->hasHermesIssueTokenCredentials()) {
            $lanes[] = self::HERMES_LANE;
        }

        return array_values(array_unique($lanes));
    }

    private function laneFor(string $identifier): string
    {
        return 'acct-'.substr(md5(strtolower(trim($identifier))), 0, 12);
    }

    private function pace(): CourierPace
    {
        return $this->pace ??= app(CourierPace::class);
    }

    private function rateLimitedReport(int $seconds): array
    {
        $seconds = max(1, $seconds);

        return CourierReportFormatter::emptyReport([
            'unavailable' => true,
            'rate_limited' => true,
            'retry_after' => $seconds,
            'message' => 'Pathao asked us to slow down. Ask again in about '.$this->duration($seconds).'.',
        ]);
    }

    private function busyReport(int $seconds): array
    {
        $seconds = max(1, $seconds);

        return CourierReportFormatter::emptyReport([
            'unavailable' => true,
            'rate_limited' => true,
            'deferred' => true,
            'retry_after' => $seconds,
            'message' => 'Pathao is busy, so this number was not asked yet. Ask again in about '.$this->duration($seconds).'.',
        ]);
    }

    private function unavailableReport(): array
    {
        return CourierReportFormatter::emptyReport([
            'unavailable' => true,
            'message' => 'Pathao did not answer this time. This number is checked again automatically.',
        ]);
    }

    private function duration(int $seconds): string
    {
        if ($seconds < 90) {
            return $seconds.' seconds';
        }

        return (int) ceil($seconds / 60).' minutes';
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
