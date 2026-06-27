<?php

namespace App\Services;

use App\Models\CourierConfiguration;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class RedXCourierService
{
    public function getConfig(int $userId): ?CourierConfiguration
    {
        if ($userId <= 0) {
            return null;
        }

        $config = CourierConfiguration::where('user_id', $userId)
            ->where('slug', 'redx')
            ->first();

        if (!$config || !$this->hasAccessToken($config)) {
            return null;
        }

        $config->settings = $this->normalizeSettings($config->settings);

        return $config;
    }

    public function getAuthConfig(int $userId, array $override = []): ?CourierConfiguration
    {
        $config = null;

        if (!empty($override['courier_config_id'])) {
            $query = CourierConfiguration::query()
                ->where('id', (int) $override['courier_config_id'])
                ->where('slug', 'redx');

            if ($userId > 0) {
                $query->where('user_id', $userId);
            }

            $config = $query->first();
        }

        if (!$config && $userId > 0) {
            $config = CourierConfiguration::where('user_id', $userId)
                ->where('slug', 'redx')
                ->first();
        }

        if (!empty($override['secret_key'])) {
            if (!$config) {
                $config = new CourierConfiguration([
                    'user_id' => $userId,
                    'slug' => 'redx',
                ]);
            }

            $config->secret_key = trim((string) $override['secret_key']);

            if (!empty($override['api_key'])) {
                $config->api_key = trim((string) $override['api_key']);
            } elseif (!$config->api_key) {
                $config->api_key = 'redx';
            }
        }

        if ($config && !empty($override['environment'])) {
            $settings = $this->normalizeSettings($config->settings);
            $settings['environment'] = $override['environment'] === 'live' ? 'live' : 'sandbox';
            $config->settings = $settings;
        }

        if (!$config || !$this->hasAccessToken($config)) {
            return null;
        }

        $config->settings = $this->normalizeSettings($config->settings);

        return $config;
    }

    public function getBaseUrl(?CourierConfiguration $config = null): string
    {
        $settings = $this->normalizeSettings($config?->settings);

        if (($settings['environment'] ?? 'sandbox') === 'live') {
            return rtrim(env('REDX_LIVE_BASE_URL', 'https://openapi.redx.com.bd'), '/');
        }

        return rtrim(env('REDX_SANDBOX_BASE_URL', 'https://sandbox.redx.com.bd'), '/');
    }

    public function getApiPrefix(): string
    {
        return '/v1.0.0-beta';
    }

    public function normalizeSettings($settings): array
    {
        $settings = is_array($settings) ? $settings : [];

        return [
            'environment' => ($settings['environment'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox',
            'pickup_store_id' => $this->nullableInt($settings['pickup_store_id'] ?? null),
            'delivery_area_id' => $this->nullableInt($settings['delivery_area_id'] ?? null),
            'delivery_area' => trim((string) ($settings['delivery_area'] ?? '')),
            'parcel_weight' => max(500, (int) ($settings['parcel_weight'] ?? 500)),
        ];
    }

    public function testConnection(CourierConfiguration $config): array
    {
        try {
            $response = $this->client($config)->get($this->endpoint($config, '/areas'));
            $json = $response->json();

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => 'RedX API connection successful.',
                    'data' => $json,
                ];
            }

            return [
                'ok' => false,
                'message' => $this->parseApiError($json, $response->body()),
            ];
        } catch (\Throwable $th) {
            return [
                'ok' => false,
                'message' => 'Could not reach RedX API: ' . $th->getMessage(),
            ];
        }
    }

    public function getAreas(CourierConfiguration $config): array
    {
        $response = $this->client($config)->get($this->endpoint($config, '/areas'));

        if (!$response->successful()) {
            throw new \RuntimeException($this->parseApiError($response->json(), $response->body()));
        }

        return $response->json() ?? [];
    }

    public function getPickupStores(CourierConfiguration $config): array
    {
        $response = $this->client($config)->get($this->endpoint($config, '/pickup/stores'));

        if (!$response->successful()) {
            throw new \RuntimeException($this->parseApiError($response->json(), $response->body()));
        }

        return $response->json() ?? [];
    }

    public function calculateCharge(CourierConfiguration $config, array $params): array
    {
        $response = $this->client($config)->get($this->endpoint($config, '/charge/charge_calculator'), [
            'delivery_area_id' => $params['delivery_area_id'] ?? null,
            'pickup_area_id' => $params['pickup_area_id'] ?? null,
            'weight' => $params['weight'] ?? null,
            'cash_collection_amount' => $params['cash_collection_amount'] ?? null,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($this->parseApiError($response->json(), $response->body()));
        }

        return $response->json() ?? [];
    }

    public function createOrder(CourierConfiguration $config, array $order, array $options = []): array
    {
        $settings = $this->normalizeSettings($config->settings);

        $deliveryAreaId = (int) ($order['delivery_area_id'] ?? $options['delivery_area_id'] ?? $settings['delivery_area_id'] ?? 0);
        $deliveryArea = (string) ($order['delivery_area'] ?? $options['delivery_area'] ?? $settings['delivery_area'] ?? '');

        if ($deliveryAreaId <= 0) {
            return $this->failedOrderRow($order, 'RedX delivery area is required.');
        }

        if ($deliveryArea === '') {
            $deliveryArea = 'Area #' . $deliveryAreaId;
        }

        $pickupStoreId = $order['pickup_store_id'] ?? $options['pickup_store_id'] ?? $settings['pickup_store_id'] ?? null;
        $weightGrams = $this->normalizeWeightGrams(
            $order['parcel_weight'] ?? $order['item_weight'] ?? $options['parcel_weight'] ?? $settings['parcel_weight']
        );

        $payload = [
            'customer_name' => (string) ($order['recipient_name'] ?? ''),
            'customer_phone' => $this->normalizePhone($order['recipient_phone'] ?? ''),
            'delivery_area' => $deliveryArea,
            'delivery_area_id' => $deliveryAreaId,
            'customer_address' => (string) ($order['recipient_address'] ?? ''),
            'merchant_invoice_id' => (string) ($order['invoice'] ?? ''),
            'cash_collection_amount' => (int) round($order['cod_amount'] ?? 0),
            'parcel_weight' => $weightGrams,
            'instruction' => $order['note'] ?? null,
            'value' => (int) round($order['value'] ?? $order['cod_amount'] ?? 0),
        ];

        if (!empty($pickupStoreId)) {
            $payload['pickup_store_id'] = (int) $pickupStoreId;
        }

        if (!empty($order['parcel_details_json']) && is_array($order['parcel_details_json'])) {
            $payload['parcel_details_json'] = $order['parcel_details_json'];
        }

        try {
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $response = $this->client($config)->post($this->endpoint($config, '/parcel'), $payload);
                    $json = $response->json();

                    $trackingId = $json['tracking_id']
                        ?? $json['data']['tracking_id']
                        ?? $json['parcel']['tracking_id']
                        ?? null;

                    if ($response->successful() && !empty($trackingId)) {
                        return $this->successOrderRow($order, (string) $trackingId, $json);
                    }

                    return $this->failedOrderRow($order, $this->parseApiError($json, $response->body()));
                } catch (\Throwable $th) {
                    if ($attempt >= 3 || !$this->isRetryableNetworkError($th->getMessage())) {
                        return $this->failedOrderRow($order, 'RedX order creation failed: ' . $th->getMessage());
                    }

                    usleep(250000 * $attempt);
                }
            }
        } catch (\Throwable $th) {
            return $this->failedOrderRow($order, 'RedX order creation failed: ' . $th->getMessage());
        }

        return $this->failedOrderRow($order, 'RedX order creation failed.');
    }

    public function getTrackingStatuses(CourierConfiguration $config, array $trackingIds): array
    {
        $ids = $this->normalizeTrackingIds($trackingIds);
        if ($ids === []) {
            return [];
        }

        $limit = max(1, min(50, (int) config('courier.bulk_status_concurrency', 15)));
        $statuses = [];

        foreach (array_chunk($ids, $limit) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $config) {
                foreach ($chunk as $trackingId) {
                    $pool->as($trackingId)
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'API-ACCESS-TOKEN' => 'Bearer ' . $this->getAccessToken($config),
                        ])
                        ->timeout(12)
                        ->get($this->endpoint($config, '/parcel/info/' . rawurlencode($trackingId)));
                }
            });

            foreach ($chunk as $trackingId) {
                $statuses[$trackingId] = $this->extractLatestTrackingStatusFromResponse(
                    $responses[$trackingId] ?? null,
                );
            }
        }

        return $statuses;
    }

    public function getParcelTrackingHistory(CourierConfiguration $config, string $trackingId): array
    {
        $id = trim($trackingId);

        if ($id === '') {
            return [
                'tracking_id' => '',
                'tracking' => [],
                'error' => 'Tracking ID is required.',
            ];
        }

        try {
            $response = $this->client($config)->get($this->endpoint($config, '/parcel/track/' . rawurlencode($id)));

            if (!$response->successful()) {
                return [
                    'tracking_id' => $id,
                    'tracking' => [],
                    'error' => $this->parseApiError($response->json(), $response->body()),
                ];
            }

            $json = $response->json();
            $tracking = $json['tracking'] ?? $json['data']['tracking'] ?? null;

            if (!is_array($tracking)) {
                $tracking = [];
            }

            return [
                'tracking_id' => $id,
                'tracking' => $this->normalizeTrackingEvents($tracking),
                'error' => null,
            ];
        } catch (\Throwable $th) {
            return [
                'tracking_id' => $id,
                'tracking' => [],
                'error' => 'Could not load RedX tracking: ' . $th->getMessage(),
            ];
        }
    }

    public function getParcelInfo(CourierConfiguration $config, string $trackingId): ?array
    {
        $id = trim($trackingId);

        if ($id === '') {
            return null;
        }

        try {
            $response = $this->client($config)->get($this->endpoint($config, '/parcel/info/' . rawurlencode($id)));

            if (!$response->successful()) {
                return null;
            }

            $json = $response->json();
            $parcel = $json['parcel'] ?? $json['data']['parcel'] ?? null;

            return is_array($parcel) ? $parcel : null;
        } catch (\Throwable $th) {
            return null;
        }
    }

    public function getLatestTrackingStatus(CourierConfiguration $config, string $trackingId): string
    {
        $parcel = $this->getParcelInfo($config, $trackingId);

        return $this->extractLatestTrackingStatusFromParcel($parcel);
    }

    private function extractLatestTrackingStatusFromResponse(mixed $response): string
    {
        if ($response === null) {
            return '';
        }

        try {
            if (method_exists($response, 'successful') && !$response->successful()) {
                return '';
            }

            $json = $response->json();
            $parcel = $json['parcel'] ?? $json['data']['parcel'] ?? null;

            return $this->extractLatestTrackingStatusFromParcel(
                is_array($parcel) ? $parcel : null,
            );
        } catch (\Throwable $th) {
            return '';
        }
    }

    private function extractLatestTrackingStatusFromParcel(?array $parcel): string
    {
        if (!is_array($parcel)) {
            return '';
        }

        foreach (['status', 'delivery_status', 'current_status', 'order_status'] as $field) {
            if (!empty($parcel[$field])) {
                return (string) $parcel[$field];
            }
        }

        return '';
    }

    /**
     * @param  array<int|string>  $trackingIds
     * @return array<int, string>
     */
    private function normalizeTrackingIds(array $trackingIds): array
    {
        $normalized = [];

        foreach ($trackingIds as $trackingId) {
            $id = trim((string) $trackingId);
            if ($id !== '') {
                $normalized[] = $id;
            }
        }

        return $normalized;
    }

    private function normalizeTrackingEvents(array $events): array
    {
        $normalized = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $messageEn = trim((string) ($event['message_en'] ?? $event['message'] ?? ''));
            $messageBn = trim((string) ($event['message_bn'] ?? ''));
            $time = trim((string) ($event['time'] ?? $event['timestamp'] ?? ''));

            if ($messageEn === '' && $messageBn === '') {
                continue;
            }

            $normalized[] = [
                'message_en' => $messageEn,
                'message_bn' => $messageBn,
                'time' => $time,
            ];
        }

        return $normalized;
    }

    private function isRetryableNetworkError(string $message): bool
    {
        return str_contains($message, 'cURL error 56')
            || str_contains($message, 'cURL error 52')
            || str_contains($message, 'cURL error 28')
            || str_contains($message, 'Connection reset by peer')
            || str_contains($message, 'Broken pipe')
            || str_contains($message, 'Operation timed out');
    }

    private function client(CourierConfiguration $config)
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'API-ACCESS-TOKEN' => 'Bearer ' . $this->getAccessToken($config),
        ])->timeout(45);
    }

    private function endpoint(CourierConfiguration $config, string $path): string
    {
        return $this->getBaseUrl($config) . $this->getApiPrefix() . $path;
    }

    private function hasAccessToken(CourierConfiguration $config): bool
    {
        return trim((string) ($config->secret_key ?? '')) !== '';
    }

    private function getAccessToken(CourierConfiguration $config): string
    {
        return trim((string) ($config->secret_key ?? ''));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            $digits = '0' . substr($digits, 3);
        }

        return $digits;
    }

    private function normalizeWeightGrams($value): int
    {
        $parsed = (float) $value;

        if (!is_finite($parsed) || $parsed <= 0) {
            return 500;
        }

        // Values below 50 are treated as kilograms.
        if ($parsed < 50) {
            $parsed *= 1000;
        }

        return max(500, (int) round($parsed));
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }

    private function parseApiError($json, string $body = ''): string
    {
        if (is_array($json)) {
            foreach (['message', 'error', 'error_message', 'detail'] as $key) {
                if (!empty($json[$key]) && is_string($json[$key])) {
                    return $json[$key];
                }
            }

            if (!empty($json['errors']) && is_array($json['errors'])) {
                $messages = [];

                foreach ($json['errors'] as $field => $error) {
                    if (is_array($error)) {
                        $messages[] = implode(' ', $error);
                    } elseif (is_string($error)) {
                        $messages[] = $error;
                    } else {
                        $messages[] = (string) $field;
                    }
                }

                if ($messages) {
                    return implode(' ', $messages);
                }
            }
        }

        $body = trim($body);

        return $body !== '' ? mb_substr($body, 0, 500) : 'RedX API request failed.';
    }

    private function successOrderRow(array $order, string $trackingId, array $json = []): array
    {
        $now = now()->toDateTimeString();

        return [
            'invoice' => $order['invoice'] ?? '',
            'recipient_name' => $order['recipient_name'] ?? '',
            'recipient_phone' => $order['recipient_phone'] ?? '',
            'recipient_address' => $order['recipient_address'] ?? '',
            'cod_amount' => $order['cod_amount'] ?? 0,
            'note' => $order['note'] ?? null,
            'consignment_id' => $trackingId,
            'tracking_code' => $trackingId,
            'status' => (string) ($json['status'] ?? $json['data']['status'] ?? 'pending'),
            'error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function failedOrderRow(array $order, string $message): array
    {
        $now = now()->toDateTimeString();

        return [
            'invoice' => $order['invoice'] ?? '',
            'recipient_name' => $order['recipient_name'] ?? '',
            'recipient_phone' => $order['recipient_phone'] ?? '',
            'recipient_address' => $order['recipient_address'] ?? '',
            'cod_amount' => $order['cod_amount'] ?? 0,
            'note' => $order['note'] ?? null,
            'consignment_id' => null,
            'tracking_code' => null,
            'status' => null,
            'error' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
