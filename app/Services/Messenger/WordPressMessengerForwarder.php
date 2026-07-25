<?php

namespace App\Services\Messenger;

use App\Models\AccessToken;
use App\Models\MessengerPageConnection;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressMessengerForwarder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function notifyPageConnected(MessengerPageConnection $connection, array $payload = []): array
    {
        $body = array_merge([
            'page_id' => $connection->page_id,
            'page_name' => $connection->page_name,
            'page_picture' => $connection->page_picture,
            'meta' => [
                'connected_at' => optional($connection->connected_at)->toDateTimeString(),
            ],
        ], $payload);

        return $this->forward($connection, $body, 'messenger/page-connected');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function forwardInbound(MessengerPageConnection $connection, array $payload): array
    {
        return $this->forward($connection, $payload, 'messenger/inbound');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function forward(MessengerPageConnection $connection, array $payload, string $route): array
    {
        $accessToken = AccessToken::query()->find($connection->access_token_id);
        if (! $accessToken || ! $accessToken->access_key) {
            return ['success' => false, 'message' => 'missing_access_token'];
        }

        $plainToken = decodeToken($accessToken->access_key);
        if (! $plainToken) {
            return ['success' => false, 'message' => 'invalid_access_token'];
        }

        $candidates = $this->resolveSiteUrlCandidates($connection, $accessToken);
        if ($candidates === []) {
            return ['success' => false, 'message' => 'missing_site_url'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $body, $plainToken);
        $lastResult = ['success' => false, 'message' => 'forward_failed'];

        foreach ($candidates as $candidate) {
            $endpoint = rtrim($candidate, '/') . '/wp-json/wooeasylife/v1/' . ltrim($route, '/');

            try {
                $response = Http::timeout(20)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-WEL-Internal-Token' => $signature,
                    ])
                    ->withBody((string) $body, 'application/json')
                    ->post($endpoint);

                if ($response->successful()) {
                    if ($candidate !== rtrim((string) $connection->site_url, '/')) {
                        $connection->site_url = $candidate;
                        $connection->save();
                    }

                    return [
                        'success' => true,
                        'message' => 'forwarded',
                        'http_status' => $response->status(),
                        'site_url' => $candidate,
                    ];
                }

                Log::warning('Messenger WordPress forward failed', [
                    'connection_id' => $connection->id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $lastResult = [
                    'success' => false,
                    'message' => 'forward_failed',
                    'http_status' => $response->status(),
                ];
            } catch (\Throwable $exception) {
                Log::error('Messenger WordPress forward exception', [
                    'connection_id' => $connection->id,
                    'endpoint' => $endpoint,
                    'message' => $exception->getMessage(),
                ]);

                $lastResult = [
                    'success' => false,
                    'message' => 'forward_exception',
                ];
            }
        }

        return $lastResult;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSiteUrlCandidates(MessengerPageConnection $connection, AccessToken $accessToken): array
    {
        $candidates = [];

        $stored = rtrim((string) $connection->site_url, '/');
        if ($stored !== '') {
            $candidates[] = $stored;
        }

        if ($accessToken->website_id) {
            $website = Website::query()->find($accessToken->website_id);
            $baseUrl = rtrim((string) ($website?->base_url ?? ''), '/');
            if ($baseUrl !== '' && ! in_array($baseUrl, $candidates, true)) {
                $candidates[] = $baseUrl;
            }
        }

        $domain = trim((string) ($accessToken->domain ?? ''));
        if ($domain !== '') {
            $derived = str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
                ? rtrim($domain, '/')
                : 'https://' . rtrim($domain, '/');

            if (! in_array($derived, $candidates, true)) {
                $candidates[] = $derived;
            }
        }

        return $candidates;
    }
}
