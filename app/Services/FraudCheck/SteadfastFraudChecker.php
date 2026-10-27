<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use App\Services\Courier\SteadfastPortalSessionClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteadfastFraudChecker
{
    private const HOSTS = ['www.steadfast.com.bd', 'steadfast.com.bd'];

    /**
     * Steadfast renamed the portal session cookie. The new name is also set
     * on the guest login page, so its presence alone is not proof of login.
     *
     * @var list<string>
     */
    private const SESSION_COOKIES = ['sfc_user_session', 'steadfast_courier_session'];

    private const SCORE_URL = 'https://portal.packzy.com/api/v1/fraud_check/score/';

    /** One number stops walking keys after this long and keeps what it has. */
    private const SCORE_WALK_SECONDS = 20;

    /** Keys in a row that get no answer before the API counts as down, not the keys. */
    private const OUTAGE_STREAK = 2;

    private const OUTAGE_PAUSE_SECONDS = 60;

    /** A store's own key that Steadfast rejects is not asked again for this long. */
    private const STORE_KEY_REJECTED_REST_SECONDS = 1800;

    /** A login can take two slow requests, so the lock outlives both. */
    private const LOGIN_LOCK_SECONDS = 120;

    private const LOGIN_WAIT_SECONDS = 25;

    /** Rests after a failed login, so one bad password does not log in on every number. */
    private const LOGIN_REJECTED_REST_SECONDS = 900;

    private const LOGIN_FAILED_REST_SECONDS = 60;

    /** Lookups in a row that fail on a session before it is dropped and logged in again. */
    private const SESSION_MISS_LIMIT = 3;

    /** @var array{username: string, password: string}|null */
    private ?array $credentials = null;

    private bool $loginPosted = false;

    public function __construct(
        private FraudPartnerCredentialResolver $partnerCredentials,
        private SteadfastFraudApiCredentialStore $apiCredentials,
    ) {}

    public function isConfigured(): bool
    {
        return $this->partnerCredentials->isConfigured('steadfast');
    }

    /**
     * @param  array{username?: string, password?: string}|null  $credentials
     */
    public function check(string $phone, ?array $credentials = null): array
    {
        $this->credentials = $credentials;

        try {
            return $this->performCheck($phone);
        } finally {
            $this->credentials = null;
        }
    }

    /**
     * Pool the official score request when a key pair is already known.
     * A response that is not usable falls back to check(), which still tries
     * the remaining keys and the portal session.
     *
     * @param  array{username?: string, password?: string, api_key?: string, secret_key?: string}|null  $credentials
     * @return array{send: callable, read: callable}|null
     */
    public function poolPlan(string $phone, ?array $credentials = null): ?array
    {
        $this->credentials = $credentials;

        try {
            if ($this->scoreApiPaused()) {
                return null;
            }

            $keys = null;

            foreach ($this->scoreApiCandidates() as $candidate) {
                if (empty($candidate['recheck']) && ($candidate['shape'] ?? null) !== 'ratio') {
                    $keys = $candidate;

                    break;
                }
            }

            $digits = preg_replace('/\D/', '', $phone) ?? '';

            if (! is_array($keys) || $digits === '') {
                return null;
            }

            return [
                'send' => function (Pool $pool, string $name) use ($keys, $digits): void {
                    $pool->as($name)
                        ->timeout(12)
                        ->connectTimeout(5)
                        ->withOptions([
                            'curl' => [
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                            ],
                        ])
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                            'Accept' => 'application/json',
                            'Accept-Language' => 'en-US,en;q=0.9',
                            'Api-Key' => $keys['api_key'],
                            'Secret-Key' => $keys['secret_key'],
                        ])
                        ->get(self::SCORE_URL.$digits);
                },
                'read' => function (Response $response) use ($keys): ?array {
                    $report = $this->scoreReportFromResponse($response);
                    $tracked = ! empty($keys['hub']);
                    $outcomes = [];

                    $this->noteKeyOutcome($report, $keys, $outcomes);
                    $report = $this->withoutKeyNotes($report);

                    if (! $this->isUsableReport($report)) {
                        $this->flushPoolOutcomes($tracked, $outcomes);

                        return null;
                    }

                    $hidden = $this->hasHiddenCounts($report);
                    $this->apiCredentials->noteShape($keys['api_key'], ! $hidden);
                    $this->flushPoolOutcomes($tracked, $outcomes, $tracked ? $keys['api_key'] : null);

                    return $hidden ? null : $report;
                },
            ];
        } finally {
            $this->credentials = null;
        }
    }

    /**
     * The official API is asked once, every usable key in one walk. Only
     * when no key gives parcel counts does the portal session run, and a
     * portal login is the last option. Keys that are all resting do not stop
     * the portal: a rate limit on the API is not a rate limit on the portal.
     */
    private function performCheck(string $phone): array
    {
        $apiReport = $this->checkViaScoreApi($phone);
        $hiddenCountsFallback = null;

        if (is_array($apiReport)) {
            if ($this->isUsableReport($apiReport) && ! $this->hasHiddenCounts($apiReport)) {
                return $apiReport;
            }

            if ($this->hasHiddenCounts($apiReport)) {
                $hiddenCountsFallback = $apiReport;
            }
        }

        $report = $this->runCredentialCheck($phone);

        if ($this->isUsableReport($report) && (! $this->hasHiddenCounts($report) || $hiddenCountsFallback === null)) {
            return $report;
        }

        if ($this->credentials !== null
            && $this->hasPortalCredentials()
            && $this->platformPortalDiffersFromMerchant()
            && ! $this->hasStoredSession()) {
            LogHelper::saveLog(
                'Steadfast fraud check fallback',
                'Merchant portal credentials returned no data; retrying with platform credentials.'
            );

            $merchantCredentials = $this->credentials;
            $this->credentials = null;

            try {
                $fallbackReport = $this->runCredentialCheck($phone);

                if ($this->isUsableReport($fallbackReport) && (! $this->hasHiddenCounts($fallbackReport) || $hiddenCountsFallback === null)) {
                    return $fallbackReport;
                }

                $report = $fallbackReport;
            } finally {
                $this->credentials = $merchantCredentials;
            }
        }

        if ($hiddenCountsFallback !== null) {
            return $hiddenCountsFallback;
        }

        if (is_array($apiReport) && ! empty($apiReport['credential_error'])) {
            return $apiReport;
        }

        if (is_array($apiReport) && ! empty($apiReport['rate_limited'])) {
            return $apiReport;
        }

        return $report;
    }

    private function runCredentialCheck(string $phone): array
    {
        // Explicit merchant portal credentials (plugin/API token path): single account.
        if ($this->credentials !== null && $this->hasPortalCredentials()) {
            if ($this->hasCredentials()) {
                $report = $this->checkViaCachedSession($phone);

                if ($this->isUsableReport($report)) {
                    return $report;
                }

                $report = $this->checkViaLogin($phone);

                if ($this->isUsableReport($report)) {
                    return $report;
                }

                // Only a rejected password is a credential error. A timeout,
                // a busy login, or a rest is asked again later.
                if (empty($report['credential_error'])) {
                    return $report;
                }

                return CourierReportFormatter::emptyReport([
                    'frauds' => [],
                    'credential_error' => true,
                ]);
            }
        } elseif ($this->partnerCredentials->isConfigured('steadfast')) {
            $previousCredentials = $this->credentials;

            // Platform multi-cred: reuse any warm session first.
            foreach ($this->partnerCredentials->candidates('steadfast') as $candidate) {
                $this->credentials = [
                    'username' => $candidate['identifier'],
                    'password' => $candidate['password'],
                ];

                try {
                    $report = $this->checkViaCachedSession($phone);
                    if ($this->isUsableReport($report)) {
                        return $report;
                    }
                } finally {
                    $this->credentials = $previousCredentials;
                }
            }

            // Session expired / cold: pick a random account, then failover.
            $credentialFailure = null;
            $waitingFailure = null;

            foreach ($this->partnerCredentials->loginCandidates('steadfast') as $candidate) {
                $this->credentials = [
                    'username' => $candidate['identifier'],
                    'password' => $candidate['password'],
                ];

                try {
                    $report = $this->checkViaLogin($phone);
                    if ($this->isUsableReport($report)) {
                        $this->partnerCredentials->markSuccess($candidate['id']);

                        return $report;
                    }

                    if ($this->hasStoredSession()) {
                        return $report;
                    }

                    // A rest or a lockout is not this account's fault, and it
                    // says why there is no answer better than an empty report.
                    if (! empty($report['login_skipped']) || ! empty($report['rate_limited'])) {
                        $waitingFailure ??= $report;

                        if (! empty($report['login_skipped'])) {
                            continue;
                        }
                    }

                    if (! empty($report['credential_error'])) {
                        $credentialFailure = $report;
                    }

                    $this->partnerCredentials->markFailure($candidate['id'], 'Steadfast login/check failed');
                } finally {
                    $this->credentials = $previousCredentials;
                }
            }
        }

        if (file_exists($this->legacyCurlPath())) {
            $legacyReport = $this->checkViaLegacyCurl($phone);

            if ($this->isUsableReport($legacyReport)) {
                return $legacyReport;
            }

            if (isset($credentialFailure)) {
                return $credentialFailure;
            }

            return $waitingFailure ?? $legacyReport;
        }

        if (isset($credentialFailure)) {
            return $credentialFailure;
        }

        if (isset($waitingFailure)) {
            return $waitingFailure;
        }

        if (! $this->partnerCredentials->isConfigured('steadfast')) {
            LogHelper::saveLog('Steadfast fraud check skipped', 'No Steadfast credentials configured in admin or .env.');
        }

        return CourierReportFormatter::emptyReport(['frauds' => []]);
    }

    private function isUsableReport(array $report): bool
    {
        return $this->hasDeliveryData($report) || ! empty($report['api_success']);
    }

    /**
     * When Steadfast knows parcels exist (delivery_ratio / cancellation_ratio is numeric)
     * but hides delivered_count and cancelled_count for an inactive API key, keep looking
     * for an active key or portal session that returns exact counts.
     *
     * @param  array<string, mixed>  $report
     */
    private function hasHiddenCounts(array $report): bool
    {
        // Fraud notes are not delivery counts. A ratio-only answer with fraud
        // categories must still fall through to a key or portal that has counts.
        return ! empty($report['counts_hidden'])
            && empty($report['history_unknown'])
            && (int) ($report['total_order'] ?? 0) === 0
            && (int) ($report['confirmed'] ?? 0) === 0
            && (int) ($report['cancel'] ?? 0) === 0;
    }

    private function hasPortalCredentials(): bool
    {
        return filled($this->credentials['username'] ?? null)
            && filled($this->credentials['password'] ?? null);
    }

    /**
     * Hub keys are already in the one key walk, so only a different portal
     * account is left to try.
     */
    private function platformPortalDiffersFromMerchant(): bool
    {
        if ($this->credentials === null || ! $this->isConfigured()) {
            return false;
        }

        $platform = $this->partnerCredentials->primary('steadfast');
        if ($platform === null) {
            return false;
        }

        return $platform['identifier'] !== ($this->credentials['username'] ?? null)
            || $platform['password'] !== ($this->credentials['password'] ?? null);
    }

    private function hasCredentials(): bool
    {
        return filled($this->resolveUsername()) && filled($this->resolvePassword());
    }

    private function resolveUsername(): ?string
    {
        $username = $this->credentials['username'] ?? null;

        if (filled($username)) {
            return (string) $username;
        }

        return $this->partnerCredentials->primary('steadfast')['identifier'] ?? null;
    }

    private function resolvePassword(): ?string
    {
        $password = $this->credentials['password'] ?? null;

        if (filled($password)) {
            return (string) $password;
        }

        return $this->partnerCredentials->primary('steadfast')['password'] ?? null;
    }

    private function hasDeliveryData(array $report): bool
    {
        return ($report['total_order'] ?? 0) > 0
            || ($report['confirmed'] ?? 0) > 0
            || !empty($report['frauds']);
    }

    private function sessionCacheKey(): string
    {
        if ($this->credentials !== null) {
            return self::sessionCacheKeyFor($this->credentials);
        }

        return self::sessionCacheKeyFor(null, (string) $this->resolveUsername());
    }

    private function hasStoredSession(): bool
    {
        return $this->isSession(Cache::get($this->sessionCacheKey()));
    }

    /**
     * @param  array{username?: string, password?: string}|null  $credentials
     */
    public static function sessionCacheKeyFor(?array $credentials = null, ?string $username = null): string
    {
        if ($credentials !== null) {
            $username = trim((string) ($credentials['username'] ?? ''));
            $password = trim((string) ($credentials['password'] ?? ''));

            return 'fraud_check_steadfast_session_' . md5($username . '|' . $password);
        }

        return 'fraud_check_steadfast_session_' . md5((string) $username);
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    public function forgetSessionForCredentials(array $credentials): void
    {
        Cache::forget(self::sessionCacheKeyFor($credentials));
    }

    private function storeSession(string $host, array $cookies): void
    {
        Cache::put($this->sessionCacheKey(), [
            'host' => $host,
            'cookies' => $cookies,
        ], now()->addMinutes(55));
    }

    public function expireSession(): bool
    {
        $cleared = $this->partnerCredentials->forgetSessionCaches('steadfast') > 0;

        // Also clear platform/merchant session keys that may use the steadfast_session_ prefix.
        foreach ($this->partnerCredentials->candidates('steadfast') as $candidate) {
            $key = self::sessionCacheKeyFor([
                'username' => $candidate['identifier'],
                'password' => $candidate['password'],
            ]);
            if (Cache::has($key)) {
                Cache::forget($key);
                $cleared = true;
            }
        }

        $curlPath = $this->legacyCurlPath();
        $hadLegacyCurl = file_exists($curlPath);

        if ($hadLegacyCurl) {
            @unlink($curlPath);
            $cleared = true;
        }

        return $cleared;
    }

    private function forgetSession(): void
    {
        Cache::forget($this->sessionCacheKey());
    }

    /**
     * Official merchant API: GET https://portal.packzy.com/api/v1/fraud_check/score/{phone}
     *
     * @return array<string, mixed>|null Null when no API key pair is configured.
     */
    private function checkViaScoreApi(string $phone): ?array
    {
        $candidates = $this->scoreApiCandidates();

        if ($candidates === []) {
            if ($this->hasRestingKey()) {
                return CourierReportFormatter::emptyReport([
                    'frauds' => [],
                    'rate_limited' => true,
                    'deferred' => true,
                    'message' => 'Steadfast fraud check limit reached for this store. Try again later.',
                ]);
            }

            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return CourierReportFormatter::emptyReport(['frauds' => []]);
        }

        if ($this->scoreApiPaused()) {
            return CourierReportFormatter::emptyReport([
                'frauds' => [],
                'message' => 'Steadfast did not answer. The portal is tried instead.',
            ]);
        }

        $credentialError = null;
        $rateLimited = null;
        $hiddenCountsFallback = null;
        $lastReport = CourierReportFormatter::emptyReport(['frauds' => []]);
        $outcomes = [];
        $missedInRow = 0;
        $startedAt = microtime(true);

        foreach ($candidates as $keys) {
            if (microtime(true) - $startedAt >= self::SCORE_WALK_SECONDS) {
                break;
            }

            // Keys are ordered best first, so the rest only give a ratio too.
            if ($hiddenCountsFallback !== null && ($keys['shape'] ?? null) === 'ratio') {
                break;
            }

            if (! empty($keys['recheck']) && ! $this->apiCredentials->claimRecheck()) {
                continue;
            }

            $tracked = ! empty($keys['hub']);
            $report = $this->requestScoreApi($digits, $keys);
            $failure = $report['failure'] ?? null;

            $this->noteKeyOutcome($report, $keys, $outcomes);
            $report = $this->withoutKeyNotes($report);

            if ($this->isUsableReport($report)) {
                $missedInRow = 0;
                $hidden = $this->hasHiddenCounts($report);
                $this->apiCredentials->noteShape($keys['api_key'], ! $hidden);
                $this->flushPoolOutcomes($tracked || $outcomes !== [], $outcomes, $tracked ? $keys['api_key'] : null);
                $outcomes = [];

                if (! $hidden) {
                    return $report;
                }

                $hiddenCountsFallback ??= $report;

                continue;
            }

            // Steadfast refused the number itself. Another key gets the same answer.
            if ($failure === 'request') {
                $lastReport = $report;

                break;
            }

            if ($failure === 'outage') {
                $lastReport = $report;

                if (++$missedInRow >= self::OUTAGE_STREAK) {
                    $this->pauseScoreApi();

                    break;
                }

                continue;
            }

            $missedInRow = 0;

            if (! empty($report['credential_error'])) {
                $credentialError = $report;

                continue;
            }

            if (! empty($report['rate_limited'])) {
                $rateLimited = $report;

                continue;
            }

            $lastReport = $report;
        }

        $this->flushPoolOutcomes($outcomes !== [], $outcomes);

        if (is_array($hiddenCountsFallback)) {
            return $hiddenCountsFallback;
        }

        if (is_array($credentialError)) {
            return $credentialError;
        }

        if (is_array($rateLimited)) {
            return $rateLimited;
        }

        return $lastReport;
    }

    /**
     * Every usable key, best first: the store's own key, then a rejected hub
     * key that is due for its automatic re-check, then keys that gave parcel
     * counts lately, keys not asked yet, and last the keys that only give a
     * ratio. A resting key sits out until its rest ends. The .env pair is
     * used only when the panel list is empty.
     *
     * @return list<array{api_key: string, secret_key: string, hub: bool, shape: string|null, recheck?: bool}>
     */
    private function scoreApiCandidates(): array
    {
        $apiKey = '';
        $secretKey = '';

        if (is_array($this->credentials)) {
            $apiKey = trim((string) ($this->credentials['api_key'] ?? ''));
            $secretKey = trim((string) ($this->credentials['secret_key'] ?? ''));
        }

        $candidates = [];

        if ($apiKey !== '' && $secretKey !== '' && ! $this->apiCredentials->isCoolingDown($apiKey)) {
            $candidates[] = [
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
                'hub' => false,
                'shape' => $this->apiCredentials->shape($apiKey),
            ];
        }

        if ($this->apiCredentials->hasAccounts()) {
            foreach ($this->apiCredentials->hubCandidates() as $row) {
                if ($row['api_key'] === $apiKey) {
                    continue;
                }

                $row['hub'] = true;
                $candidates[] = $row;
            }
        } else {
            $envKey = trim((string) config('fraud-checker-bd-courier.steadfast.api_key'));
            $envSecret = trim((string) config('fraud-checker-bd-courier.steadfast.secret_key'));

            if ($envKey !== '' && $envSecret !== '' && $envKey !== $apiKey && ! $this->apiCredentials->isCoolingDown($envKey)) {
                $candidates[] = [
                    'api_key' => $envKey,
                    'secret_key' => $envSecret,
                    'hub' => false,
                    'shape' => $this->apiCredentials->shape($envKey),
                ];
            }
        }

        usort($candidates, fn (array $a, array $b) => $this->keyRank($a) <=> $this->keyRank($b));

        return $candidates;
    }

    /**
     * @param  array{hub: bool, shape: string|null, recheck?: bool}  $keys
     */
    private function keyRank(array $keys): int
    {
        if (($keys['shape'] ?? null) === 'ratio') {
            return 4;
        }

        if (empty($keys['hub'])) {
            return 0;
        }

        if (! empty($keys['recheck'])) {
            return 1;
        }

        return ($keys['shape'] ?? null) === 'counts' ? 2 : 3;
    }

    private function hasRestingKey(): bool
    {
        if ($this->apiCredentials->hasCoolingKey()) {
            return true;
        }

        $keys = [
            is_array($this->credentials) ? (string) ($this->credentials['api_key'] ?? '') : '',
            (string) config('fraud-checker-bd-courier.steadfast.api_key'),
        ];

        foreach ($keys as $apiKey) {
            if ($this->apiCredentials->isCoolingDown($apiKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A failure that can pass rests the key, a store's own key too. Only a
     * key saved in the panel gets its error written there; a rejected store
     * key rests half an hour instead. A refused number says nothing about
     * the key.
     *
     * @param  array<string, mixed>  $report
     * @param  array{api_key: string, hub?: bool}  $keys
     * @param  array<string, string>  $outcomes
     */
    private function noteKeyOutcome(array $report, array $keys, array &$outcomes): void
    {
        if (($report['failure'] ?? null) === 'request') {
            return;
        }

        $apiKey = $keys['api_key'];
        $retryAfter = (int) ($report['key_retry_after'] ?? 0);
        $cooldown = (int) ($report['key_cooldown'] ?? 0);

        if ($retryAfter > 0) {
            $this->apiCredentials->rest($apiKey, $retryAfter, exact: true);

            return;
        }

        if ($cooldown > 0) {
            $this->apiCredentials->rest($apiKey, $cooldown);

            return;
        }

        $error = isset($report['key_error']) ? trim((string) $report['key_error']) : '';

        if (! empty($keys['hub'])) {
            $outcomes[$apiKey] = $error;

            return;
        }

        if ($error !== '') {
            $this->apiCredentials->rest($apiKey, self::STORE_KEY_REJECTED_REST_SECONDS, exact: true);

            return;
        }

        $this->apiCredentials->clearRest($apiKey);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function withoutKeyNotes(array $report): array
    {
        unset($report['key_error'], $report['key_cooldown'], $report['key_retry_after'], $report['failure']);

        return $report;
    }

    /**
     * Two keys in a row with no answer means Steadfast itself is down. Every
     * worker then skips the API for a minute and goes to the portal, instead
     * of resting every key one by one.
     */
    private function pauseScoreApi(): void
    {
        Cache::put('fraud_check_steadfast_score_paused', time() + self::OUTAGE_PAUSE_SECONDS, self::OUTAGE_PAUSE_SECONDS);
        LogHelper::saveLog('Steadfast fraud check error', 'Score API did not answer for two keys in a row; paused for a minute.');
    }

    private function scoreApiPaused(): bool
    {
        return Cache::has('fraud_check_steadfast_score_paused');
    }

    /**
     * @param  array<string, string>  $outcomes
     */
    private function flushPoolOutcomes(bool $fromPool, array $outcomes, ?string $usedApiKey = null): void
    {
        if (! $fromPool || ($outcomes === [] && trim((string) $usedApiKey) === '')) {
            return;
        }

        try {
            $this->apiCredentials->recordOutcomes($outcomes, $usedApiKey);
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Steadfast fraud check error', 'Could not save key status: '.$exception->getMessage());
        }
    }

    /**
     * Only `rejected` turns a key off. Every other failure can pass.
     *
     * @return array{ok: bool, error: string|null, temporary: bool, rate_limited: bool, cooldown: int, retry_after: int}
     */
    public function probeApiKey(string $apiKey, string $secretKey): array
    {
        $report = $this->requestScoreApi('01700000000', [
            'api_key' => trim($apiKey),
            'secret_key' => trim($secretKey),
        ]);

        $error = trim((string) ($report['key_error'] ?? ''));
        $ok = $error === '' && $this->isUsableReport($report);

        return [
            'ok' => $ok,
            'error' => $error !== '' ? $error : null,
            'temporary' => ! $ok && ($report['failure'] ?? null) !== 'rejected',
            'rate_limited' => ! empty($report['rate_limited']),
            'cooldown' => (int) ($report['key_cooldown'] ?? 0),
            'retry_after' => (int) ($report['key_retry_after'] ?? 0),
        ];
    }

    /**
     * @param  array{api_key: string, secret_key: string}  $keys
     * @return array<string, mixed>
     */
    private function requestScoreApi(string $digits, array $keys): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);

        try {
            $response = $this->scoreClient()
                ->withHeaders([
                    'Api-Key' => $keys['api_key'],
                    'Secret-Key' => $keys['secret_key'],
                ])
                ->get(self::SCORE_URL.$digits);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast fraud check error', 'Score API request failed: '.CourierTransport::safeMessage($th));

            return array_merge($report, [
                'key_error' => 'Could not reach Steadfast. Another saved key is tried automatically.',
                'key_cooldown' => 60,
                'failure' => 'outage',
            ]);
        }

        return $this->scoreReportFromResponse($response);
    }

    /**
     * `failure` says what went wrong: `rejected` (the key or secret),
     * `limited` (this key's quota), `outage` (Steadfast did not answer) or
     * `request` (Steadfast refused this number, whatever the key).
     *
     * @return array<string, mixed>
     */
    private function scoreReportFromResponse(Response $response): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);
        $status = $response->status();

        if ($status === 401) {
            return $this->rejectedKeyReport($report);
        }

        if ($status === 429) {
            return $this->limitedKeyReport($report, $this->retryAfterSeconds($response));
        }

        if (! $response->successful()) {
            LogHelper::saveLog('Steadfast fraud check error', 'Score API failed with status '.$status);

            $outage = $response->serverError() || $status === 403 || $status === 408;

            return array_merge($report, [
                'key_error' => 'Steadfast request failed (HTTP '.$status.').',
                'key_cooldown' => $outage ? ($status === 403 ? 600 : 120) : 0,
                'failure' => $outage ? 'outage' : 'request',
            ]);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return array_merge($report, [
                'key_error' => 'Steadfast returned an unreadable response.',
                'key_cooldown' => 120,
                'failure' => 'outage',
            ]);
        }

        $payloadStatus = $payload['status'] ?? null;
        if ($payloadStatus !== null && (int) $payloadStatus !== 200) {
            return match ((int) $payloadStatus) {
                401 => $this->rejectedKeyReport($report),
                429 => $this->limitedKeyReport($report, 0),
                default => array_merge($report, [
                    'key_error' => 'Steadfast returned status '.$payloadStatus.'.',
                    'failure' => 'request',
                ]),
            };
        }

        return array_merge(CourierReportFormatter::fromSteadfastScore($payload), [
            'api_success' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function rejectedKeyReport(array $report): array
    {
        LogHelper::saveLog('Steadfast fraud check error', 'Score API rejected the API key');

        return array_merge($report, [
            'auth_failed' => true,
            'credential_error' => true,
            'key_error' => 'Steadfast rejected this API key or secret.',
            'failure' => 'rejected',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function limitedKeyReport(array $report, int $retryAfter): array
    {
        LogHelper::saveLog('Steadfast fraud check error', 'Score API rate limit reached');

        return array_merge($report, [
            'rate_limited' => true,
            'message' => 'Steadfast fraud check limit reached for this store. Try again later.',
            'key_error' => 'Steadfast rate limit reached for this key.',
            'key_cooldown' => 900,
            'key_retry_after' => $retryAfter,
            'failure' => 'limited',
        ]);
    }

    /**
     * Steadfast's own wait time, when it sends one, beats our guess.
     */
    private function retryAfterSeconds(Response $response): int
    {
        $value = trim((string) $response->header('Retry-After'));

        if ($value === '') {
            return 0;
        }

        $seconds = is_numeric($value) ? (int) $value : (int) (strtotime($value) ?: 0) - time();

        return $seconds > 0 ? max(30, min(3600, $seconds)) : 0;
    }

    private function checkViaCachedSession(string $phone): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);
        $cached = Cache::get($this->sessionCacheKey());

        if (!is_array($cached) || empty($cached['cookies']) || empty($cached['host'])) {
            return $report;
        }

        try {
            return $this->fetchFraudReport(
                $phone,
                (string) $cached['host'],
                (array) $cached['cookies'],
                invalidateCacheOnFailure: true,
            );
        } catch (\Throwable $th) {
            $this->noteSessionMiss();
            LogHelper::saveLog('Steadfast fraud check error', 'Cached session failed: ' . $th->getMessage());

            return $report;
        }
    }

    /**
     * One login per account at a time, shared with the courier booking
     * client. A worker that waits uses the session the first one made. After
     * a failed login the account rests, so a bad password or a Steadfast
     * lockout is not repeated on every number.
     */
    private function checkViaLogin(string $phone): array
    {
        $account = [
            'username' => (string) ($this->resolveUsername() ?? ''),
            'password' => (string) ($this->resolvePassword() ?? ''),
        ];

        if (trim($account['username']) === '' || trim($account['password']) === '') {
            return CourierReportFormatter::emptyReport(['frauds' => []]);
        }

        // Callers try the saved session just before this. That same session is
        // not asked twice, and it is not replaced while it may still be alive.
        $tried = Cache::get($this->sessionCacheKey());

        if ($this->isSession($tried)) {
            return $this->sessionMissedReport();
        }

        if ($this->loginResting($account)) {
            return $this->loginSkippedReport('Steadfast login is resting after a failed try.');
        }

        try {
            return Cache::lock(SteadfastPortalSessionClient::loginLockKeyFor($account), self::LOGIN_LOCK_SECONDS)
                ->block(self::LOGIN_WAIT_SECONDS, function () use ($phone, $account) {
                    if ($this->hasStoredSession()) {
                        return $this->checkViaCachedSession($phone);
                    }

                    if ($this->loginResting($account)) {
                        return $this->loginSkippedReport('Steadfast login is resting after a failed try.');
                    }

                    return $this->loginAcrossHosts($phone, $account);
                });
        } catch (LockTimeoutException) {
            if ($this->hasStoredSession()) {
                return $this->checkViaCachedSession($phone);
            }

            LogHelper::saveLog('Steadfast fraud check error', 'Steadfast login is already running, so a second login was skipped.');

            return $this->loginSkippedReport('Steadfast login is already running.');
        }
    }

    private function isSession(mixed $cached): bool
    {
        return is_array($cached) && ! empty($cached['cookies']) && ! empty($cached['host']);
    }

    private function sessionMissedReport(): array
    {
        return $this->loginSkippedReport('Steadfast did not answer on the saved session. This number is checked again shortly.');
    }

    /**
     * @param  array{username: string, password: string}  $account
     */
    private function loginResting(array $account): bool
    {
        return SteadfastPortalSessionClient::loginCooldownActiveFor($account)
            || Cache::has($this->loginRestKey($account));
    }

    /**
     * @param  array{username: string, password: string}  $account
     */
    private function restLogin(array $account, int $seconds, string $reason): void
    {
        Cache::put($this->loginRestKey($account), time() + $seconds, $seconds);
        LogHelper::saveLog('Steadfast fraud check error', $reason.' Portal login rests for '.$seconds.'s.');
    }

    /**
     * @param  array{username: string, password: string}  $account
     */
    private function loginRestKey(array $account): string
    {
        return 'fraud_check_steadfast_login_rest_'.md5(strtolower(trim($account['username'])).'|'.$account['password']);
    }

    private function loginSkippedReport(string $message): array
    {
        return CourierReportFormatter::emptyReport([
            'frauds' => [],
            'login_skipped' => true,
            'message' => $message,
        ]);
    }

    /**
     * The second host is only for a host that cannot be reached. Once the
     * login form was sent, Steadfast may already have logged us in, so a
     * second form would be a second login.
     *
     * @param  array{username: string, password: string}  $account
     */
    private function loginAcrossHosts(string $phone, array $account): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);
        $this->loginPosted = false;

        foreach (self::HOSTS as $host) {
            try {
                $attempt = $this->attemptLoginCheck($phone, $host, $account);

                if ($this->hasDeliveryData($attempt)) {
                    return $attempt;
                }

                if ($this->isUsableReport($attempt)) {
                    return $attempt;
                }

                if (! empty($attempt['auth_failed'])) {
                    return $attempt;
                }

                $report = $attempt;
            } catch (\Throwable $th) {
                LogHelper::saveLog('Steadfast fraud check error', $host . ': ' . $th->getMessage());

                if (!$this->isRetryableError($th)) {
                    break;
                }
            }

            if ($this->loginPosted || $this->hasStoredSession()) {
                break;
            }
        }

        return $report;
    }

    /**
     * @param  array{username: string, password: string}  $account
     */
    private function attemptLoginCheck(string $phone, string $host, array $account): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);

        try {
            return $this->performLoginCheck($phone, $host, $report, $account);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast fraud check error', "{$host}: {$th->getMessage()}");

            if ($this->loginPosted && ! $this->hasStoredSession()) {
                $this->restLogin($account, self::LOGIN_FAILED_REST_SECONDS, "Login form on {$host} got no answer.");
            }

            return $report;
        }
    }

    /**
     * @param  array{username: string, password: string}  $account
     */
    private function performLoginCheck(string $phone, string $host, array $report, array $account): array
    {
        $loginPage = $this->client()->get($this->url('/login', $host));

        if (!$loginPage->successful()) {
            LogHelper::saveLog('Steadfast fraud check error', "Unable to load login page on {$host}.");

            if ($loginPage->status() === 429 || SteadfastPortalSessionClient::looksRateLimited($loginPage->body())) {
                return $this->loginLimitedReport($report, $account);
            }

            return $report;
        }

        if (SteadfastPortalSessionClient::looksRateLimited($loginPage->body())) {
            return $this->loginLimitedReport($report, $account);
        }

        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $loginPage->body(), $matches);
        $csrfToken = $matches[1] ?? null;

        if (!$csrfToken) {
            LogHelper::saveLog('Steadfast fraud check error', "CSRF token not found on {$host} login page");

            return $report;
        }

        $cookies = $this->cookiesToArray($loginPage->cookies()->toArray());

        $this->loginPosted = true;

        $loginResponse = $this->loginClient()
            ->withCookies($cookies, $host)
            ->asForm()
            ->withHeaders(['Referer' => $this->url('/login', $host)])
            ->post($this->url('/login', $host), [
                '_token' => $csrfToken,
                'email' => $this->resolveUsername(),
                'password' => $this->resolvePassword(),
            ]);

        $status = $loginResponse->status();

        if ($status === 429 || SteadfastPortalSessionClient::looksRateLimited($loginResponse->body())) {
            return $this->loginLimitedReport($report, $account);
        }

        if (!$loginResponse->successful() && !$loginResponse->redirect()) {
            LogHelper::saveLog('Steadfast fraud check error', "Login failed on {$host} with status " . $status);

            $rejected = in_array($status, [401, 403, 422], true);
            $this->restLogin(
                $account,
                $rejected ? self::LOGIN_REJECTED_REST_SECONDS : self::LOGIN_FAILED_REST_SECONDS,
                "Login on {$host} returned HTTP {$status}."
            );

            return array_merge($report, ['auth_failed' => true]);
        }

        $sessionCookies = $this->mergeCookies(
            $cookies,
            $this->cookiesToArray($loginResponse->cookies()->toArray())
        );

        if (self::loginWasRejected($loginResponse->body(), (string) $loginResponse->header('Location'))) {
            LogHelper::saveLog('Steadfast fraud check error', "Steadfast rejected the portal login on {$host}");
            $this->restLogin($account, self::LOGIN_REJECTED_REST_SECONDS, 'Steadfast rejected the portal login.');

            return array_merge($report, [
                'auth_failed' => true,
                'credential_error' => true,
            ]);
        }

        if (! self::hasSessionCookie($sessionCookies)) {
            LogHelper::saveLog('Steadfast fraud check error', "Login on {$host} did not create a session cookie");
            $this->restLogin($account, self::LOGIN_FAILED_REST_SECONDS, "Login on {$host} did not create a session cookie.");

            return array_merge($report, ['auth_failed' => true]);
        }

        Cache::forget($this->loginRestKey($account));
        $this->storeSession($host, $sessionCookies);

        if ($this->credentials === null) {
            SteadfastCurlExporter::save($host, $sessionCookies, $phone);
        }

        return $this->fetchFraudReport($phone, $host, $sessionCookies, invalidateCacheOnFailure: true);
    }

    private function fetchFraudReport(
        string $phone,
        string $host,
        array $sessionCookies,
        bool $invalidateCacheOnFailure = false,
    ): array {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);

        try {
            $fraudResponse = $this->apiClient($host, $sessionCookies)
                ->withCookies($sessionCookies, $host)
                ->get($this->url("/user/frauds/check/{$phone}", $host));
        } catch (\Throwable $th) {
            if ($invalidateCacheOnFailure) {
                $this->noteSessionMiss();
            }

            LogHelper::saveLog('Steadfast fraud check error', "Fraud API request failed on {$host}: {$th->getMessage()}");

            return $report;
        }

        if ($this->looksLoggedOut($fraudResponse)) {
            if ($invalidateCacheOnFailure) {
                $this->forgetSession();
            }

            LogHelper::saveLog('Steadfast fraud check error', 'Session expired — will re-login on next check.');

            return $report;
        }

        // A busy portal is not a dead session. Keeping it avoids a new login.
        if ($fraudResponse->status() === 429) {
            LogHelper::saveLog('Steadfast fraud check error', "Fraud API rate limited on {$host}");

            return array_merge($report, [
                'rate_limited' => true,
                'message' => 'Steadfast asked us to slow down. This number is checked again shortly.',
            ]);
        }

        if (!$fraudResponse->successful()) {
            if ($invalidateCacheOnFailure) {
                $this->noteSessionMiss();
            }

            LogHelper::saveLog(
                'Steadfast fraud check error',
                "Fraud API failed on {$host} with status " . $fraudResponse->status()
            );

            return $report;
        }

        $payload = $fraudResponse->json() ?? [];

        if (!SteadfastCurlExporter::isValid(json_encode($payload))) {
            if ($invalidateCacheOnFailure) {
                $this->noteSessionMiss();
            }

            LogHelper::saveLog('Steadfast fraud check error', "Fraud API on {$host} returned an answer we could not read.");

            return $report;
        }

        if ($invalidateCacheOnFailure) {
            Cache::forget($this->sessionMissKey());
        }

        return array_merge(CourierReportFormatter::fromSteadfast($payload), [
            'api_success' => true,
        ]);
    }

    /**
     * Only a sign that the session is gone drops it. A timeout or a portal
     * error keeps it, because dropping it means a new login (and a "new
     * login" alert to the account owner) on the next number.
     */
    private function looksLoggedOut(Response $response): bool
    {
        if (in_array($response->status(), [401, 419], true)) {
            return true;
        }

        $body = $response->body();

        if (self::looksLikeLoginPage($body)) {
            return true;
        }

        $location = (string) $response->header('Location');

        if ($location !== '' && $response->redirect() && self::loginWasRejected('', $location)) {
            return true;
        }

        return str_contains(strtolower(substr($body, 0, 300)), 'unauthenticated');
    }

    /**
     * Misses in a row on a session that may still be alive. Only a run of
     * them drops it, so one slow answer does not cost a login.
     */
    private function noteSessionMiss(): void
    {
        $key = $this->sessionMissKey();

        try {
            Cache::add($key, 0, now()->addMinutes(10));
            $misses = (int) Cache::increment($key);
        } catch (\Throwable) {
            $misses = self::SESSION_MISS_LIMIT;
        }

        if ($misses >= self::SESSION_MISS_LIMIT) {
            Cache::forget($key);
            $this->forgetSession();
        }
    }

    private function sessionMissKey(): string
    {
        return $this->sessionCacheKey().'_misses';
    }

    private function checkViaLegacyCurl(string $phone): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);

        try {
            if (!function_exists('shell_exec')) {
                return $report;
            }

            $curlString = file_get_contents($this->legacyCurlPath());
            $curlString = preg_replace(
                '#https://(?:www\.)?steadfast\.com\.bd/user/frauds/check/\d+#',
                'https://www.steadfast.com.bd/user/frauds/check/' . $phone,
                $curlString
            );

            if (!preg_match('/\s\-s\b/', $curlString)) {
                $curlString = preg_replace('/^curl\s/', 'curl -s ', $curlString);
            }

            $raw = $this->runLegacyCurlScript($curlString);
            $payload = json_decode($raw, true) ?? [];

            if (!SteadfastCurlExporter::isValid($raw)) {
                LogHelper::saveLog(
                    'Steadfast legacy curl fraud check error',
                    'Saved curl session expired — credentials login will refresh it automatically.'
                );

                return $report;
            }

            return array_merge(CourierReportFormatter::fromSteadfast($payload), [
                'api_success' => true,
            ]);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast legacy curl fraud check error', $th->getMessage());
        }

        return $report;
    }

    private function runLegacyCurlScript(string $curlString): string
    {
        $script = tempnam(sys_get_temp_dir(), 'steadfast_curl_');

        if ($script === false) {
            return '';
        }

        file_put_contents($script, $curlString . PHP_EOL);
        chmod($script, 0700);

        $raw = (string) shell_exec('bash ' . escapeshellarg($script) . ' 2>/dev/null');

        @unlink($script);

        return $raw;
    }

    /**
     * @param  array<string, string>  $cookies
     */
    public static function hasSessionCookie(array $cookies): bool
    {
        foreach (self::SESSION_COOKIES as $name) {
            if (filled($cookies[$name] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A guest session cookie is set before login. Rejection is the login
     * form itself, or a redirect back to /login.
     */
    public static function loginWasRejected(string $body, string $location = ''): bool
    {
        if (self::looksLikeLoginPage($body)) {
            return true;
        }

        $path = parse_url($location, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $location;
        $path = rtrim($path, '/');

        return $path === '/login' || str_ends_with($path, '/login');
    }

    public static function looksLikeLoginPage(string $html): bool
    {
        $html = strtolower($html);

        if (str_starts_with(ltrim($html), '{') || str_starts_with(ltrim($html), '[')) {
            return false;
        }

        return str_contains($html, 'name="email"')
            && str_contains($html, 'name="password"')
            && (str_contains($html, 'merchant login') || str_contains($html, '/login') || str_contains($html, 'remember me'));
    }

    private function scoreClient(): PendingRequest
    {
        return Http::timeout(12)
            ->connectTimeout(5)
            ->withOptions([
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->retry(2, 400, fn ($exception) => CourierTransport::shouldRetry($exception), throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);
    }

    private function client(): PendingRequest
    {
        return Http::timeout(25)
            ->connectTimeout(10)
            ->withOptions([
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->retry(3, 1500, fn ($exception) => $this->isRetryableError($exception), throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);
    }

    /**
     * The login form is sent once. A resend after a timeout is a second
     * login on Steadfast and a second "new login" alert for the account.
     */
    private function loginClient(): PendingRequest
    {
        return Http::timeout(25)
            ->connectTimeout(10)
            ->withOptions([
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array{username: string, password: string}  $account
     * @return array<string, mixed>
     */
    private function loginLimitedReport(array $report, array $account): array
    {
        SteadfastPortalSessionClient::coolDownLoginFor($account);
        LogHelper::saveLog('Steadfast fraud check error', 'Steadfast says too many login attempts. Portal logins rest for 5 minutes.');

        return array_merge($report, [
            'auth_failed' => true,
            'rate_limited' => true,
            'message' => 'Too many Steadfast login attempts. Logins rest for a few minutes.',
        ]);
    }

    private function apiClient(string $host, array $sessionCookies = []): PendingRequest
    {
        return Http::timeout(25)
            ->connectTimeout(10)
            ->withOptions([
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->retry(3, 1500, fn ($exception) => $this->isRetryableError($exception), throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => $this->url('/user/frauds/check', $host),
                'X-XSRF-TOKEN' => urldecode($sessionCookies['XSRF-TOKEN'] ?? ''),
            ]);
    }

    private function isRetryableError(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException || CourierTransport::shouldRetry($exception)) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $message = strtolower($exception->getMessage());

            return str_contains($message, 'connection reset')
                || str_contains($message, 'connection refused')
                || str_contains($message, 'timed out')
                || str_contains($message, 'could not resolve');
        }

        return false;
    }

    private function url(string $path, string $host = self::HOSTS[0]): string
    {
        return 'https://' . $host . $path;
    }

    private function legacyCurlPath(): string
    {
        return SteadfastCurlExporter::path();
    }

    private function cookiesToArray(array $cookies): array
    {
        $mapped = [];

        foreach ($cookies as $cookie) {
            $mapped[$cookie['Name']] = $cookie['Value'];
        }

        return $mapped;
    }

    private function mergeCookies(array ...$cookieSets): array
    {
        $merged = [];

        foreach ($cookieSets as $set) {
            $merged = array_merge($merged, $set);
        }

        return $merged;
    }
}
