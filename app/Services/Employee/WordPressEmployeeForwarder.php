<?php

namespace App\Services\Employee;

use App\Models\AccessToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressEmployeeForwarder
{
    /**
     * @param  array<int, string>  $siteUrls
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sync(array $siteUrls, AccessToken $accessToken, array $payload): array
    {
        return $this->forward($siteUrls, $accessToken, $payload, 'sync-wp-user');
    }

    /**
     * @param  array<int, string>  $siteUrls
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function delete(array $siteUrls, AccessToken $accessToken, array $payload): array
    {
        return $this->forward($siteUrls, $accessToken, $payload, 'delete-wp-user');
    }

    /**
     * @param  array<int, string>  $siteUrls
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateEmail(array $siteUrls, AccessToken $accessToken, array $payload): array
    {
        return $this->forward($siteUrls, $accessToken, $payload, 'validate-wp-user-email');
    }

    /**
     * @param  array<int, string>  $siteUrls
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function forward(
        array $siteUrls,
        AccessToken $accessToken,
        array $payload,
        string $route
    ): array {
        if ($siteUrls === []) {
            return [
                'success' => false,
                'message' => 'missing_site_url',
            ];
        }

        if (! $accessToken->access_key) {
            return [
                'success' => false,
                'message' => 'missing_access_token',
            ];
        }

        $plainToken = decodeToken($accessToken->access_key);

        if (! $plainToken) {
            return [
                'success' => false,
                'message' => 'invalid_access_token',
            ];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $body, $plainToken);
        $lastResult = [
            'success' => false,
            'message' => 'forward_failed',
        ];

        foreach ($siteUrls as $candidate) {
            $endpoint = rtrim($candidate, '/') . '/wp-json/wooeasylife/v1/employees/' . $route;

            try {
                $response = Http::timeout(20)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-WEL-Internal-Token' => $signature,
                    ])
                    ->withBody((string) $body, 'application/json')
                    ->post($endpoint);

                $parsed = $this->parseResponse($response);

                if ($parsed !== null) {
                    return $parsed;
                }

                Log::warning('Employee WordPress forward returned an unexpected response', [
                    'endpoint' => $endpoint,
                    'route' => $route,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $lastResult = [
                    'success' => false,
                    'message' => $this->summarizeUnexpectedResponse($response),
                    'http_status' => $response->status(),
                    'data' => [
                        'response_excerpt' => $this->responseExcerpt($response->body()),
                    ],
                ];
            } catch (\Throwable $exception) {
                Log::error('Employee WordPress forward exception', [
                    'endpoint' => $endpoint,
                    'route' => $route,
                    'message' => $exception->getMessage(),
                ]);

                $lastResult = [
                    'success' => false,
                    'message' => 'forward_exception',
                    'http_status' => null,
                    'data' => [
                        'exception' => $exception->getMessage(),
                    ],
                ];
            }
        }

        return $lastResult;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseResponse(\Illuminate\Http\Client\Response $response): ?array
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            return null;
        }

        if (array_key_exists('status', $decoded)) {
            return [
                'success' => (bool) $decoded['status'],
                'message' => (string) ($decoded['message'] ?? ''),
                'http_status' => $response->status(),
                'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            ];
        }

        if (isset($decoded['code'])) {
            return [
                'success' => false,
                'message' => (string) ($decoded['message'] ?? $decoded['code']),
                'http_status' => $response->status(),
                'data' => [
                    'code' => (string) $decoded['code'],
                ],
            ];
        }

        return null;
    }

    private function summarizeUnexpectedResponse(\Illuminate\Http\Client\Response $response): string
    {
        $status = $response->status();

        if ($status === 401) {
            return 'WordPress rejected the hub signature (HTTP 401). Reconnect the plugin license on this store.';
        }

        if ($status === 404) {
            return 'Employee sync endpoint not found (HTTP 404). Update or activate WooEasyLife on this store.';
        }

        if ($status >= 500) {
            return "WordPress server error (HTTP {$status}).";
        }

        return $status > 0
            ? "Unexpected WordPress response (HTTP {$status})."
            : 'forward_failed';
    }

    private function responseExcerpt(string $body): string
    {
        $body = trim($body);

        if ($body === '') {
            return '';
        }

        return mb_substr($body, 0, 300);
    }
}
