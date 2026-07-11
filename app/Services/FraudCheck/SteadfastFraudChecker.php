<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteadfastFraudChecker
{
    private const HOSTS = ['www.steadfast.com.bd', 'steadfast.com.bd'];

    /** @var array{username: string, password: string}|null */
    private ?array $credentials = null;

    public function __construct(
        private FraudPartnerCredentialResolver $partnerCredentials,
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

    private function performCheck(string $phone): array
    {
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

                return $fallbackReport;
            } finally {
                $this->credentials = $merchantCredentials;
            }
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

            return $legacyReport;
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

        if (!isset($sessionCookies['steadfast_courier_session'])) {
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

    private function client(): PendingRequest
    {
        return Http::timeout(25)
            ->connectTimeout(10)
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
        if ($exception instanceof ConnectionException) {
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
