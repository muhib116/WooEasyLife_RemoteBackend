<?php

namespace App\Services;

use App\Models\CourierConfiguration;
use Illuminate\Http\Client\Pool;
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
        return $this->getAuthConfig($userId);
    }

    public function getAuthConfig(int $userId, array $override = []): ?CourierConfiguration
    {
        $config = null;

        if (!empty($override['courier_config_id'])) {
            $query = CourierConfiguration::query()
                ->where('id', (int) $override['courier_config_id'])
                ->where('slug', 'pathao');

            if ($userId > 0) {
                $query->where('user_id', $userId);
            }

            $config = $query->first();
        }

        if (!$config && $userId > 0) {
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

            $settings = $this->normalizeSettings($config->settings);
            $storedApiKey = trim((string) ($config->api_key ?? ''));
            $overrideKey = trim((string) $override['api_key']);
            $overrideSecret = trim((string) ($override['secret_key'] ?? ''));
            $nextEnvironment = !empty($override['environment'])
                ? ($override['environment'] === 'live' ? 'live' : 'sandbox')
                : (($settings['environment'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox');
            $authChanged = false;

            $shouldKeepStoredCredentials = $config->exists
                && $nextEnvironment === 'live'
                && $this->isSandboxApiKey($overrideKey)
                && $storedApiKey !== ''
                && !$this->isSandboxApiKey($storedApiKey);

            if (!$shouldKeepStoredCredentials) {
                $config->api_key = $overrideKey;

                if ($overrideSecret !== '') {
                    $config->secret_key = $overrideSecret;
                }
            }

            if (!empty($override['username'])) {
                $settings['username'] = trim((string) $override['username']);
                $authChanged = true;
            }

            if (!empty($override['password'])) {
                $settings['password'] = (string) $override['password'];
                $authChanged = true;
            }

            if (!empty($override['environment'])) {
                if (($settings['environment'] ?? 'sandbox') !== $nextEnvironment) {
                    $authChanged = true;
                }
                $settings['environment'] = $nextEnvironment;
            }

            if ($authChanged) {
                unset($settings['access_token'], $settings['refresh_token'], $settings['expires_at']);
            }

            $config->settings = $settings;
        }

        if (!$config || !$config->api_key || !$config->secret_key) {
            return null;
        }

        $config->settings = $this->normalizeSettings($config->settings);

        return $config;
    }

    public function getBaseUrl(?CourierConfiguration $config = null): string
    {
        if ($config) {
            $settings = $this->normalizeSettings($config->settings);

            if (($settings['environment'] ?? 'sandbox') === 'live') {
                return rtrim(env('PATHAO_LIVE_BASE_URL', 'https://api-hermes.pathao.com'), '/');
            }
        }

        return rtrim(
            env('PATHAO_SANDBOX_BASE_URL', env('PATHAO_BASE_URL', 'https://courier-api-sandbox.pathao.com')),
            '/'
        );
    }

    /**
     * Pathao's sandbox gateway resets HTTP/2 connections from PHP/cURL.
     * Force HTTP/1.1 for all merchant API calls.
     */
    private function pathaoClient(int $timeout = 25): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions([
            'version' => 1.1,
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ])->timeout($timeout);
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

        $token = $this->requestExternalLoginToken($config);

        if ($token) {
            return $token;
        }

        if (!empty($settings['refresh_token'])) {
            $token = $this->requestToken($config, 'refresh_token', [
                'refresh_token' => $settings['refresh_token'],
            ]);

            if ($token) {
                return $token;
            }
        }

        if (!empty($settings['username']) && !empty($settings['password'])) {
            return $this->requestToken($config, 'password', [
                'username' => $settings['username'],
                'password' => $settings['password'],
            ]);
        }

        return null;
    }

    public function createOrder(CourierConfiguration $config, array $order): array
    {
        $settings = $this->normalizeSettings($config->settings);
        $token = $this->getAccessToken($config);

        if (!$token) {
            return $this->failedOrderRow($order, 'Unable to authenticate with Pathao. Check Client ID, Secret, login email, and password.');
        }

        $storeId = (int) ($order['store_id'] ?? $settings['store_id'] ?? 0);

        if ($storeId <= 0) {
            return $this->failedOrderRow($order, 'Select a Pathao pickup store before sending the order.');
        }

        $itemType = (int) ($order['item_type'] ?? $settings['item_type'] ?? self::ITEM_TYPE_PARCEL);
        if (!in_array($itemType, [self::ITEM_TYPE_DOCUMENT, self::ITEM_TYPE_PARCEL], true)) {
            $itemType = self::ITEM_TYPE_PARCEL;
        }

        $payload = [
            'store_id' => $storeId,
            'merchant_order_id' => (string) ($order['invoice'] ?? ''),
            'recipient_name' => (string) ($order['recipient_name'] ?? ''),
            'recipient_phone' => $this->normalizePhone($order['recipient_phone'] ?? ''),
            'recipient_address' => (string) ($order['recipient_address'] ?? ''),
            'delivery_type' => (int) ($order['delivery_type'] ?? $settings['delivery_type'] ?? self::DELIVERY_TYPE_NORMAL),
            'item_type' => $itemType,
            'item_quantity' => max(1, (int) ($order['item_quantity'] ?? $settings['item_quantity'] ?? 1)),
            'item_weight' => (float) ($order['item_weight'] ?? $settings['item_weight'] ?? 0.5),
            'amount_to_collect' => max(0, (int) round($order['cod_amount'] ?? 0)),
        ];

        $recipientCity = (int) ($order['recipient_city'] ?? $settings['recipient_city'] ?? 0);
        $recipientZone = (int) ($order['recipient_zone'] ?? $settings['recipient_zone'] ?? 0);
        $recipientArea = (int) ($order['recipient_area'] ?? $settings['recipient_area'] ?? 0);

        if ($recipientCity > 0) {
            $payload['recipient_city'] = $recipientCity;
        }

        if ($recipientZone > 0) {
            $payload['recipient_zone'] = $recipientZone;
        }

        if ($recipientArea > 0) {
            $payload['recipient_area'] = $recipientArea;
        }

        $secondaryPhone = $this->normalizePhone((string) ($order['recipient_secondary_phone'] ?? ''));

        if ($secondaryPhone !== '' && preg_match('/^01[0-9]{9}$/', $secondaryPhone)) {
            $payload['recipient_secondary_phone'] = $secondaryPhone;
        }

        $specialInstruction = trim((string) ($order['note'] ?? ''));

        if ($specialInstruction !== '') {
            $payload['special_instruction'] = $specialInstruction;
        }

        $itemDescription = trim((string) ($order['item_description'] ?? $specialInstruction));

        if ($itemDescription !== '') {
            $payload['item_description'] = $itemDescription;
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = $this->pathaoClient()->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ])->post($this->getBaseUrl($config) . '/aladdin/api/v1/orders', $payload);

                $json = $response->json();

                if ($response->successful() && ($json['code'] ?? null) == 200 && !empty($json['data']['consignment_id'])) {
                    return $this->successOrderRow($order, $json['data']);
                }

                return $this->failedOrderRow($order, $this->parseApiError($json, $response->body()));
            } catch (\Throwable $th) {
                if ($attempt >= 3 || !$this->isRetryableNetworkError($th->getMessage())) {
                    return $this->failedOrderRow($order, $this->formatNetworkError($th->getMessage()));
                }

                usleep(250000 * $attempt);
            }
        }

        return $this->failedOrderRow($order, 'Pathao order creation failed.');
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

    public function getOrderStatus(CourierConfiguration $config, string $consignmentId): string
    {
        $token = $this->getAccessToken($config);

        if (!$token) {
            return '';
        }

        try {
            $response = $this->pathaoClient()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get($this->getBaseUrl($config) . '/aladdin/api/v1/orders/' . $consignmentId . '/info');

            $json = $response->json();

            if ($response->successful() && ($json['code'] ?? null) == 200) {
                $status = $json['data']['order_status_slug'] ?? $json['data']['order_status'] ?? '';

                return $this->normalizeOrderStatus(is_string($status) ? $status : '');
            }
        } catch (\Throwable $th) {
            return '';
        }

        return '';
    }

    /**
     * @param  array<int|string>  $consignmentIds
     * @return array<string, string>
     */
    public function fetchOrderStatuses(
        CourierConfiguration $config,
        array $consignmentIds,
        ?int $concurrency = null,
    ): array {
        $ids = $this->normalizeConsignmentIds($consignmentIds);
        if ($ids === []) {
            return [];
        }

        $token = $this->getAccessToken($config);
        if (!$token) {
            return array_fill_keys($ids, '');
        }

        $limit = max(1, min(50, $concurrency ?? (int) config('courier.bulk_status_concurrency', 15)));
        $baseUrl = $this->getBaseUrl($config);
        $statuses = [];

        foreach (array_chunk($ids, $limit) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $token, $baseUrl) {
                foreach ($chunk as $id) {
                    $pool->as($id)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])
                        ->timeout(12)
                        ->get($baseUrl . '/aladdin/api/v1/orders/' . rawurlencode($id) . '/info');
                }
            });

            foreach ($chunk as $id) {
                $statuses[$id] = $this->parseOrderStatusResponse($responses[$id] ?? null);
            }
        }

        return $statuses;
    }

    private function parseOrderStatusResponse(mixed $response): string
    {
        if ($response === null) {
            return '';
        }

        try {
            if (method_exists($response, 'successful') && !$response->successful()) {
                return '';
            }

            $json = $response->json();
            if (($json['code'] ?? null) == 200) {
                $status = $json['data']['order_status_slug'] ?? $json['data']['order_status'] ?? '';

                return $this->normalizeOrderStatus(is_string($status) ? $status : '');
            }
        } catch (\Throwable $th) {
            return '';
        }

        return '';
    }

    /**
     * @param  array<int|string>  $consignmentIds
     * @return array<int, string>
     */
    private function normalizeConsignmentIds(array $consignmentIds): array
    {
        $normalized = [];

        foreach ($consignmentIds as $consignmentId) {
            $id = trim((string) $consignmentId);
            if ($id !== '') {
                $normalized[] = $id;
            }
        }

        return $normalized;
    }

    public function normalizeOrderStatus(string $status): string
    {
        $raw = strtolower(trim(str_replace(['-', ' '], '_', $status)));
        $raw = preg_replace('/^order\./', '', $raw);

        if ($raw === '') {
            return '';
        }

        $aliases = [
            'success' => 'delivered',
            'complete' => 'delivered',
            'completed' => 'delivered',
            'picked_up' => 'in_transit',
            'picked' => 'in_transit',
            'pickup_requested' => 'pending',
            'on_hold' => 'hold',
            'returned' => 'cancelled',
            'canceled' => 'cancelled',
        ];

        if (isset($aliases[$raw])) {
            return $aliases[$raw];
        }

        return $raw;
    }

    public function getStores(
        CourierConfiguration $config,
        int $maxPages = 3,
        int $maxItems = 100,
        ?int $includeStoreId = null
    ): array {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $stores = $this->fetchStorePages($config, $maxPages);

            if ($stores !== []) {
                $stores = array_slice($stores, 0, max(1, $maxItems));

                if ($includeStoreId && !$this->storeListContains($stores, $includeStoreId)) {
                    $matched = $this->findStoreById($config, $includeStoreId, $maxPages);

                    if ($matched) {
                        array_unshift($stores, $matched);
                    } else {
                        array_unshift($stores, [
                            'store_id' => $includeStoreId,
                            'store_name' => 'Saved store',
                        ]);
                    }
                }

                return $stores;
            }

            if ($attempt < 3) {
                usleep(300000 * $attempt);
            }
        }

        if ($includeStoreId) {
            return [[
                'store_id' => $includeStoreId,
                'store_name' => 'Saved store',
            ]];
        }

        return [];
    }

    private function fetchStorePages(CourierConfiguration $config, int $maxPages): array
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
                $response = $this->pathaoClient(25)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ])->get($this->getBaseUrl($config) . '/aladdin/api/v1/stores', [
                        'page' => $page,
                    ]);

                if (!$response->successful()) {
                    break;
                }

                $json = $response->json();

                if (($json['code'] ?? null) != 200) {
                    break;
                }

                $pageData = $json['data']['data'] ?? [];
                $stores = array_merge($stores, is_array($pageData) ? $pageData : []);

                $lastPage = (int) ($json['data']['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage && $page <= max(1, $maxPages));
        } catch (\Throwable $th) {
            return $stores;
        }

        return $stores;
    }

    private function storeListContains(array $stores, int $storeId): bool
    {
        foreach ($stores as $store) {
            if ((int) ($store['store_id'] ?? 0) === $storeId) {
                return true;
            }
        }

        return false;
    }

    private function findStoreById(CourierConfiguration $config, int $storeId, int $maxPages): ?array
    {
        $token = $this->getAccessToken($config);

        if (!$token) {
            return null;
        }

        $page = 1;
        $lastPage = 1;

        try {
            do {
                $response = $this->pathaoClient(25)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ])->get($this->getBaseUrl($config) . '/aladdin/api/v1/stores', [
                        'page' => $page,
                    ]);

                if (!$response->successful()) {
                    break;
                }

                $json = $response->json();

                if (($json['code'] ?? null) != 200) {
                    break;
                }

                $pageData = $json['data']['data'] ?? [];

                if (is_array($pageData)) {
                    foreach ($pageData as $store) {
                        if ((int) ($store['store_id'] ?? 0) === $storeId) {
                            return $store;
                        }
                    }
                }

                $lastPage = (int) ($json['data']['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage && $page <= max(1, $maxPages));
        } catch (\Throwable $th) {
            return null;
        }

        return null;
    }

    public function getCities(CourierConfiguration $config): array
    {
        $cities = $this->authorizedCatalogList($config, '/aladdin/api/v1/countries/1/city-list');

        if ($cities !== []) {
            return $cities;
        }

        return $this->authorizedCatalogList($config, '/aladdin/api/v1/city-list');
    }

    public function getZones(CourierConfiguration $config, int $cityId): array
    {
        return $this->authorizedCatalogList(
            $config,
            '/aladdin/api/v1/cities/' . $cityId . '/zone-list'
        );
    }

    public function getAreas(CourierConfiguration $config, int $zoneId): array
    {
        return $this->authorizedCatalogList(
            $config,
            '/aladdin/api/v1/zones/' . $zoneId . '/area-list'
        );
    }

    public function testConnection(CourierConfiguration $config): array
    {
        $failureMessage = $this->explainAuthFailure($config);

        if ($failureMessage !== null) {
            return [
                'success' => false,
                'message' => $failureMessage,
            ];
        }

        $this->invalidateAccessToken($config);
        $token = $this->getAccessToken($config);

        if (!$token) {
            return [
                'success' => false,
                'message' => $this->explainAuthFailure($config)
                    ?? 'Invalid Client ID or Secret. Check credentials and environment.',
            ];
        }

        return [
            'success' => true,
            'message' => 'API connection successful.',
        ];
    }

    public function resetToken(CourierConfiguration $config): array
    {
        $this->invalidateAccessToken($config);

        $token = $this->requestExternalLoginToken($config);

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Unable to reset token. Check Client ID and Secret.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Connection token reset successfully.',
        ];
    }

    public function getMerchantInfo(CourierConfiguration $config): array
    {
        $failureMessage = $this->explainAuthFailure($config);

        if ($failureMessage !== null) {
            return [
                'success' => false,
                'message' => $failureMessage,
                'data' => null,
            ];
        }

        $token = $this->getAccessToken($config);

        if (!$token) {
            return [
                'success' => false,
                'message' => $this->explainAuthFailure($config)
                    ?? 'Unable to authenticate with Pathao. Check Client ID, Secret, and environment.',
                'data' => null,
            ];
        }

        try {
            $response = $this->pathaoClient()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($this->getBaseUrl($config) . '/aladdin/api/v1/user/short-info');

            $json = $response->json();

            if (!$response->successful() || !is_array($json['data'] ?? null)) {
                return [
                    'success' => false,
                    'message' => $this->parseApiError($json, $response->body()),
                    'data' => null,
                ];
            }

            $data = $json['data'];
            $countryId = (int) ($data['country_id'] ?? 0);

            return [
                'success' => true,
                'message' => $json['message'] ?? 'Merchant info loaded.',
                'data' => [
                    'merchant_name' => (string) ($data['merchant_name'] ?? $data['user_name'] ?? ''),
                    'user_email' => (string) ($data['user_email'] ?? ''),
                    'user_phone' => (string) ($data['user_phone'] ?? ''),
                    'country_id' => $countryId,
                    'country_name' => $countryId === 1 ? 'Bangladesh' : ($countryId > 0 ? 'Nepal' : ''),
                ],
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => $this->formatNetworkError($th->getMessage()),
                'data' => null,
            ];
        }
    }

    public function createStore(CourierConfiguration $config, array $storeData): array
    {
        return $this->mutateStore(
            $config,
            'POST',
            '/aladdin/api/v1/stores',
            $this->buildStorePayload($storeData),
            'Store created successfully.',
            'Pathao store creation failed.'
        );
    }

    public function updateStore(CourierConfiguration $config, int $storeId, array $storeData): array
    {
        $payload = $this->buildStorePayload($storeData);

        return $this->mutateStore(
            $config,
            'PUT',
            '/aladdin/api/v1/stores/' . $storeId,
            $payload,
            'Store updated successfully.',
            'Pathao store update failed.'
        );
    }

    public function deleteStore(CourierConfiguration $config, int $storeId): array
    {
        return $this->mutateStore(
            $config,
            'DELETE',
            '/aladdin/api/v1/stores/' . $storeId,
            null,
            'Store deleted successfully.',
            'Pathao store deletion failed.'
        );
    }

    private function invalidateAccessToken(CourierConfiguration $config): void
    {
        $settings = $this->normalizeSettings($config->settings);
        unset($settings['access_token'], $settings['refresh_token'], $settings['expires_at']);
        $config->settings = $settings;

        if (class_exists(\Illuminate\Support\Facades\Cache::class)) {
            \Illuminate\Support\Facades\Cache::forget($this->tokenCacheKey($config));
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

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = $this->pathaoClient()->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ])->post($this->getBaseUrl($config) . '/aladdin/api/v1/merchant/price-plan', $payload);

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
                if ($attempt >= 3) {
                    return [
                        'success' => false,
                        'message' => $this->formatNetworkError($th->getMessage()),
                        'data' => null,
                    ];
                }

                usleep(250000 * $attempt);
            }
        }

        return [
            'success' => false,
            'message' => 'Unable to calculate delivery price from Pathao.',
            'data' => null,
        ];
    }

    /**
     * Fetch Pathao catalog lists (cities/zones/areas).
     * Tries GET first, then POST with empty body — live Hermes often requires POST.
     */
    private function authorizedCatalogList(CourierConfiguration $config, string $path): array
    {
        for ($authAttempt = 0; $authAttempt < 2; $authAttempt++) {
            $token = $this->getAccessToken($config);

            if (!$token) {
                return [];
            }

            $url = $this->getBaseUrl($config) . $path;
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            foreach (['get', 'post'] as $method) {
                $list = $this->fetchPathaoCatalogList($url, $headers, $method);

                if ($list !== []) {
                    return $list;
                }
            }

            if ($authAttempt === 0) {
                $this->invalidateAccessToken($config);
            }
        }

        return [];
    }

    private function fetchPathaoCatalogList(string $url, array $headers, string $method): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $client = $this->pathaoClient(25)->withHeaders($headers);
                $response = $method === 'post'
                    ? $client->post($url, [])
                    : $client->get($url);

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

                if ($list !== []) {
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

    private function authorizedGet(CourierConfiguration $config, string $path): array
    {
        return $this->authorizedCatalogList($config, $path);
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
        $candidates = [];

        if (isset($json['data'])) {
            $candidates[] = $json['data'];
        }

        $candidates[] = $json;

        foreach ($candidates as $candidate) {
            $list = $this->normalizePathaoListItems($candidate);

            if ($list !== []) {
                return $list;
            }
        }

        return [];
    }

    private function normalizePathaoListItems(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $nested = $this->normalizePathaoListItems($payload['data']);

            if ($nested !== []) {
                return $nested;
            }
        }

        foreach (['cities', 'zones', 'areas', 'stores', 'items', 'list'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $nested = $this->normalizePathaoListItems($payload[$key]);

                if ($nested !== []) {
                    return $nested;
                }
            }
        }

        if ($payload === []) {
            return [];
        }

        if ($this->isSequentialList($payload)) {
            return array_values(array_filter($payload, fn ($item) => is_array($item) && $item !== []));
        }

        $values = array_values($payload);

        if ($values !== [] && is_array($values[0])) {
            return array_values(array_filter($values, fn ($item) => is_array($item) && $item !== []));
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

    private function requestExternalLoginToken(CourierConfiguration $config, bool $persist = true): ?string
    {
        $settings = $this->normalizeSettings($config->settings);

        try {
            $response = $this->pathaoClient()->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->getBaseUrl($config) . '/aladdin/api/v1/external/login', [
                'client_id' => $config->api_key,
                'client_secret' => $config->secret_key,
            ]);

            $json = $response->json();

            if (!$response->successful() || empty($json['access_token'])) {
                return null;
            }

            if (!$persist) {
                return $json['access_token'];
            }

            return $this->persistAccessToken($config, $settings, $json);
        } catch (\Throwable $th) {
            return null;
        }
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
            $response = $this->pathaoClient()->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->getBaseUrl($config) . '/aladdin/api/v1/issue-token', $payload);

            $json = $response->json();

            if (!$response->successful() || empty($json['access_token'])) {
                return null;
            }

            return $this->persistAccessToken($config, $settings, $json);
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function persistAccessToken(CourierConfiguration $config, array $settings, array $json): ?string
    {
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

    private function resolvePathaoEnvironment(CourierConfiguration $config): string
    {
        $settings = $this->normalizeSettings($config->settings);

        return ($settings['environment'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    private function isSandboxApiKey(?string $apiKey): bool
    {
        $key = trim((string) $apiKey);

        if ($key === '') {
            return false;
        }

        $configured = trim((string) env('PATHAO_SANDBOX_CLIENT_ID', '7N1aMJQbWm'));

        return $key === '7N1aMJQbWm' || ($configured !== '' && $key === $configured);
    }

    private function explainAuthFailure(CourierConfiguration $config): ?string
    {
        if (!$config->api_key || !$config->secret_key) {
            return 'Save Pathao Client ID and Secret first.';
        }

        if ($this->resolvePathaoEnvironment($config) === 'live' && $this->isSandboxApiKey($config->api_key)) {
            return 'Live mode is on but Pathao sandbox Client ID is still in use. Enter your production Client ID and Secret from Pathao Developer API, then save settings.';
        }

        return null;
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

    private function formatNetworkError(string $message): string
    {
        if (
            str_contains($message, 'cURL error 56')
            || str_contains($message, 'Connection reset by peer')
            || str_contains($message, 'Broken pipe')
        ) {
            return 'Pathao API connection failed. Please try again in a moment.';
        }

        return $message;
    }

    private function buildStorePayload(array $storeData): array
    {
        $contactNumber = $this->normalizePhone((string) ($storeData['contact_number'] ?? ''));
        $payload = [
            'name' => trim((string) ($storeData['name'] ?? '')),
            'contact_name' => trim((string) ($storeData['contact_name'] ?? '')),
            'contact_number' => $contactNumber,
            'address' => trim((string) ($storeData['address'] ?? '')),
            'city_id' => (int) ($storeData['city_id'] ?? 0),
            'zone_id' => (int) ($storeData['zone_id'] ?? 0),
            'area_id' => (int) ($storeData['area_id'] ?? 0),
        ];

        $otpNumber = $this->normalizePhone((string) ($storeData['otp_number'] ?? $contactNumber));

        if ($otpNumber !== '') {
            $payload['otp_number'] = $otpNumber;
        }

        $secondaryContact = $this->normalizePhone((string) ($storeData['secondary_contact'] ?? ''));

        if ($secondaryContact !== '' && preg_match('/^01[0-9]{9}$/', $secondaryContact)) {
            $payload['secondary_contact'] = $secondaryContact;
        }

        return $payload;
    }

    private function mutateStore(
        CourierConfiguration $config,
        string $method,
        string $path,
        ?array $payload,
        string $successMessage,
        string $failureMessage
    ): array {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $token = $this->getAccessToken($config);

            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Unable to authenticate with Pathao.',
                    'data' => null,
                ];
            }

            try {
                $request = $this->pathaoClient()->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ]);

                $url = $this->getBaseUrl($config) . $path;

                $response = match (strtoupper($method)) {
                    'PUT' => $request->put($url, $payload ?? []),
                    'PATCH' => $request->patch($url, $payload ?? []),
                    'DELETE' => $request->delete($url),
                    default => $request->post($url, $payload ?? []),
                };

                $json = $response->json();
                $responseCode = is_array($json) ? (int) ($json['code'] ?? 0) : 0;
                $hasApiError = is_array($json) && !empty($json['error']);

                if ($response->successful() && !$hasApiError && ($responseCode === 0 || in_array($responseCode, [200, 201, 204], true))) {
                    $storeData = $json['data'] ?? null;

                    if (is_array($storeData) && isset($storeData['data']) && is_array($storeData['data'])) {
                        $storeData = $storeData['data'];
                    }

                    return [
                        'success' => true,
                        'message' => $json['message'] ?? $successMessage,
                        'data' => $storeData,
                    ];
                }

                $details = $this->parseApiErrorDetails($json, $response->body(), $failureMessage);
                $message = $details['message'];

                if ($response->status() === 404 || $response->status() === 405) {
                    $message .= ' Pathao may not allow this action via API — use the Pathao Merchant app to manage stores.';
                }

                return [
                    'success' => false,
                    'message' => $message,
                    'errors' => $details['errors'],
                    'data' => null,
                ];
            } catch (\Throwable $th) {
                if ($attempt < 3 && $this->isRetryableNetworkError($th->getMessage())) {
                    $this->invalidateAccessToken($config);
                    usleep(250000 * $attempt);
                    continue;
                }

                return [
                    'success' => false,
                    'message' => $this->formatNetworkError($th->getMessage()),
                    'data' => null,
                ];
            }
        }

        return [
            'success' => false,
            'message' => $failureMessage,
            'data' => null,
        ];
    }

    private function parseApiError($json, string $fallbackBody = '', string $defaultMessage = 'Pathao request failed.'): string
    {
        return $this->parseApiErrorDetails($json, $fallbackBody, $defaultMessage)['message'];
    }

    private function parseApiErrorDetails($json, string $fallbackBody = '', string $defaultMessage = 'Pathao request failed.'): array
    {
        $errors = null;
        $message = $defaultMessage;

        if (is_array($json)) {
            if (!empty($json['errors']) && is_array($json['errors'])) {
                $errors = $json['errors'];
                $messages = [];

                foreach ($json['errors'] as $fieldErrors) {
                    if (is_array($fieldErrors)) {
                        foreach ($fieldErrors as $error) {
                            if (is_string($error) && $error !== '') {
                                $messages[] = $error;
                            }
                        }
                    } elseif (is_string($fieldErrors) && $fieldErrors !== '') {
                        $messages[] = $fieldErrors;
                    }
                }

                if ($messages) {
                    return [
                        'message' => implode(' ', array_values(array_unique($messages))),
                        'errors' => $errors,
                    ];
                }
            }

            if (!empty($json['message']) && is_string($json['message'])) {
                $message = $json['message'];
            }
        }

        if ($message === $defaultMessage && is_string($fallbackBody) && strlen($fallbackBody) > 0 && strlen($fallbackBody) < 300) {
            $message = $fallbackBody;
        }

        return [
            'message' => $message,
            'errors' => $errors,
        ];
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
            'status' => $this->normalizeOrderStatus((string) ($data['order_status_slug'] ?? $data['order_status'] ?? 'pending')),
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
