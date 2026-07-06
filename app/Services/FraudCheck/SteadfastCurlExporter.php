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

        if (! is_array($payload)) {
            return false;
        }

        if (isset($payload['message'], $payload['exception']) || isset($payload['error'])) {
            return false;
        }

        $deliveryFields = [
            'total_delivered',
            'delivered',
            'total_cancelled',
            'cancelled',
            'cancel',
            'total_order',
            'total',
            'frauds',
        ];

        foreach ($deliveryFields as $field) {
            if (array_key_exists($field, $payload)) {
                return true;
            }
        }

        return false;
    }
}
