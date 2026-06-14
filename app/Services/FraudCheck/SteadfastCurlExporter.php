<?php

namespace App\Services\FraudCheck;

class SteadfastCurlExporter
{
    public static function path(): string
    {
        return app_path('Http/Controllers/curlcode.txt');
    }

    public static function save(string $host, array $cookies, string $phone = '01770989591'): void
    {
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
        $cookieString = implode('; ', array_map(
            fn (string $name, string $value) => "{$name}={$value}",
            array_keys($cookies),
            array_values($cookies),
        ));
        $xsrfToken = urldecode($cookies['XSRF-TOKEN'] ?? '');

        $curl = <<<CURL
curl -s 'https://{$host}/user/frauds/check/{$phone}' \\
  -H 'accept: application/json, text/plain, */*' \\
  -H 'accept-language: en-US,en;q=0.9' \\
  -b '{$cookieString}' \\
  -H 'referer: https://{$host}/user/frauds/check' \\
  -H 'user-agent: {$userAgent}' \\
  -H 'x-requested-with: XMLHttpRequest' \\
  -H 'x-xsrf-token: {$xsrfToken}'
CURL;

        file_put_contents(self::path(), $curl);
    }

    public static function isValid(?string $output): bool
    {
        if (empty($output)) {
            return false;
        }

        $payload = json_decode($output, true);

        return is_array($payload)
            && (isset($payload['total_delivered']) || isset($payload['frauds']));
    }

    public static function run(string $phone, ?string $sourcePath = null): string
    {
        if (!function_exists('shell_exec')) {
            return '';
        }

        $path = $sourcePath ?? self::path();

        if (!is_file($path)) {
            return '';
        }

        $curlString = file_get_contents($path);
        $curlString = preg_replace(
            '#https://(?:www\.)?steadfast\.com\.bd/user/frauds/check/\d+#',
            'https://www.steadfast.com.bd/user/frauds/check/' . $phone,
            (string) $curlString
        );

        if (!preg_match('/\s\-s\b/', $curlString)) {
            $curlString = preg_replace('/^curl\s/', 'curl -s ', $curlString);
        }

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
}
