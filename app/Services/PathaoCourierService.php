<?php

namespace App\Services;

use App\Models\CourierConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PathaoCourierService
{
    const DELIVERY_TYPE_NORMAL = 48;
    const DELIVERY_TYPE_DEMAND = 12;
    const ITEM_TYPE_DOCUMENT = 1;
    const ITEM_TYPE_PARCEL = 2;

    public function getConfig(int $userId): ?CourierConfiguration
    {
        $config = $this->getAuthConfig($userId);

        if (!$config) {
            return null;
        }

        $settings = $this->normalizeSettings($config->settings);

        if (
            empty($settings['store_id'])
            || empty($settings['sender_name'])
            || empty($settings['sender_phone'])
            || empty($settings['recipient_city'])
            || empty($settings['recipient_zone'])
            || empty($settings['recipient_area'])
        ) {
            return null;
        }

        $config->settings = $settings;

        return $config;
    }

    public function getAuthConfig(int $userId, array $override = []): ?CourierConfiguration
    {
        $config = null;

        if ($userId > 0) {
            $config = CourierConfiguration::where('user_id', $userId)
                ->where('slug', 'pathao')
                ->first();
        }

        if (!empty($override['api_key'])) {
            if (!$config) {
                $config = new CourierConfiguration([
                    'user_id' => $userId,
                    'slug' => 'pathao',
                ]);
            }

            $config->api_key = trim((string) $override['api_key']);
            $config->secret_key = trim((string) ($override['secret_key'] ?? $config->secret_key ?? ''));

            $settings = $this->normalizeSettings($config->settings);

            if (!empty($override['username'])) {
                $settings['username'] = trim((string) $override['username']);
            }

            if (!empty($override['password'])) {
                $settings['password'] = (string) $override['password'];
            }

            if (!empty($override['environment'])) {
                $settings['environment'] = $override['environment'] === 'live' ? 'live' : 'sandbox';
            }

            $config->settings = $settings;
        }

        if (!$config || !$config->api_key || !$config->secret_key) {
            return null;
        }

        $settings = $this->normalizeSettings($config->settings);

        if (empty($settings['username']) || empty($settings['password'])) {
            return null;
        }

        $config->settings = $settings;

        return $config;
    }

    public function getBaseUrl(): string
    {
        return rtrim(env('PATHAO_BASE_URL', 'https://courier-api-sandbox.pathao.com'), '/');
    }

    public function getAccessToken(CourierConfiguration $config): ?string
    {
        if (!$config->exists) {
            $cachedToken = $this->getCachedAccessToken($config);

            if ($cachedToken) {
                return $cachedToken;
            }
        }

        $settings = $this->normalizeSettings($config->settings);
        $expiresAt = (int) ($settings['expires_at'] ?? 0);

        if (!empty($settings['access_token']) && $expiresAt > (time() + 120)) {
            return $settings['access_token'];
        }

        if (!empty($settings['refresh_token'])) {
            $token = $this->requestToken($config, 'refresh_token', [
                'refresh_token' => $settings['refresh_token'],
            ]);

            if ($token) {
                return $token;
            }
        }

        return $this->requestToken($config, 'password', [
            'username' => $settings['username'],
            'password' => $settings['password'],
        ]);
    }

    public function createOrder(CourierConfiguration $config, array $order): array
    {
        $settings = $this->normalizeSettings($config->settings);
        $token = $this->getAccessToken($config);

        if (!$token) {
            return $this->failedOrderRow($order, 'Unable to authenticate with Pathao. Check Client ID, Secret, login email, and password.');
        }

        $payload = [
            'store_id' => (int) $settings['store_id'],
            'merchant_order_id' => (string) ($order['invoice'] ?? ''),
            'sender_name' => (string) $settings['sender_name'],
            'sender_phone' => $this->normalizePhone($settings['sender_phone']),
            'recipient_name' => (string) ($order['recipient_name'] ?? ''),
            'recipient_phone' => $this->normalizePhone($order['recipient_phone'] ?? ''),
            'recipient_address' => (string) ($order['recipient_address'] ?? ''),
            'recipient_city' => (int) ($order['recipient_city'] ?? $settings['recipient_city']),
            'recipient_zone' => (int) ($order['recipient_zone'] ?? $settings['recipient_zone']),
            'recipient_area' => (int) ($order['recipient_area'] ?? $settings['recipient_area']),
            'delivery_type' => (int) ($settings['delivery_type'] ?? self::DELIVERY_TYPE_NORMAL),
            'item_type' => (int) ($settings['item_type'] ?? self::ITEM_TYPE_PARCEL),
            'item_quantity' => (int) ($settings['item_quantity'] ?? 1),
            'item_weight' => (float) ($settings['item_weight'] ?? 0.5),
            'amount_to_collect' => (int) round($order['cod_amount'] ?? 0),
            'special_instruction' => $order['note'] ?? null,
            'item_description' => $order['note'] ?? null,
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->post($this->getBaseUrl() . '/aladdin/api/v1/orders', $payload);

            $json = $response->json();

            if ($response->successful() && ($json['code'] ?? null) == 200 && !empty($json['data']['consignment_id'])) {
                return $this->successOrderRow($order, $json['data']);
            }

            return $this->failedOrderRow($order, $this->parseApiError($json, $response->body()));
        } catch (\Throwable $th) {
            return $this->failedOrderRow($order, $th->getMessage());
        }
    }

    public function getOrderStatus(CourierConfiguration $config, string $consignmentId): string
    {
        $token = $this->getAccessToken($config);

        if (!$token) {
            return '';
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get($this->getBaseUrl() . '/aladdin/api/v1/orders/' . $consignmentId . '/info');

            $json = $response->json();

            if ($response->successful() && ($json['code'] ?? null) == 200) {
                $status = $json['data']['order_status_slug'] ?? $json['data']['order_status'] ?? '';

                return is_string($status) ? strtolower($status) : '';
            }
        } catch (\Throwable $th) {
            return '';
        }

        return '';
    }

    public function getStores(CourierConfiguration $config): array
    {
        $token = $this->getAccessToken($config);

        if (!$token) {
            return [];
        }

        $stores = [];
        $page = 1;
        $lastPage = 1;

        try {
            do {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get($this->getBaseUrl() . '/aladdin/api/v1/stores', [
                    'page' => $page,
                ]);

                $json = $response->json();

                if (!$response->successful() || ($json['code'] ?? null) != 200) {
                    break;
                }

                $pageData = $json['data']['data'] ?? [];
                $stores = array_merge($stores, is_array($pageData) ? $pageData : []);

                $lastPage = (int) ($json['data']['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage && $page <= 5);
        } catch (\Throwable $th) {
            return $stores;
        }

        return $stores;
    }

    public function getCities(CourierConfiguration $config): array
    {
        return $this->authorizedGet($config, '/aladdin/api/v1/countries/1/city-list');
    }

    public function getZones(CourierConfiguration $config, int $cityId): array
    {
        return $this->authorizedGet($config, '/aladdin/api/v1/cities/' . $cityId . '/zone-list');
    }

    public function getAreas(CourierConfiguration $config, int $zoneId): array
    {
        return $this->authorizedGet($config, '/aladdin/api/v1/zones/' . $zoneId . '/area-list');
    }

    public function createStore(CourierConfiguration $config, array $storeData): array
    {
        $token = $this->getAccessToken($config);

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Unable to authenticate with Pathao.',
                'data' => null,
            ];
        }

        $payload = [
            'name' => (string) ($storeData['name'] ?? ''),
            'contact_name' => (string) ($storeData['contact_name'] ?? ''),
            'contact_number' => $this->normalizePhone($storeData['contact_number'] ?? ''),
            'address' => (string) ($storeData['address'] ?? ''),
            'city_id' => (int) ($storeData['city_id'] ?? 0),
            'zone_id' => (int) ($storeData['zone_id'] ?? 0),
            'area_id' => (int) ($storeData['area_id'] ?? 0),
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->post($this->getBaseUrl() . '/aladdin/api/v1/stores', $payload);

            $json = $response->json();

            if ($response->successful() && ($json['code'] ?? null) == 200) {
                return [
                    'success' => true,
                    'message' => $json['message'] ?? 'Store created successfully.',
                    'data' => $json['data'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $this->parseApiError($json, $response->body()),
                'data' => null,
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage(),
                'data' => null,
            ];
        }
    }

    public function calculatePrice(CourierConfiguration $config, array $input): array
    {
        $settings = $this->normalizeSettings($config->settings);
        $token = $this->getAccessToken($config);

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Unable to authenticate with Pathao.',
                'data' => null,
            ];
        }

        $payload = [
            'store_id' => (int) ($input['store_id'] ?? $settings['store_id']),
            'delivery_type' => (int) ($input['delivery_type'] ?? $settings['delivery_type'] ?? self::DELIVERY_TYPE_NORMAL),
            'item_type' => (int) ($input['item_type'] ?? $settings['item_type'] ?? self::ITEM_TYPE_PARCEL),
            'item_weight' => (float) ($input['item_weight'] ?? $settings['item_weight'] ?? 0.5),
            'recipient_city' => (int) ($input['recipient_city'] ?? $settings['recipient_city']),
            'recipient_zone' => (int) ($input['recipient_zone'] ?? $settings['recipient_zone']),
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->post($this->getBaseUrl() . '/aladdin/api/v1/merchant/price-plan', $payload);

            $json = $response->json();

            if ($response->successful() && ($json['code'] ?? null) == 200) {
                return [
                    'success' => true,
                    'message' => $json['message'] ?? 'Price calculated.',
                    'data' => $json['data'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $this->parseApiError($json, $response->body()),
                'data' => null,
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $th->getMessage(),
                'data' => null,
            ];
        }
    }

    private function authorizedGet(CourierConfiguration $config, string $path): array
    {
        $token = $this->getAccessToken($config);

        if (!$token) {
            return [];
        }

        $url = $this->getBaseUrl() . $path;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout(25)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ])->get($url);

                if (!$response->successful()) {
                    if ($attempt < 3) {
                        usleep(250000 * $attempt);
                        continue;
                    }

                    return [];
                }

                $json = $response->json();

                if (!is_array($json)) {
                    return [];
                }

                $list = $this->extractPathaoList($json);

                if ($list !== [] || (int) ($json['code'] ?? 0) === 200) {
                    return $list;
                }

                if ($attempt < 3) {
                    usleep(250000 * $attempt);
                }
            } catch (\Throwable $th) {
                if ($attempt >= 3) {
                    return [];
                }

                usleep(250000 * $attempt);
            }
        }

        return [];
    }

    private function tokenCacheKey(CourierConfiguration $config): string
    {
        $settings = $this->normalizeSettings($config->settings);

        return 'pathao_token:' . hash('sha256', implode('|', [
            (string) $config->api_key,
            (string) ($settings['username'] ?? ''),
            (string) ($settings['environment'] ?? 'sandbox'),
        ]));
    }

    private function getCachedAccessToken(CourierConfiguration $config): ?string
    {
        $cached = Cache::get($this->tokenCacheKey($config));

        if (!is_array($cached) || empty($cached['access_token'])) {
            return null;
        }

        if ((int) ($cached['expires_at'] ?? 0) <= time() + 120) {
            return null;
        }

        return (string) $cached['access_token'];
    }

    private function cacheAccessToken(CourierConfiguration $config, array $settings): void
    {
        $expiresAt = (int) ($settings['expires_at'] ?? 0);
        $ttlSeconds = max(300, $expiresAt - time());

        Cache::put($this->tokenCacheKey($config), [
            'access_token' => $settings['access_token'] ?? null,
            'refresh_token' => $settings['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
        ], now()->addSeconds($ttlSeconds));
    }

    private function extractPathaoList(array $json): array
    {
        $data = $json['data'] ?? null;

        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            return array_values($data['data']);
        }

        if (is_array($data) && $this->isSequentialList($data)) {
            return array_values($data);
        }

        return [];
    }

    private function isSequentialList(array $items): bool
    {
        if ($items === []) {
            return true;
        }

        return array_keys($items) === range(0, count($items) - 1);
    }

    private function requestToken(CourierConfiguration $config, string $grantType, array $extra): ?string
    {
        $settings = $this->normalizeSettings($config->settings);

        $payload = array_merge([
            'client_id' => $config->api_key,
            'client_secret' => $config->secret_key,
            'grant_type' => $grantType,
        ], $extra);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->getBaseUrl() . '/aladdin/api/v1/issue-token', $payload);

            $json = $response->json();

            if (!$response->successful() || empty($json['access_token'])) {
                return null;
            }

            $settings['access_token'] = $json['access_token'];
            $settings['refresh_token'] = $json['refresh_token'] ?? ($settings['refresh_token'] ?? null);
            $settings['expires_at'] = time() + (int) ($json['expires_in'] ?? 432000);

            $config->settings = $settings;

            if ($config->exists) {
                $config->save();
            } else {
                $this->cacheAccessToken($config, $settings);
            }

            return $json['access_token'];
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function normalizeSettings($settings): array
    {
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($settings)) {
            return json_decode(json_encode($settings), true) ?: [];
        }

        return is_array($settings) ? $settings : [];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return '0' . substr($digits, 3);
        }

        if (str_starts_with($digits, '88') && strlen($digits) === 13) {
            return '0' . substr($digits, 2);
        }

        return $digits;
    }

    private function parseApiError($json, string $fallbackBody = ''): string
    {
        if (is_array($json)) {
            if (!empty($json['message']) && is_string($json['message'])) {
                return $json['message'];
            }

            if (!empty($json['errors']) && is_array($json['errors'])) {
                $messages = [];

                foreach ($json['errors'] as $field => $errors) {
                    if (is_array($errors)) {
                        $messages = array_merge($messages, $errors);
                    } elseif (is_string($errors)) {
                        $messages[] = $errors;
                    }
                }

                if ($messages) {
                    return implode(' ', $messages);
                }
            }
        }

        return is_string($fallbackBody) && strlen($fallbackBody) < 300
            ? $fallbackBody
            : 'Pathao order creation failed.';
    }

    private function successOrderRow(array $order, array $data): array
    {
        $now = now()->toDateTimeString();

        return [
            'invoice' => $order['invoice'] ?? '',
            'recipient_name' => $order['recipient_name'] ?? '',
            'recipient_phone' => $order['recipient_phone'] ?? '',
            'recipient_address' => $order['recipient_address'] ?? '',
            'cod_amount' => $order['cod_amount'] ?? 0,
            'note' => $order['note'] ?? null,
            'consignment_id' => (string) ($data['consignment_id'] ?? ''),
            'tracking_code' => 'not-available',
            'status' => strtolower((string) ($data['order_status'] ?? 'pending')),
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
