<?php

namespace App\Services\Courier;

use App\LogHelper;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SteadfastPortalSessionClient
{
    private const HOSTS = ['www.steadfast.com.bd', 'steadfast.com.bd'];

    /** How long to pause portal logins after Steadfast throttles the account. */
    private const LOGIN_COOLDOWN_SECONDS = 300;

    /**
     * Run $callback with an authenticated portal session for the given merchant credentials.
     *
     * Retries at most once, and only when the failure looks like an expired/invalid session.
     *
     * @param  array{username: string, password: string}  $credentials
     * @param  callable(self, string $host, array<string, string> $cookies): mixed  $callback
     */
    public function withSession(array $credentials, callable $callback): mixed
    {
        $session = $this->resolveSession($credentials);

        try {
            return $callback($this, $session['host'], $session['cookies']);
        } catch (\Throwable $th) {
            if (! $this->isSessionFailure($th)) {
                throw $th;
            }

            $this->forgetSession($credentials);
            $session = $this->login($credentials);

            return $callback($this, $session['host'], $session['cookies']);
        }
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array{host: string, cookies: array<string, string>}
     */
    public function resolveSession(array $credentials): array
    {
        $cached = Cache::get($this->cacheKey($credentials));

        if (is_array($cached) && ! empty($cached['host']) && ! empty($cached['cookies']) && is_array($cached['cookies'])) {
            return [
                'host' => (string) $cached['host'],
                'cookies' => (array) $cached['cookies'],
            ];
        }

        return $this->login($credentials);
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array{host: string, cookies: array<string, string>}
     */
    public function login(array $credentials): array
    {
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));

        if ($username === '' || $password === '') {
            throw new RuntimeException('Steadfast portal username/password are not configured.');
        }

        $this->assertLoginNotCooldowned($credentials);

        $lockKey = 'steadfast_portal_login_lock_' . md5($username . '|' . $password);

        try {
            return Cache::lock($lockKey, 45)->block(20, function () use ($credentials, $username, $password) {
                $this->assertLoginNotCooldowned($credentials);

                // Another request may have warmed the session while we waited for the lock.
                $cached = Cache::get($this->cacheKey($credentials));
                if (is_array($cached) && ! empty($cached['host']) && ! empty($cached['cookies']) && is_array($cached['cookies'])) {
                    return [
                        'host' => (string) $cached['host'],
                        'cookies' => (array) $cached['cookies'],
                    ];
                }

                $lastError = 'Unable to authenticate with Steadfast portal.';

                foreach (self::HOSTS as $host) {
                    try {
                        return $this->attemptLogin($host, $username, $password, $credentials);
                    } catch (\Throwable $th) {
                        $lastError = $th->getMessage();
                        LogHelper::saveLog('Steadfast portal login error', $host . ': ' . $lastError);

                        // Rate-limit / auth errors must not fan out across hosts.
                        if ($this->isRateLimitedMessage($lastError) || ! $this->isRetryableError($th)) {
                            break;
                        }
                    }
                }

                throw new RuntimeException($lastError);
            });
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast portal login lock error', $th->getMessage());

            $this->assertLoginNotCooldowned($credentials);

            // Fallback without lock if the cache driver cannot lock.
            $cached = Cache::get($this->cacheKey($credentials));
            if (is_array($cached) && ! empty($cached['host']) && ! empty($cached['cookies']) && is_array($cached['cookies'])) {
                return [
                    'host' => (string) $cached['host'],
                    'cookies' => (array) $cached['cookies'],
                ];
            }

            $lastError = 'Unable to authenticate with Steadfast portal.';
            foreach (self::HOSTS as $host) {
                try {
                    return $this->attemptLogin($host, $username, $password, $credentials);
                } catch (\Throwable $inner) {
                    $lastError = $inner->getMessage();
                    if ($this->isRateLimitedMessage($lastError) || ! $this->isRetryableError($inner)) {
                        break;
                    }
                }
            }

            throw new RuntimeException($lastError);
        }
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    public function forgetSession(array $credentials): void
    {
        Cache::forget($this->cacheKey($credentials));
    }

    /**
     * @param  array<string, string>  $cookies
     */
    public function get(string $path, string $host, array $cookies, bool $expectJson = false): Response
    {
        $request = $expectJson
            ? $this->apiClient($host, $cookies)
            : $this->htmlClient($host, $cookies);

        return $request
            ->withCookies($cookies, $host)
            ->get($this->url($path, $host));
    }

    /**
     * Merge Set-Cookie values from a response into the working cookie jar.
     * Critical after HTML GETs — Laravel rotates XSRF-TOKEN on many page loads.
     *
     * @param  array<string, string>  $cookies
     * @param  array{username: string, password: string}|null  $credentials
     * @return array<string, string>
     */
    public function absorbCookies(
        array $cookies,
        Response $response,
        string $host,
        ?array $credentials = null,
    ): array {
        $merged = $this->mergeCookies(
            $cookies,
            $this->cookiesToArray($response->cookies()->toArray())
        );

        if ($credentials !== null && isset($merged['steadfast_courier_session'])) {
            $this->storeSession($credentials, $host, $merged);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $cookies
     */
    public function postForm(string $path, array $data, string $host, array $cookies): Response
    {
        return $this->htmlClient($host, $cookies)
            ->withCookies($cookies, $host)
            ->asForm()
            ->withHeaders([
                'Referer' => $this->url($path, $host),
                'X-XSRF-TOKEN' => $this->xsrfHeaderValue($cookies),
                'X-CSRF-TOKEN' => $this->plainCsrfFromCookies($cookies),
            ])
            ->post($this->url($path, $host), $data);
    }

    /**
     * Multipart POST matching Steadfast Vue axios FormData submissions.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $cookies
     */
    public function postMultipart(
        string $path,
        array $data,
        string $host,
        array $cookies,
        ?string $referer = null,
    ): Response {
        $parts = [];
        foreach ($data as $name => $value) {
            if ($value === null) {
                $value = '';
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            } else {
                $value = json_encode($value) ?: '';
            }

            $parts[] = [
                'name' => (string) $name,
                'contents' => $value,
            ];
        }

        // Prefer form-urlencoded first — same fields Steadfast reads via $request->all(),
        // and more reliable CSRF cookie pairing than some multipart stacks.
        return $this->apiClient($host, $cookies)
            ->withCookies($cookies, $host)
            ->asForm()
            ->withHeaders([
                'Referer' => $referer ?: $this->url($path, $host),
                'X-XSRF-TOKEN' => $this->xsrfHeaderValue($cookies),
                'X-CSRF-TOKEN' => $this->plainCsrfFromCookies($cookies),
            ])
            ->post($this->url($path, $host), $this->multipartToAssoc($parts));
    }

    /**
     * @param  list<array{name: string, contents: string}>  $parts
     * @return array<string, string>
     */
    private function multipartToAssoc(array $parts): array
    {
        $data = [];
        foreach ($parts as $part) {
            $data[$part['name']] = $part['contents'];
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function xsrfHeaderValue(array $cookies): string
    {
        return urldecode((string) ($cookies['XSRF-TOKEN'] ?? ''));
    }

    /**
     * Best-effort plain CSRF for X-CSRF-TOKEN (when cookie decrypt isn't needed client-side).
     *
     * @param  array<string, string>  $cookies
     */
    private function plainCsrfFromCookies(array $cookies): string
    {
        // Laravel SPAs send the url-decoded XSRF cookie value as X-XSRF-TOKEN.
        // Some stacks also accept the same value on X-CSRF-TOKEN.
        return $this->xsrfHeaderValue($cookies);
    }

    public function looksLikeLoginPage(string $html): bool
    {
        $html = strtolower($html);

        // Avoid false positives on JSON API error bodies.
        if (str_starts_with(ltrim($html), '{') || str_starts_with(ltrim($html), '[')) {
            return false;
        }

        return str_contains($html, 'name="email"')
            && str_contains($html, 'name="password"')
            && (str_contains($html, 'merchant login') || str_contains($html, '/login') || str_contains($html, 'remember me'));
    }

    private function isSessionFailure(\Throwable $th): bool
    {
        $message = $th->getMessage();

        // Never re-login while Steadfast is throttling — that makes the lockout worse.
        if ($this->isRateLimitedMessage($message)) {
            return false;
        }

        $lower = strtolower($message);

        return str_contains($lower, 'session expired')
            || str_contains($lower, 'did not create a session')
            || str_contains($lower, 'unable to authenticate')
            || str_contains($lower, 'login failed');
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array{host: string, cookies: array<string, string>}
     */
    private function attemptLogin(string $host, string $username, string $password, array $credentials): array
    {
        $loginPage = $this->browserClient()->get($this->url('/login', $host));

        if (! $loginPage->successful()) {
            if ($loginPage->status() === 429 || $this->responseLooksRateLimited($loginPage->body())) {
                $this->throwRateLimited($credentials);
            }

            throw new RuntimeException("Unable to load Steadfast login page on {$host}.");
        }

        if ($this->responseLooksRateLimited($loginPage->body())) {
            $this->throwRateLimited($credentials);
        }

        preg_match('/<input[^>]+name="_token"[^>]+value="([^"]+)"/', $loginPage->body(), $matches);
        if (empty($matches[1])) {
            preg_match('/<input[^>]+value="([^"]+)"[^>]+name="_token"/', $loginPage->body(), $matches);
        }

        $csrfToken = $matches[1] ?? null;
        if (! $csrfToken) {
            if ($this->responseLooksRateLimited($loginPage->body())) {
                $this->throwRateLimited($credentials);
            }

            throw new RuntimeException("CSRF token not found on {$host} login page.");
        }

        $cookies = $this->cookiesToArray($loginPage->cookies()->toArray());

        $loginResponse = $this->browserClient()
            ->withCookies($cookies, $host)
            ->asForm()
            ->withHeaders(['Referer' => $this->url('/login', $host)])
            ->post($this->url('/login', $host), [
                '_token' => $csrfToken,
                'email' => $username,
                'password' => $password,
            ]);

        $responseBody = $loginResponse->body();

        if (
            $loginResponse->status() === 429
            || $this->responseLooksRateLimited($responseBody)
        ) {
            $this->throwRateLimited($credentials);
        }

        if (! $loginResponse->successful() && ! $loginResponse->redirect()) {
            throw new RuntimeException("Login failed on {$host} with status " . $loginResponse->status());
        }

        $sessionCookies = $this->mergeCookies(
            $cookies,
            $this->cookiesToArray($loginResponse->cookies()->toArray())
        );

        if (! isset($sessionCookies['steadfast_courier_session'])) {
            if ($this->responseLooksRateLimited($responseBody) || $this->looksLikeLoginPage($responseBody)) {
                if ($this->responseLooksRateLimited($responseBody)) {
                    $this->throwRateLimited($credentials);
                }
            }

            throw new RuntimeException("Login on {$host} did not create a session cookie.");
        }

        $this->clearLoginCooldown($credentials);
        $this->storeSession($credentials, $host, $sessionCookies);

        return [
            'host' => $host,
            'cookies' => $sessionCookies,
        ];
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    private function assertLoginNotCooldowned(array $credentials): void
    {
        $until = Cache::get($this->loginCooldownKey($credentials));
        if (! is_numeric($until)) {
            return;
        }

        $secondsLeft = max(1, (int) $until - time());
        $minutes = max(1, (int) ceil($secondsLeft / 60));

        throw new RuntimeException(
            "Too many Steadfast login attempts. Please try again in about {$minutes} minute"
            . ($minutes === 1 ? '' : 's')
            . '.'
        );
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    private function throwRateLimited(array $credentials): never
    {
        $this->markLoginCooldowned($credentials);

        throw new RuntimeException(
            'Too many Steadfast login attempts. Please try again in a few minutes.'
        );
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    private function markLoginCooldowned(array $credentials): void
    {
        Cache::put(
            $this->loginCooldownKey($credentials),
            time() + self::LOGIN_COOLDOWN_SECONDS,
            self::LOGIN_COOLDOWN_SECONDS
        );

        // Drop any half-broken session so we do not thrash with expired cookies.
        $this->forgetSession($credentials);

        LogHelper::saveLog(
            'Steadfast portal login rate-limited',
            'cooldown=' . self::LOGIN_COOLDOWN_SECONDS . 's'
        );
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    private function clearLoginCooldown(array $credentials): void
    {
        Cache::forget($this->loginCooldownKey($credentials));
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    private function loginCooldownKey(array $credentials): string
    {
        $username = strtolower(trim((string) ($credentials['username'] ?? '')));
        $password = (string) ($credentials['password'] ?? '');

        return 'steadfast_portal_login_cooldown_' . md5($username . '|' . $password);
    }

    private function responseLooksRateLimited(string $body): bool
    {
        return $this->isRateLimitedMessage($body);
    }

    private function isRateLimitedMessage(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'too many login attempts')
            || str_contains($message, 'too many attempts')
            || (str_contains($message, 'too many') && str_contains($message, 'try again in a few minutes'));
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @param  array<string, string>  $cookies
     */
    private function storeSession(array $credentials, string $host, array $cookies): void
    {
        Cache::put($this->cacheKey($credentials), [
            'host' => $host,
            'cookies' => $cookies,
        ], now()->addMinutes(55));
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     */
    private function cacheKey(array $credentials): string
    {
        return SteadfastFraudChecker::sessionCacheKeyFor($credentials);
    }

    private function browserClient(): PendingRequest
    {
        return Http::timeout(25)
            ->connectTimeout(10)
            ->retry(2, 1200, fn ($exception) => $this->isRetryableError($exception), throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function htmlClient(string $host, array $cookies): PendingRequest
    {
        return $this->browserClient()->withHeaders([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer' => $this->url('/user/consignments', $host),
            'X-XSRF-TOKEN' => urldecode($cookies['XSRF-TOKEN'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function apiClient(string $host, array $cookies): PendingRequest
    {
        return $this->browserClient()->withHeaders([
            'Accept' => 'application/json, text/plain, */*',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => $this->url('/user/consignments', $host),
            'X-XSRF-TOKEN' => urldecode($cookies['XSRF-TOKEN'] ?? ''),
        ]);
    }

    private function url(string $path, string $host): string
    {
        return 'https://' . $host . $path;
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

    /**
     * @param  array<int, array<string, mixed>>  $cookies
     * @return array<string, string>
     */
    private function cookiesToArray(array $cookies): array
    {
        $mapped = [];

        foreach ($cookies as $cookie) {
            $mapped[$cookie['Name']] = $cookie['Value'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, string>  ...$cookieSets
     * @return array<string, string>
     */
    private function mergeCookies(array ...$cookieSets): array
    {
        $merged = [];

        foreach ($cookieSets as $set) {
            $merged = array_merge($merged, $set);
        }

        return $merged;
    }
}
