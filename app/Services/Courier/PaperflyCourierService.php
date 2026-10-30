<?php

namespace App\Services\Courier;

use App\Models\CourierConfiguration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PaperflyCourierService
{
    public const SENDER_BASE = 'https://go-app.paperfly.com.bd/merchant/api/react/address/sender/';

    public const ORDER_URL = 'https://api.paperfly.com.bd/merchant/api/service/new_order_v2.php';

    public const PAPERFLY_KEY = 'Paperfly_~La?Rj73FcLm';

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(CourierConfiguration $config): array
    {
        $call = $this->call($config, 'list.php?limit=1&page=1');
        if (! $call['ok']) {
            return ['ok' => false, 'message' => $call['message']];
        }

        return ['ok' => true, 'message' => 'Paperfly accepted the username and password.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function listStores(CourierConfiguration $config): array
    {
        $listed = $this->fetchRecords($config);
        if (! $listed['ok']) {
            return $listed;
        }

        return $this->snapshotResponse(
            $config,
            $this->mergeMeta($listed['records'], $this->settings($config)),
            (bool) $listed['truncated']
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createStore(CourierConfiguration $config, array $input): array
    {
        $payload = $this->storePayload($input);
        $invalid = $this->validateStorePayload($payload);
        if ($invalid !== null) {
            return ['ok' => false, 'validation' => true, 'message' => $invalid];
        }

        $created = $this->call($config, 'add.php', $payload);
        if (! $created['ok']) {
            return $created;
        }

        $listed = $this->fetchRecords($config);
        if (! $listed['ok']) {
            return $listed;
        }

        $match = $this->matchCreated($listed['records'], $payload['full_name'], $payload['phone_number']);
        $settings = $this->settings($config);
        if ($match === null) {
            return $this->snapshotResponse(
                $config,
                $this->mergeMeta($listed['records'], $settings),
                (bool) $listed['truncated'],
                'Paperfly accepted the store, but it was not in the list yet.'
            );
        }

        $settings['store_meta'] = $this->rememberMeta($settings, $match['id'], $payload['email'], $payload['label']);
        $match = $this->applyMeta($match, $settings['store_meta']);
        $active = $this->activeStore($settings);
        if (trim((string) ($active['id'] ?? '')) === '') {
            $settings['active_store'] = $match;
            $settings['store_name'] = $match['full_name'];
        }
        $this->saveSettings($config, $settings);

        return $this->snapshotResponse($config, $this->mergeMeta($listed['records'], $settings), (bool) $listed['truncated']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function updateStore(CourierConfiguration $config, array $input): array
    {
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            return ['ok' => false, 'validation' => true, 'message' => 'Store id is required.'];
        }

        $payload = $this->storePayload($input);
        $invalid = $this->validateStorePayload($payload);
        if ($invalid !== null) {
            return ['ok' => false, 'validation' => true, 'message' => $invalid];
        }

        $updated = $this->call($config, 'update.php', array_merge($payload, ['id' => $id]));
        if (! $updated['ok']) {
            return $updated;
        }

        $listed = $this->fetchRecords($config);
        if (! $listed['ok']) {
            return $listed;
        }

        $settings = $this->settings($config);
        $settings['store_meta'] = $this->rememberMeta($settings, $id, $payload['email'], $payload['label']);
        $records = $this->mergeMeta($listed['records'], $settings);
        $row = $this->findById($records, $id);
        $active = $this->activeStore($settings);
        if ($row !== null && (string) ($active['id'] ?? '') === $id) {
            $settings['active_store'] = $row;
            $settings['store_name'] = $row['full_name'];
        }
        $this->saveSettings($config, $settings);

        return $this->snapshotResponse($config, $records, (bool) $listed['truncated']);
    }

    /**
     * @return array<string, mixed>
     */
    public function setDefaultStore(CourierConfiguration $config, string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            return ['ok' => false, 'validation' => true, 'message' => 'Store id is required.'];
        }

        $listed = $this->fetchRecords($config);
        if (! $listed['ok']) {
            return $listed;
        }

        $settings = $this->settings($config);
        $records = $this->mergeMeta($listed['records'], $settings);
        $row = $this->findById($records, $id);
        if ($row === null) {
            return [
                'ok' => false,
                'message' => 'That store is not in the Paperfly list.',
            ];
        }

        $settings['active_store'] = $row;
        $settings['store_name'] = $row['full_name'];
        $this->saveSettings($config, $settings);

        return $this->snapshotResponse($config, $records, (bool) $listed['truncated']);
    }

    /**
     * Book one parcel per order. A bad row is returned with an error and is not sent.
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    public function createOrders(CourierConfiguration $config, array $orders): array
    {
        $settings = $this->settings($config);
        $storeName = trim((string) ($this->activeStore($settings)['full_name'] ?? ''));
        $rows = [];

        foreach ($orders as $order) {
            $row = is_array($order) ? $order : [];
            $invalid = $this->orderProblem($row, $settings, $storeName);
            if ($invalid !== null) {
                $rows[] = $this->orderRow($row, null, null, $invalid);
                continue;
            }

            $payload = $this->orderPayload($row, $settings, $storeName);
            $sent = $this->postOrder($config, $payload);
            if (! $sent['ok']) {
                $rows[] = $this->orderRow($row, null, null, $sent['message'] ?? 'Paperfly could not be reached.');
                continue;
            }

            $success = is_array($sent['body']['success'] ?? null) ? $sent['body']['success'] : [];
            $tracking = trim((string) ($success['tracking_number'] ?? ''));
            $barcode = trim((string) ($success['tracking_barcode'] ?? ''));
            if ($tracking === '') {
                $rows[] = $this->orderRow($row, null, null, 'Paperfly did not return a tracking number.');
                continue;
            }

            $rows[] = $this->orderRow($row, $tracking, $barcode !== '' ? $barcode : $tracking, null);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $payload
     */
    public function validateStorePayload(array $payload): ?string
    {
        if (mb_strlen($payload['full_name']) < 5) {
            return 'Store name is required (5+ characters).';
        }
        if (! preg_match('/^\+?[0-9]{11,15}$/', $payload['phone_number'])) {
            return 'A valid phone number is required (11-15 digits).';
        }
        if (filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'A valid email is required.';
        }
        if ($payload['district_name'] === '') {
            return 'District is required.';
        }
        if ($payload['thana_name'] === '') {
            return 'Thana is required.';
        }
        if (mb_strlen($payload['address']) < 5) {
            return 'Address is required (5+ characters).';
        }
        if ($payload['label'] === '') {
            return 'Label is required.';
        }

        return null;
    }

    /**
     * @return array{ok: bool, records?: array<int, array<string, string>>, truncated?: bool, message?: string}
     */
    private function fetchRecords(CourierConfiguration $config): array
    {
        $call = $this->call($config, 'list.php?limit=100&page=1');
        if (! $call['ok']) {
            return $call;
        }

        $body = is_array($call['body']) ? $call['body'] : [];
        $rows = $body['records'] ?? ($body['data']['records'] ?? []);
        if (! is_array($rows)) {
            $rows = [];
        }

        $records = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeStore($row);
            if ($normalized['id'] === '') {
                continue;
            }
            $records[] = $normalized;
        }

        $total = (int) ($body['totalRecords'] ?? ($body['data']['totalRecords'] ?? count($records)));

        return [
            'ok' => true,
            'records' => $records,
            'truncated' => $total > 100,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{ok: bool, body?: array<string, mixed>|null, message?: string}
     */
    private function call(CourierConfiguration $config, string $endpoint, ?array $payload = null): array
    {
        $username = trim((string) $config->api_key);
        $password = trim((string) $config->secret_key);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'message' => 'Paperfly username and password are required.'];
        }

        try {
            $pending = Http::withBasicAuth($username, $password)
                ->withHeaders([
                    'paperflykey' => self::PAPERFLY_KEY,
                    'Accept' => 'application/json',
                ])
                ->timeout(20);

            $response = $payload === null
                ? $pending->post(self::SENDER_BASE.$endpoint)
                : $pending->post(self::SENDER_BASE.$endpoint, $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Paperfly could not be reached.'];
        }

        return $this->interpret($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body?: array<string, mixed>|null, message?: string}
     */
    private function postOrder(CourierConfiguration $config, array $payload): array
    {
        $username = trim((string) $config->api_key);
        $password = trim((string) $config->secret_key);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'message' => 'Paperfly username and password are required.'];
        }

        try {
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders([
                    'paperflykey' => self::PAPERFLY_KEY,
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->post(self::ORDER_URL, $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Paperfly could not be reached.'];
        }

        return $this->interpret($response);
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $settings
     */
    private function orderProblem(array $order, array $settings, string $storeName): ?string
    {
        if ($storeName === '') {
            return 'A default Paperfly store is required.';
        }
        if (trim((string) ($order['recipient_name'] ?? '')) === '') {
            return 'Customer name is required.';
        }
        if (mb_strlen(trim((string) ($order['recipient_address'] ?? ''))) < 5) {
            return 'Customer address is required (5+ characters).';
        }
        if (! preg_match('/^01\d{9}$/', $this->customerPhone($order))) {
            return 'Customer phone must be 11 digits starting with 01.';
        }
        if ($this->orderWeight($order, $settings) === '') {
            return 'Default weight must be a positive number such as 1.0.';
        }
        if (trim((string) ($order['invoice'] ?? '')) === '') {
            return 'Invoice is required.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function orderPayload(array $order, array $settings, string $storeName): array
    {
        $invoice = trim((string) ($order['invoice'] ?? ''));
        $brief = trim((string) ($order['item_description'] ?? ''));
        if ($brief === '') {
            $brief = 'Order '.$invoice;
        }

        return [
            'merchantOrderReference' => $invoice,
            'storeName' => $storeName,
            'productBrief' => mb_substr($brief, 0, 180),
            'packagePrice' => (string) ($order['cod_amount'] ?? '0'),
            'max_weight' => $this->orderWeight($order, $settings),
            'customerName' => trim((string) ($order['recipient_name'] ?? '')),
            'customerAddress' => trim((string) ($order['recipient_address'] ?? '')),
            'customerPhone' => $this->customerPhone($order),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function customerPhone(array $order): string
    {
        $digits = preg_replace('/\D/', '', (string) ($order['recipient_phone'] ?? '')) ?? '';
        if (str_starts_with($digits, '0088')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '88')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^1\d{9}$/', $digits) === 1) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $settings
     */
    private function orderWeight(array $order, array $settings): string
    {
        foreach ([$order['item_weight'] ?? null, $order['parcel_weight'] ?? null, $settings['default_weight'] ?? null] as $raw) {
            if (is_numeric($raw) && (float) $raw > 0) {
                $number = (float) $raw;

                return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function orderRow(array $order, ?string $tracking, ?string $barcode, ?string $error): array
    {
        $now = now()->toDateTimeString();

        return [
            'invoice' => $order['invoice'] ?? '',
            'recipient_name' => $order['recipient_name'] ?? '',
            'recipient_phone' => $order['recipient_phone'] ?? '',
            'recipient_address' => $order['recipient_address'] ?? '',
            'cod_amount' => $order['cod_amount'] ?? 0,
            'note' => $order['note'] ?? null,
            'consignment_id' => $tracking,
            'tracking_code' => $barcode,
            'status' => $tracking ? 'pending' : null,
            'error' => $error,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array{ok: bool, body?: array<string, mixed>|null, message?: string}
     */
    private function interpret(Response $response): array
    {
        $status = $response->status();
        if ($status === 401 || $status === 403) {
            return ['ok' => false, 'message' => 'Paperfly rejected the username or password.'];
        }

        $body = $response->json();
        $data = is_array($body) ? $body : null;
        $responseCode = is_array($data) ? (int) ($data['response_code'] ?? 0) : 0;
        $hasError = is_array($data) && array_key_exists('error', $data);

        if ($status !== 200 || $hasError || $responseCode >= 400) {
            return ['ok' => false, 'message' => $this->errorMessage($data, $status)];
        }

        return ['ok' => true, 'body' => $data];
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function errorMessage(?array $data, int $status): string
    {
        if (! is_array($data)) {
            return 'Paperfly could not be verified (HTTP '.$status.').';
        }

        if (isset($data['message']) && is_string($data['message']) && trim($data['message']) !== '') {
            return trim($data['message']);
        }

        $parts = [];
        foreach ($data as $value) {
            if (is_string($value) && trim($value) !== '' && ! is_numeric($value)) {
                $parts[] = trim($value);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $parts[] = trim($item);
                    }
                }
            }
        }

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return 'Paperfly could not be verified (HTTP '.$status.').';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{full_name: string, phone_number: string, email: string, district_name: string, thana_name: string, address: string, label: string}
     */
    private function storePayload(array $input): array
    {
        return [
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'phone_number' => trim((string) ($input['phone_number'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'district_name' => trim((string) ($input['district_name'] ?? '')),
            'thana_name' => trim((string) ($input['thana_name'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'label' => trim((string) ($input['label'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: string, full_name: string, phone_number: string, email: string, district_name: string, thana_name: string, address_line: string, label: string}
     */
    private function normalizeStore(array $row): array
    {
        $address = trim((string) ($row['address_line'] ?? ''));
        if ($address === '') {
            $address = trim((string) ($row['address'] ?? ''));
        }

        return [
            'id' => trim((string) ($row['id'] ?? '')),
            'full_name' => trim((string) ($row['full_name'] ?? '')),
            'phone_number' => trim((string) ($row['phone_number'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'district_name' => trim((string) ($row['district_name'] ?? '')),
            'thana_name' => trim((string) ($row['thana_name'] ?? '')),
            'address_line' => $address,
            'label' => trim((string) ($row['label'] ?? '')),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $records
     */
    private function matchCreated(array $records, string $name, string $phone): ?array
    {
        $best = null;
        foreach ($records as $row) {
            if ($row['full_name'] !== $name || $row['phone_number'] !== $phone) {
                continue;
            }
            if ($best === null || $this->idIsHigher($row['id'], $best['id'])) {
                $best = $row;
            }
        }

        return $best;
    }

    private function idIsHigher(string $candidate, string $current): bool
    {
        if (ctype_digit($candidate) && ctype_digit($current)) {
            return (int) $candidate > (int) $current;
        }

        return strcmp($candidate, $current) > 0;
    }

    /**
     * @param  array<int, array<string, string>>  $records
     * @return array<string, string>|null
     */
    private function findById(array $records, string $id): ?array
    {
        foreach ($records as $row) {
            if ((string) $row['id'] === (string) $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array{email: string, label: string}>
     */
    private function rememberMeta(array $settings, string $id, string $email, string $label): array
    {
        $meta = is_array($settings['store_meta'] ?? null) ? $settings['store_meta'] : [];
        $meta[$id] = ['email' => $email, 'label' => $label];

        return $meta;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function applyMeta(array $row, array $meta): array
    {
        $saved = $meta[$row['id']] ?? null;
        if (! is_array($saved)) {
            return $row;
        }
        if ($row['email'] === '') {
            $row['email'] = trim((string) ($saved['email'] ?? ''));
        }
        if ($row['label'] === '') {
            $row['label'] = trim((string) ($saved['label'] ?? ''));
        }

        return $row;
    }

    /**
     * @param  array<int, array<string, string>>  $records
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, string>>
     */
    private function mergeMeta(array $records, array $settings): array
    {
        $meta = is_array($settings['store_meta'] ?? null) ? $settings['store_meta'] : [];
        $merged = [];
        foreach ($records as $row) {
            $merged[] = $this->applyMeta($row, $meta);
        }

        return $merged;
    }

    /**
     * @param  array<int, array<string, string>>  $records
     * @return array<string, mixed>
     */
    private function snapshotResponse(CourierConfiguration $config, array $records, bool $truncated, ?string $warning = null): array
    {
        $config->refresh();
        $settings = $this->settings($config);
        $active = $this->activeStore($settings);
        $response = [
            'ok' => true,
            'records' => $records,
            'active_store' => $active === [] ? null : $active,
            'store_name' => trim((string) ($settings['store_name'] ?? '')),
            'store_meta' => is_array($settings['store_meta'] ?? null) ? $settings['store_meta'] : [],
            'truncated' => $truncated,
        ];
        if ($warning !== null) {
            $response['warning'] = $warning;
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CourierConfiguration $config): array
    {
        return is_array($config->settings) ? $config->settings : [];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function activeStore(array $settings): array
    {
        return is_array($settings['active_store'] ?? null) ? $settings['active_store'] : [];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function saveSettings(CourierConfiguration $config, array $settings): void
    {
        unset($settings['password']);
        $config->settings = $settings;
        $config->save();
    }
}
