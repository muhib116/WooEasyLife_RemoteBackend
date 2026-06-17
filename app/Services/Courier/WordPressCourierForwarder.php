<?php

namespace App\Services\Courier;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\CourierShipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressCourierForwarder extends Controller
{
    public function forward(CourierShipment $shipment, array $payload): array
    {
        $accessToken = AccessToken::query()->find($shipment->access_token_id);
        if (!$accessToken || !$accessToken->access_key) {
            return ['success' => false, 'message' => 'missing_access_token'];
        }

        $plainToken = $this->decodeToken($accessToken->access_key);
        if (!$plainToken) {
            return ['success' => false, 'message' => 'invalid_access_token'];
        }

        $candidates = $this->resolveSiteUrlCandidates($shipment, $accessToken);
        if ($candidates === []) {
            return ['success' => false, 'message' => 'missing_site_url'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $body, $plainToken);
        $lastResult = ['success' => false, 'message' => 'forward_failed'];

        foreach ($candidates as $candidate) {
            $endpoint = rtrim($candidate, '/') . '/wp-json/wooeasylife/v1/courier-status/apply';

            try {
                $response = Http::timeout(20)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-WEL-Internal-Token' => $signature,
                    ])
                    ->withBody((string) $body, 'application/json')
                    ->post($endpoint);

                if ($response->successful()) {
                    if ($candidate !== rtrim((string) $shipment->site_url, '/')) {
                        $shipment->site_url = $candidate;
                        $shipment->save();
                    }

                    return [
                        'success' => true,
                        'message' => 'forwarded',
                        'http_status' => $response->status(),
                    ];
                }

                Log::warning('Courier webhook forward failed', [
                    'shipment_id' => $shipment->id,
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
                Log::error('Courier webhook forward exception', [
                    'shipment_id' => $shipment->id,
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

    public function probeReachability(?CourierShipment $shipment, ?AccessToken $accessToken = null): array
    {
        if (!$shipment) {
            return [
                'forward_reachable' => null,
                'forward_probe_status' => 'no_shipments',
                'forward_probe_url' => null,
            ];
        }

        $accessToken = $accessToken ?: AccessToken::query()->find($shipment->access_token_id);
        $candidates = $this->resolveSiteUrlCandidates($shipment, $accessToken);

        if ($candidates === []) {
            return [
                'forward_reachable' => false,
                'forward_probe_status' => 'missing_site_url',
                'forward_probe_url' => null,
            ];
        }

        foreach ($candidates as $candidate) {
            $endpoint = rtrim($candidate, '/') . '/wp-json/wooeasylife/v1/license-status';

            try {
                $response = Http::timeout(8)->get($endpoint);

                if ($response->successful()) {
                    return [
                        'forward_reachable' => true,
                        'forward_probe_status' => 'reachable',
                        'forward_probe_url' => $endpoint,
                    ];
                }
            } catch (\Throwable $exception) {
                Log::info('Courier forward probe failed', [
                    'endpoint' => $endpoint,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'forward_reachable' => false,
            'forward_probe_status' => 'unreachable',
            'forward_probe_url' => rtrim($candidates[0], '/') . '/wp-json/wooeasylife/v1/license-status',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function testPluginReach(CourierShipment $shipment): array
    {
        $accessToken = AccessToken::query()->find($shipment->access_token_id);
        $candidates = $this->resolveSiteUrlCandidates($shipment, $accessToken);

        if ($candidates === []) {
            return [
                'reachable' => false,
                'message' => 'No site URL found for this shipment.',
                'site_url' => null,
                'license_probe' => [
                    'success' => false,
                    'status' => 'missing_site_url',
                    'url' => null,
                    'http_status' => null,
                ],
                'forward_probe' => [
                    'success' => false,
                    'status' => 'skipped',
                    'url' => null,
                    'http_status' => null,
                    'detail' => 'Site URL is required before testing the forward hook.',
                ],
            ];
        }

        $licenseProbe = $this->probeLicenseEndpoint($candidates);
        $forwardProbe = $this->probeForwardEndpoint($shipment, $accessToken, $candidates);

        $reachable = $licenseProbe['success'] || $forwardProbe['success'];
        $message = $reachable
            ? 'Plugin endpoint is reachable.'
            : 'Plugin endpoint could not be reached.';

        if ($licenseProbe['success'] && ! $forwardProbe['success']) {
            $message = 'License endpoint reachable, but courier-status hook failed.';
        } elseif (! $licenseProbe['success'] && $forwardProbe['success']) {
            $message = 'Courier-status hook reachable, but license endpoint failed.';
        }

        return [
            'reachable' => $reachable,
            'message' => $message,
            'site_url' => $licenseProbe['site_url'] ?? $forwardProbe['site_url'] ?? $candidates[0],
            'license_probe' => $licenseProbe,
            'forward_probe' => $forwardProbe,
        ];
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<string, mixed>
     */
    private function probeLicenseEndpoint(array $candidates): array
    {
        foreach ($candidates as $candidate) {
            $endpoint = rtrim($candidate, '/') . '/wp-json/wooeasylife/v1/license-status';

            try {
                $response = Http::timeout(10)->get($endpoint);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'status' => 'reachable',
                        'url' => $endpoint,
                        'http_status' => $response->status(),
                        'site_url' => $candidate,
                        'detail' => 'Plugin license endpoint responded successfully.',
                    ];
                }

                return [
                    'success' => false,
                    'status' => 'http_error',
                    'url' => $endpoint,
                    'http_status' => $response->status(),
                    'site_url' => $candidate,
                    'detail' => 'License endpoint responded with HTTP '.$response->status().'.',
                ];
            } catch (\Throwable $exception) {
                Log::info('Plugin license probe failed', [
                    'endpoint' => $endpoint,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'success' => false,
            'status' => 'unreachable',
            'url' => rtrim($candidates[0], '/') . '/wp-json/wooeasylife/v1/license-status',
            'http_status' => null,
            'site_url' => $candidates[0],
            'detail' => 'Could not connect to the plugin license endpoint.',
        ];
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<string, mixed>
     */
    private function probeForwardEndpoint(
        CourierShipment $shipment,
        ?AccessToken $accessToken,
        array $candidates
    ): array {
        if (! $accessToken?->access_key) {
            return [
                'success' => false,
                'status' => 'missing_access_token',
                'url' => null,
                'http_status' => null,
                'site_url' => $candidates[0] ?? null,
                'detail' => 'Access token is missing for this shipment.',
            ];
        }

        $plainToken = $this->decodeToken($accessToken->access_key);
        if (! $plainToken) {
            return [
                'success' => false,
                'status' => 'invalid_access_token',
                'url' => null,
                'http_status' => null,
                'site_url' => $candidates[0] ?? null,
                'detail' => 'Access token could not be decoded.',
            ];
        }

        $payload = [
            'test_ping' => true,
            'partner' => $shipment->partner,
            'raw_status' => 'connectivity_test',
            'updated_at' => now()->toDateTimeString(),
            'tracking_message' => 'Webhook connectivity test from WooEasyLife hub',
        ];

        if ($shipment->wc_order_id) {
            $payload['order_id'] = (int) $shipment->wc_order_id;
            $payload['courier_account_id'] = (int) $shipment->courier_account_id;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $body, $plainToken);
        $lastResult = [
            'success' => false,
            'status' => 'unreachable',
            'url' => null,
            'http_status' => null,
            'site_url' => $candidates[0] ?? null,
            'detail' => 'Could not connect to the courier-status hook.',
        ];

        foreach ($candidates as $candidate) {
            $endpoint = rtrim($candidate, '/') . '/wp-json/wooeasylife/v1/courier-status/apply';

            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-WEL-Internal-Token' => $signature,
                    ])
                    ->withBody((string) $body, 'application/json')
                    ->post($endpoint);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'status' => 'reachable',
                        'url' => $endpoint,
                        'http_status' => $response->status(),
                        'site_url' => $candidate,
                        'detail' => 'Courier-status hook accepted the signed test request.',
                    ];
                }

                $lastResult = [
                    'success' => false,
                    'status' => 'http_error',
                    'url' => $endpoint,
                    'http_status' => $response->status(),
                    'site_url' => $candidate,
                    'detail' => 'Courier-status hook responded with HTTP '.$response->status().'.',
                ];
            } catch (\Throwable $exception) {
                Log::info('Plugin forward probe failed', [
                    'endpoint' => $endpoint,
                    'message' => $exception->getMessage(),
                ]);

                $lastResult = [
                    'success' => false,
                    'status' => 'connection_error',
                    'url' => $endpoint,
                    'http_status' => null,
                    'site_url' => $candidate,
                    'detail' => $exception->getMessage(),
                ];
            }
        }

        return $lastResult;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSiteUrlCandidates(CourierShipment $shipment, ?AccessToken $accessToken): array
    {
        $candidates = [];
        $stored = rtrim((string) $shipment->site_url, '/');

        if ($stored !== '') {
            $candidates[] = $stored;
        }

        $domain = trim((string) ($accessToken?->domain ?? $shipment->site_domain ?? ''));
        if ($domain !== '') {
            $derived = str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
                ? rtrim($domain, '/')
                : 'https://' . rtrim($domain, '/');

            if (!in_array($derived, $candidates, true)) {
                $candidates[] = $derived;
            }
        }

        return $candidates;
    }
}
