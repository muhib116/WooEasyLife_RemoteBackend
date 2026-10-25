<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
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
     * One hub check must not walk the whole saved-key list. A rejected key
     * counts toward Steadfast's lockout, so stop after a few tries.
     */
    private const MAX_SCORE_API_ATTEMPTS = 3;

    /**
     * Steadfast renamed the portal session cookie. The new name is also set
     * on the guest login page, so its presence alone is not proof of login.
     *
     * @var list<string>
     */
    private const SESSION_COOKIES = ['sfc_user_session', 'steadfast_courier_session'];

    /** @var array{username: string, password: string}|null */
    private ?array $credentials = null;

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
            $fromPool = false;
            $candidates = $this->scoreApiCandidates($fromPool);
            $keys = $candidates[0] ?? null;
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
                        ->get('https://portal.packzy.com/api/v1/fraud_check/score/'.$digits);
                },
                'read' => function (Response $response) use ($fromPool, $keys): ?array {
                    $report = $this->scoreReportFromResponse($response);
                    $cooldown = (int) ($report['key_cooldown'] ?? 0);
                    unset($report['key_error'], $report['key_cooldown']);

                    if (! $this->isUsableReport($report)) {
                        if ($fromPool && $cooldown > 0) {
                            $this->apiCredentials->coolDown($keys['api_key'], $cooldown);
                        }

                        return null;
                    }

                    if ($fromPool) {
                        $this->flushPoolOutcomes(true, [], $keys['api_key']);
                    }

                    return $report;
                },
            ];
        } finally {
            $this->credentials = null;
        }
    }

    private function performCheck(string $phone): array
    {
        $apiReport = $this->checkViaScoreApi($phone);

        if (is_array($apiReport) && (
            $this->isUsableReport($apiReport)
            || ! empty($apiReport['rate_limited'])
        )) {
            return $apiReport;
        }

        $report = $this->runCredentialCheck($phone);

        if ($this->isUsableReport($report)) {
            return $report;
        }

        if ($this->credentials !== null && $this->platformCredentialsDifferFromMerchant()) {
            LogHelper::saveLog(
                'Steadfast fraud check fallback',
                'Merchant portal credentials returned no data; retrying with platform credentials.'
            );

            $merchantCredentials = $this->credentials;
            $this->credentials = null;

            try {
                $fallbackReport = $this->runCredentialCheck($phone);

                if ($this->isUsableReport($fallbackReport)) {
                    return $fallbackReport;
                }

                $report = $fallbackReport;
            } finally {
                $this->credentials = $merchantCredentials;
            }
        }

        if (is_array($apiReport) && ! empty($apiReport['credential_error'])) {
            return $apiReport;
        }

        return $report;
    }

    private function runCredentialCheck(string $phone): array
    {
        // Explicit merchant credentials (plugin/API token path): single account.
        if ($this->credentials !== null) {
            if ($this->hasCredentials()) {
                $report = $this->checkViaCachedSession($phone);

                if ($this->isUsableReport($report)) {
                    return $report;
                }

                $report = $this->checkViaLogin($phone);

                if ($this->isUsableReport($report)) {
                    return $report;
                }

                return CourierReportFormatter::emptyReport([
                    'frauds' => [],
                    'credential_error' => true,
                ]);
            }
        } elseif ($this->partnerCredentials->isConfigured('steadfast')) {
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
                    $this->credentials = null;
                }
            }

            // Session expired / cold: pick a random account, then failover.
            $credentialFailure = null;

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

                    if (! empty($report['credential_error'])) {
                        $credentialFailure = $report;
                    }

                    $this->partnerCredentials->markFailure($candidate['id'], 'Steadfast login/check failed');
                } finally {
                    $this->credentials = null;
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

            return $legacyReport;
        }

        if (isset($credentialFailure)) {
            return $credentialFailure;
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

    private function platformCredentialsDifferFromMerchant(): bool
    {
        if (! $this->isConfigured() || $this->credentials === null) {
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
        $fromPool = false;
        $candidates = $this->scoreApiCandidates($fromPool);

        if ($candidates === []) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return CourierReportFormatter::emptyReport(['frauds' => []]);
        }

        $credentialError = null;
        $rateLimited = null;
        $lastReport = CourierReportFormatter::emptyReport(['frauds' => []]);
        $outcomes = [];

        foreach ($candidates as $keys) {
            $report = $this->requestScoreApi($digits, $keys);

            if ($fromPool) {
                $this->noteHubKeyOutcome($report, $keys['api_key'], $outcomes);
            }

            unset($report['key_error'], $report['key_cooldown']);

            if ($this->isUsableReport($report)) {
                $this->flushPoolOutcomes($fromPool, $outcomes, $keys['api_key']);

                return $report;
            }

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

        $this->flushPoolOutcomes($fromPool, $outcomes);

        if (is_array($credentialError)) {
            return $credentialError;
        }

        if (is_array($rateLimited)) {
            return $rateLimited;
        }

        return $lastReport;
    }

    /**
     * A store's own API key pair is used alone. Hub checks shuffle saved
     * pairs that have no error and try the next one when a key fails.
     * A key with an error is not tried again. The .env pair is used only
     * when the panel list is empty.
     *
     * @return list<array{api_key: string, secret_key: string}>
     */
    private function scoreApiCandidates(bool &$fromPool = false): array
    {
        $fromPool = false;
        $apiKey = trim((string) ($this->credentials['api_key'] ?? ''));
        $secretKey = trim((string) ($this->credentials['secret_key'] ?? ''));

        if ($apiKey !== '' && $secretKey !== '') {
            return [[
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
            ]];
        }

        if ($this->apiCredentials->hasAccounts()) {
            $fromPool = true;

            return $this->apiCredentials->hubCandidates(self::MAX_SCORE_API_ATTEMPTS);
        }

        $apiKey = trim((string) config('fraud-checker-bd-courier.steadfast.api_key'));
        $secretKey = trim((string) config('fraud-checker-bd-courier.steadfast.secret_key'));

        if ($apiKey === '' || $secretKey === '') {
            return [];
        }

        return [[
            'api_key' => $apiKey,
            'secret_key' => $secretKey,
        ]];
    }

    /**
     * Only a rejected key is saved as a key error, which keeps it out of
     * hub checks until an admin test. A rate limit or a Steadfast outage
     * only rests the key for a few minutes.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, string>  $outcomes
     */
    private function noteHubKeyOutcome(array $report, string $apiKey, array &$outcomes): void
    {
        $cooldown = (int) ($report['key_cooldown'] ?? 0);

        if ($cooldown > 0) {
            $this->apiCredentials->coolDown($apiKey, $cooldown);

            return;
        }

        $outcomes[$apiKey] = isset($report['key_error']) ? trim((string) $report['key_error']) : '';
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
     * @return array{ok: bool, error: string|null}
     */
    public function probeApiKey(string $apiKey, string $secretKey): array
    {
        $report = $this->requestScoreApi('01700000000', [
            'api_key' => trim($apiKey),
            'secret_key' => trim($secretKey),
        ]);

        $error = trim((string) ($report['key_error'] ?? ''));

        return [
            'ok' => $error === '' && $this->isUsableReport($report),
            'error' => $error !== '' ? $error : null,
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
                ->get('https://portal.packzy.com/api/v1/fraud_check/score/'.$digits);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast fraud check error', 'Score API request failed: '.CourierTransport::safeMessage($th));

            return array_merge($report, [
                'key_error' => 'Could not reach Steadfast. Another saved key is tried automatically.',
                'key_cooldown' => 60,
            ]);
        }

        return $this->scoreReportFromResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreReportFromResponse(Response $response): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);

        if ($response->status() === 401) {
            LogHelper::saveLog('Steadfast fraud check error', 'Score API rejected the API key');

            return array_merge($report, [
                'auth_failed' => true,
                'credential_error' => true,
                'key_error' => 'Steadfast rejected this API key or secret.',
            ]);
        }

        if ($response->status() === 429) {
            LogHelper::saveLog('Steadfast fraud check error', 'Score API rate limit reached');

            return array_merge($report, [
                'rate_limited' => true,
                'message' => 'Steadfast fraud check limit reached for this store. Try again later.',
                'key_error' => 'Steadfast rate limit reached for this key.',
                'key_cooldown' => 900,
            ]);
        }

        if (! $response->successful()) {
            LogHelper::saveLog('Steadfast fraud check error', 'Score API failed with status '.$response->status());

            return array_merge($report, array_filter([
                'key_error' => 'Steadfast request failed (HTTP '.$response->status().').',
                'key_cooldown' => $response->serverError() ? 120 : null,
            ], fn ($value) => $value !== null));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return array_merge($report, [
                'key_error' => 'Steadfast returned an unreadable response.',
                'key_cooldown' => 120,
            ]);
        }

        $status = $payload['status'] ?? null;
        if ($status !== null && (int) $status !== 200) {
            return array_merge($report, [
                'key_error' => 'Steadfast returned status '.$status.'.',
            ]);
        }

        return array_merge(CourierReportFormatter::fromSteadfastScore($payload), [
            'api_success' => true,
        ]);
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
            $this->forgetSession();
            LogHelper::saveLog('Steadfast fraud check error', 'Cached session failed: ' . $th->getMessage());

            return $report;
        }
    }

    private function checkViaLogin(string $phone): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);

        foreach (self::HOSTS as $host) {
            try {
                $attempt = $this->attemptLoginCheck($phone, $host);

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
        }

        return $report;
    }

    private function attemptLoginCheck(string $phone, string $host): array
    {
        $report = CourierReportFormatter::emptyReport(['frauds' => []]);

        try {
            return $this->performLoginCheck($phone, $host, $report);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast fraud check error', "{$host}: {$th->getMessage()}");

            return $report;
        }
    }

    private function performLoginCheck(string $phone, string $host, array $report): array
    {
        $loginPage = $this->client()->get($this->url('/login', $host));

        if (!$loginPage->successful()) {
            LogHelper::saveLog('Steadfast fraud check error', "Unable to load login page on {$host}.");

            return $report;
        }

        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $loginPage->body(), $matches);
        $csrfToken = $matches[1] ?? null;

        if (!$csrfToken) {
            LogHelper::saveLog('Steadfast fraud check error', "CSRF token not found on {$host} login page");

            return $report;
        }

        $cookies = $this->cookiesToArray($loginPage->cookies()->toArray());

        $loginResponse = $this->client()
            ->withCookies($cookies, $host)
            ->asForm()
            ->withHeaders(['Referer' => $this->url('/login', $host)])
            ->post($this->url('/login', $host), [
                '_token' => $csrfToken,
                'email' => $this->resolveUsername(),
                'password' => $this->resolvePassword(),
            ]);

        if (!$loginResponse->successful() && !$loginResponse->redirect()) {
            LogHelper::saveLog('Steadfast fraud check error', "Login failed on {$host} with status " . $loginResponse->status());

            return array_merge($report, ['auth_failed' => true]);
        }

        $sessionCookies = $this->mergeCookies(
            $cookies,
            $this->cookiesToArray($loginResponse->cookies()->toArray())
        );

        if (self::loginWasRejected($loginResponse->body(), (string) $loginResponse->header('Location'))) {
            LogHelper::saveLog('Steadfast fraud check error', "Steadfast rejected the portal login on {$host}");

            return array_merge($report, [
                'auth_failed' => true,
                'credential_error' => true,
            ]);
        }

        if (! self::hasSessionCookie($sessionCookies)) {
            LogHelper::saveLog('Steadfast fraud check error', "Login on {$host} did not create a session cookie");

            return array_merge($report, ['auth_failed' => true]);
        }

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
                $this->forgetSession();
            }

            LogHelper::saveLog('Steadfast fraud check error', "Fraud API request failed on {$host}: {$th->getMessage()}");

            return $report;
        }

        if (!$fraudResponse->successful()) {
            if ($invalidateCacheOnFailure) {
                $this->forgetSession();
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
                $this->forgetSession();
            }

            LogHelper::saveLog('Steadfast fraud check error', 'Session expired — will re-login on next check.');

            return $report;
        }

        return array_merge(CourierReportFormatter::fromSteadfast($payload), [
            'api_success' => true,
        ]);
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
