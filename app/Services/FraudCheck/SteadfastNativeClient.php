<?php

namespace App\Services\FraudCheck;

use RuntimeException;

class SteadfastNativeClient
{
    public const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    public const DEFAULT_HOST = 'www.steadfast.com.bd';

    /**
     * @return array<string, string>
     */
    public function login(string $host, string $email, string $password): array
    {
        $cookies = $this->attemptLogin($host, $email, $password);

        if ($cookies === null || !isset($cookies['steadfast_courier_session'])) {
            throw new RuntimeException('Login did not return a Steadfast session cookie');
        }

        return $cookies;
    }

    public function fetchFraudCheckRaw(string $host, array $cookies, string $phone): string
    {
        $url = "https://{$host}/user/frauds/check/{$phone}";

        [$status, $body, $error] = $this->request('GET', $url, [], $this->fraudHeaders($host, $cookies));

        if ($error !== '') {
            throw new RuntimeException($error);
        }

        if ($status < 200 || $status >= 300 || !SteadfastCurlExporter::isValid($body)) {
            throw new RuntimeException("Fraud API HTTP {$status}");
        }

        return $body;
    }

    /**
     * @return array<string, string>|null
     */
    private function attemptLogin(string $host, string $email, string $password): ?array
    {
        $jar = $this->tempCookieJar();

        try {
            [$status, $body, $error] = $this->request('GET', "https://{$host}/login", [], $this->browserHeaders(), $jar);

            if ($error !== '' || $status < 200 || $status >= 400 || $body === '') {
                throw new RuntimeException($error !== '' ? $error : "Login page HTTP {$status}");
            }

            if (!preg_match('/name="_token" value="([^"]+)"/', $body, $matches)) {
                throw new RuntimeException('CSRF token not found on login page');
            }

            [$status, , $error] = $this->request(
                'POST',
                "https://{$host}/login",
                [
                    '_token' => $matches[1],
                    'email' => $email,
                    'password' => $password,
                ],
                array_merge($this->browserHeaders(), [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Origin: https://' . $host,
                    'Referer: https://' . $host . '/login',
                ]),
                $jar,
            );

            if ($error !== '') {
                throw new RuntimeException($error);
            }

            if ($status >= 400 && $status !== 302) {
                throw new RuntimeException("Login failed HTTP {$status}");
            }

            $cookies = $this->readCookieJar($jar);

            return isset($cookies['steadfast_courier_session']) ? $cookies : null;
        } finally {
            @unlink($jar);
        }
    }

    /**
     * @param  array<string, string>  $form
     * @param  list<string>  $headers
     * @return array{0: int, 1: string, 2: string}
     */
    private function request(
        string $method,
        string $url,
        array $form,
        array $headers,
        ?string $cookieJar = null,
    ): array {
        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($cookieJar !== null) {
            $options[CURLOPT_COOKIEJAR] = $cookieJar;
            $options[CURLOPT_COOKIEFILE] = $cookieJar;
        }

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($form);
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [$status, is_string($body) ? $body : '', $error];
    }

    /**
     * @param  array<string, string>  $cookies
     * @return list<string>
     */
    private function fraudHeaders(string $host, array $cookies): array
    {
        $cookieHeader = $this->buildCookieHeader($cookies);

        return array_merge($this->browserHeaders(), [
            "Cookie: {$cookieHeader}",
            'Accept: application/json, text/plain, */*',
            'Referer: https://' . $host . '/user/frauds/check',
            'X-Requested-With: XMLHttpRequest',
            'X-XSRF-TOKEN: ' . urldecode($cookies['XSRF-TOKEN'] ?? ''),
        ]);
    }

    /**
     * @return list<string>
     */
    private function browserHeaders(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ];
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function buildCookieHeader(array $cookies): string
    {
        $parts = [];

        foreach ($cookies as $name => $value) {
            $parts[] = "{$name}={$value}";
        }

        return implode('; ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function readCookieJar(string $jarPath): array
    {
        $contents = @file_get_contents($jarPath);

        if (!is_string($contents) || $contents === '') {
            return [];
        }

        $cookies = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 7) {
                continue;
            }

            $cookies[$parts[5]] = $parts[6];
        }

        return $cookies;
    }

    private function tempCookieJar(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'steadfast_cookies_');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary cookie jar');
        }

        file_put_contents($path, "# Netscape HTTP Cookie File\n");

        return $path;
    }
}
