<?php

namespace App\Services\Courier;

use App\LogHelper;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Steadfast return / cancel-request hub service.
 *
 * Create + list prefer Packzy public API (Api-Key / Secret-Key).
 * Confirm cancel / request resend use merchant portal cancel-requests pages.
 */
class SteadfastReturnRequestsService
{
    private const PACKZY_BASE = 'https://portal.packzy.com/api/v1';

    /** Portal cancel-request tab index → status slug. */
    private const PORTAL_TAB_STATUSES = [
        0 => 'pending',
        1 => 'confirmed',
        2 => 'resend_request',
        3 => 'cancelled',
        4 => 'resent',
    ];

    public function __construct(
        private SteadfastPortalSessionClient $portal,
    ) {}

    /**
     * @param  array{api_key: string, secret_key: string}  $apiConfig
     * @param  array{username?: string, password?: string}|null  $portalCredentials
     * @return array<string, mixed>
     */
    public function create(
        string $consignmentId,
        string $reason,
        array $apiConfig,
        ?array $portalCredentials = null,
        ?string $trackingCode = null,
        ?string $invoice = null,
    ): array {
        $consignmentId = $this->normalizeConsignmentId($consignmentId);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new RuntimeException('Please explain why the courier should return this parcel (at least 10 characters).');
        }

        $payload = [
            'consignment_id' => (int) $consignmentId,
            'reason' => $reason,
        ];
        if ($trackingCode) {
            $payload['tracking_code'] = $trackingCode;
        }
        if ($invoice) {
            $payload['invoice'] = $invoice;
        }

        try {
            $json = $this->packzyRequest('POST', '/create_return_request', $apiConfig, $payload);

            return $this->normalizeItem($json['data'] ?? $json, [
                'consignment_id' => $consignmentId,
                'reason' => $reason,
                'status' => 'pending',
            ]);
        } catch (\Throwable $apiError) {
            LogHelper::saveLog('Steadfast return create API failed', $apiError->getMessage());

            if ($portalCredentials === null) {
                throw $apiError;
            }

            return $this->createViaPortal($consignmentId, $reason, $portalCredentials);
        }
    }

    /**
     * @param  array{api_key: string, secret_key: string}  $apiConfig
     * @param  array{username?: string, password?: string}|null  $portalCredentials
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function list(
        array $apiConfig,
        ?array $portalCredentials = null,
        ?string $status = null,
        ?string $date = null,
    ): array {
        $status = $this->normalizeStatus($status);
        /** @var array<string, array<string, mixed>> $byConsignment */
        $byConsignment = [];
        $packzyOk = false;
        $portalOk = false;
        $lastError = null;

        // Packzy is primary (same pipeline as create_return_request).
        try {
            $json = $this->packzyRequest('GET', '/get_return_requests', $apiConfig);
            $raw = $json['data'] ?? $json;
            if (! is_array($raw)) {
                $raw = [];
            }
            if (isset($raw['items']) && is_array($raw['items'])) {
                $raw = $raw['items'];
            }

            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $item = $this->normalizeItem($row);
                $key = (string) ($item['consignment_id'] ?? '');
                if ($key === '') {
                    continue;
                }
                $byConsignment[$key] = $item;
            }
            $packzyOk = true;
        } catch (\Throwable $th) {
            $lastError = $th;
            LogHelper::saveLog('Steadfast return list API failed', $th->getMessage());
        }

        // Portal cancel-requests merge: fills Decide-tab statuses Packzy may omit.
        if ($portalCredentials !== null
            && trim((string) ($portalCredentials['username'] ?? '')) !== ''
            && trim((string) ($portalCredentials['password'] ?? '')) !== ''
        ) {
            try {
                $portal = $this->listViaPortal($portalCredentials, null, $date);
                foreach ($portal['items'] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $item = $this->normalizeItem($row);
                    $key = (string) ($item['consignment_id'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    if (! isset($byConsignment[$key])) {
                        $byConsignment[$key] = $item;
                        continue;
                    }
                    // Portal status wins (matches merchant cancel-requests UI).
                    $merged = $byConsignment[$key];
                    $portalStatus = $this->normalizeStatus($item['status'] ?? '') ?: '';
                    if ($portalStatus !== '') {
                        $merged['status'] = $portalStatus;
                    }
                    foreach (['customer_name', 'charge', 'requested_at', 'reason', 'id'] as $field) {
                        $incoming = $item[$field] ?? null;
                        if ($incoming === null || $incoming === '') {
                            continue;
                        }
                        if (($merged[$field] ?? null) === null || $merged[$field] === '') {
                            $merged[$field] = $incoming;
                        }
                    }
                    $byConsignment[$key] = $merged;
                }
                $portalOk = true;
            } catch (\Throwable $th) {
                $lastError = $th;
                LogHelper::saveLog('Steadfast return list portal failed', $th->getMessage());
            }
        }

        if (! $packzyOk && ! $portalOk) {
            throw $lastError instanceof \Throwable
                ? $lastError
                : new RuntimeException('Unable to list Steadfast return requests.');
        }

        $counts = $this->emptyCounts();
        $items = [];
        foreach ($byConsignment as $item) {
            $slug = $this->normalizeStatus($item['status'] ?? 'pending') ?: 'pending';
            if (isset($counts[$slug])) {
                $counts[$slug]++;
            }
            if ($status === null || $status === '' || $slug === $status) {
                $items[] = $item;
            }
        }

        return [
            'items' => array_values($items),
            'counts' => $counts,
        ];
    }

    /**
     * @param  array{username: string, password: string}  $portalCredentials
     * @return array<string, mixed>
     */
    public function updateStatus(
        string $action,
        array $portalCredentials,
        ?string $consignmentId = null,
        ?string $remoteId = null,
    ): array {
        $action = $this->sanitizeAction($action);
        if (! in_array($action, ['confirm_cancel', 'request_resend'], true)) {
            throw new RuntimeException('Choose Confirm cancel/return or Ask courier to resend.');
        }

        $consignmentId = $consignmentId ? $this->normalizeConsignmentId($consignmentId) : '';
        if ($consignmentId === '') {
            throw new RuntimeException('Consignment ID is required to update return status.');
        }

        $remoteId = $remoteId !== null ? trim((string) $remoteId) : '';
        if ($remoteId === $consignmentId) {
            // Portal scrapes often store consignment as id; keep as consignment only.
            $remoteId = '';
        }

        return $this->portal->withSession($portalCredentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($action, $consignmentId, $remoteId, $portalCredentials) {
            $listPath = '/user/consignment/cancel-requests/show/0';
            $page = $client->get($listPath, $host, $cookies, expectJson: false);
            $cookies = $client->absorbCookies($cookies, $page, $host, $portalCredentials);
            $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'cancel-requests');

            $csrf = $this->extractMetaCsrfToken($page->body())
                ?? $this->extractInputValue($page->body(), '_token');

            $statusValue = $action === 'confirm_cancel' ? 'confirm_cancel' : 'request_to_resend';
            $discovered = $this->discoverCancelStatusCandidates(
                $page->body(),
                $consignmentId,
                $csrf,
                $action,
                $statusValue,
                $remoteId !== '' ? $remoteId : null
            );

            // Known portal endpoints (Network capture still preferred when available).
            $fallback = [
                [
                    'path' => '/user/consignment/cancel-requests/change-status',
                    'payload' => array_filter([
                        '_token' => $csrf,
                        'consignment_id' => $consignmentId,
                        'id' => $remoteId !== '' ? $remoteId : null,
                        'status' => $statusValue,
                        'action' => $action,
                    ], static fn ($v) => $v !== null && $v !== ''),
                ],
                [
                    'path' => '/user/consignment/cancel-requests/change-status',
                    'payload' => array_filter([
                        '_token' => $csrf,
                        'consignment_id' => $consignmentId,
                        'status' => $action === 'confirm_cancel' ? 'confirmed' : 'resend_request',
                    ], static fn ($v) => $v !== null && $v !== ''),
                ],
                [
                    'path' => '/user/consignment/cancel-request/update',
                    'payload' => array_filter([
                        '_token' => $csrf,
                        'consignment_id' => $consignmentId,
                        'id' => $remoteId !== '' ? $remoteId : null,
                        'request_status' => $statusValue,
                        'status' => $statusValue,
                    ], static fn ($v) => $v !== null && $v !== ''),
                ],
                [
                    'path' => '/user/consignment/cancel-requests/update',
                    'payload' => array_filter([
                        '_token' => $csrf,
                        'consignment_id' => $consignmentId,
                        'id' => $remoteId !== '' ? $remoteId : null,
                        'status' => $action === 'confirm_cancel' ? 1 : 2,
                        'type' => $action === 'confirm_cancel' ? 'confirm' : 'resend',
                    ], static fn ($v) => $v !== null && $v !== ''),
                ],
            ];

            $candidates = array_merge($discovered, $fallback);
            $lastError = 'Unable to update Steadfast cancel/return request status. '
                .'Confirm portal login in Config → Courier → Steadfast, then try again.';

            foreach ($candidates as $candidate) {
                $path = (string) ($candidate['path'] ?? '');
                $payload = is_array($candidate['payload'] ?? null) ? $candidate['payload'] : [];
                if ($path === '' || $payload === []) {
                    continue;
                }

                $response = $client->postMultipart(
                    $path,
                    $payload,
                    $host,
                    $cookies,
                    $this->absoluteUrl($host, $listPath)
                );
                $cookies = $client->absorbCookies($cookies, $response, $host, $portalCredentials);

                if ($response->status() === 404) {
                    continue;
                }

                if ($response->status() === 401 || $response->status() === 419 || $client->looksLikeLoginPage($response->body())) {
                    throw new RuntimeException('Steadfast portal session expired while updating return status.');
                }

                $body = $response->json();
                if (is_array($body)) {
                    $ok = ($body['status'] ?? null) === true
                        || (int) ($body['status'] ?? 0) === 1
                        || ($body['success'] ?? false) === true
                        || (($body['status'] ?? null) === 'success');
                    if ($ok) {
                        return [
                            'consignment_id' => $consignmentId,
                            'id' => $remoteId !== '' ? $remoteId : null,
                            'status' => $action === 'confirm_cancel' ? 'confirmed' : 'resend_request',
                            'action' => $action,
                        ];
                    }
                    $message = trim((string) ($body['message'] ?? ''));
                    if ($message !== '') {
                        $lastError = $message;
                    }
                    continue;
                }

                // Only treat redirects / explicit success flashes as provisional wins,
                // then verify the consignment left the pending cancel-requests tab.
                $html = (string) $response->body();
                $looksError = preg_match('/\b(error|failed|invalid|unauthorized|forbidden)\b/i', $html) === 1;
                $looksSuccessFlash = preg_match('/\b(success|updated|confirmed|resend)\b/i', $html) === 1;
                $provisionalOk = $response->redirect()
                    || ($response->successful() && $looksSuccessFlash && ! $looksError);

                if (! $provisionalOk) {
                    continue;
                }

                $verify = $client->get($listPath, $host, $cookies, expectJson: false);
                $cookies = $client->absorbCookies($cookies, $verify, $host, $portalCredentials);
                $stillPending = str_contains($verify->body(), $consignmentId);
                if ($stillPending) {
                    $lastError = 'SteadFast did not confirm the status change for this consignment. Try again from the portal or check credentials.';
                    continue;
                }

                return [
                    'consignment_id' => $consignmentId,
                    'id' => $remoteId !== '' ? $remoteId : null,
                    'status' => $action === 'confirm_cancel' ? 'confirmed' : 'resend_request',
                    'action' => $action,
                ];
            }

            throw new RuntimeException($lastError);
        });
    }

    /**
     * Pull candidate POST targets from cancel-requests HTML near this consignment.
     *
     * @return list<array{path: string, payload: array<string, mixed>}>
     */
    private function discoverCancelStatusCandidates(
        string $html,
        string $consignmentId,
        ?string $csrf,
        string $action,
        string $statusValue,
        ?string $remoteId,
    ): array {
        $candidates = [];

        if (preg_match_all(
            '/<form\b[^>]*action=["\']([^"\']+)["\'][^>]*>(.*?)<\/form>/is',
            $html,
            $formMatches,
            PREG_SET_ORDER
        )) {
            foreach ($formMatches as $match) {
                $actionUrl = html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5);
                $formBody = (string) $match[2];
                if (! preg_match('/cancel[-_]?request/i', $actionUrl.$formBody)
                    && ! str_contains($formBody, $consignmentId)
                ) {
                    continue;
                }

                $path = parse_url($actionUrl, PHP_URL_PATH) ?: $actionUrl;
                if (! is_string($path) || $path === '') {
                    continue;
                }

                $payload = [
                    '_token' => $csrf,
                    'consignment_id' => $consignmentId,
                    'status' => $statusValue,
                    'action' => $action,
                ];
                if ($remoteId) {
                    $payload['id'] = $remoteId;
                }

                if (preg_match_all('/<input\b[^>]*>/i', $formBody, $inputMatches)) {
                    foreach ($inputMatches[0] as $inputHtml) {
                        if (! preg_match('/name=["\']([^"\']+)["\']/i', $inputHtml, $nameMatch)) {
                            continue;
                        }
                        $name = $nameMatch[1];
                        $value = '';
                        if (preg_match('/value=["\']([^"\']*)["\']/i', $inputHtml, $valueMatch)) {
                            $value = html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_HTML5);
                        }
                        if ($name === '_token' && $csrf) {
                            $payload['_token'] = $csrf;
                            continue;
                        }
                        if ($value !== '') {
                            $payload[$name] = $value;
                        }
                    }
                }

                $payload['consignment_id'] = $consignmentId;
                $payload['status'] = $statusValue;
                $payload['action'] = $action;

                $candidates[] = [
                    'path' => $path,
                    'payload' => array_filter($payload, static fn ($v) => $v !== null && $v !== ''),
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array<string, mixed>
     */
    private function createViaPortal(string $consignmentId, string $reason, array $credentials): array
    {
        return $this->portal->withSession($credentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($consignmentId, $reason, $credentials) {
            $path = '/consignments/' . rawurlencode($consignmentId) . '/return-request';
            $page = $client->get($path, $host, $cookies, expectJson: false);
            $cookies = $client->absorbCookies($cookies, $page, $host, $credentials);
            $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'return-request form');

            $csrf = $this->extractMetaCsrfToken($page->body())
                ?? $this->extractInputValue($page->body(), '_token');

            $payload = array_filter([
                '_token' => $csrf,
                'reason' => $reason,
                'consignment_id' => $consignmentId,
            ], static fn ($v) => $v !== null && $v !== '');

            $response = $client->postMultipart(
                $path,
                $payload,
                $host,
                $cookies,
                $this->absoluteUrl($host, $path)
            );
            $cookies = $client->absorbCookies($cookies, $response, $host, $credentials);

            if ($response->status() === 401 || $response->status() === 419 || $client->looksLikeLoginPage($response->body())) {
                throw new RuntimeException('Steadfast portal session expired while creating return request.');
            }

            $body = $response->json();
            if (is_array($body)) {
                $ok = ($body['status'] ?? null) === true
                    || (int) ($body['status'] ?? 0) === 1
                    || ($body['success'] ?? false) === true;
                if (! $ok && ! $response->successful()) {
                    throw new RuntimeException(
                        trim((string) ($body['message'] ?? 'Unable to create Steadfast return request.'))
                    );
                }

                return $this->normalizeItem($body['data'] ?? $body, [
                    'consignment_id' => $consignmentId,
                    'reason' => $reason,
                    'status' => 'pending',
                ]);
            }

            if (! $response->successful() && ! $response->redirect()) {
                throw new RuntimeException('Unable to create Steadfast return request (HTTP ' . $response->status() . ').');
            }

            return [
                'id' => '',
                'consignment_id' => $consignmentId,
                'status' => 'pending',
                'reason' => $reason,
                'customer_name' => '',
                'charge' => null,
                'requested_at' => now()->toDateTimeString(),
            ];
        });
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>}
     */
    private function listViaPortal(array $credentials, ?string $status, ?string $date): array
    {
        return $this->portal->withSession($credentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($credentials, $status, $date) {
            $counts = $this->emptyCounts();
            $items = [];
            $targetStatus = $this->normalizeStatus($status);

            foreach (self::PORTAL_TAB_STATUSES as $index => $slug) {
                $path = '/user/consignment/cancel-requests/show/' . $index;
                if ($date) {
                    $path .= '?date=' . rawurlencode($date);
                }

                $page = $client->get($path, $host, $cookies, expectJson: false);
                $cookies = $client->absorbCookies($cookies, $page, $host, $credentials);

                if ($index === 0) {
                    $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'cancel-requests');
                }

                if (! $page->successful()) {
                    continue;
                }

                $rows = $this->parseCancelRequestRows($page->body(), $slug);
                $counts[$slug] = count($rows);

                if ($targetStatus === null || $targetStatus === '' || $targetStatus === $slug) {
                    foreach ($rows as $row) {
                        $items[] = $row;
                    }
                }
            }

            return [
                'items' => $items,
                'counts' => $counts,
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCancelRequestRows(string $html, string $status): array
    {
        $rows = [];

        // Prefer table rows with consignment-looking IDs.
        if (preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $trMatches)) {
            foreach ($trMatches[1] as $trHtml) {
                if (stripos($trHtml, '<th') !== false) {
                    continue;
                }

                $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $trHtml)), ENT_QUOTES | ENT_HTML5));
                if ($text === '') {
                    continue;
                }

                if (! preg_match('/\b(\d{6,20})\b/', $trHtml, $idMatch)) {
                    continue;
                }
                $consignmentId = $idMatch[1];

                // Skip obvious non-data rows.
                if (preg_match('/change status|download excel|pending|confirmed/i', $text)
                    && ! preg_match('/\b' . preg_quote($consignmentId, '/') . '\b/', $text)
                ) {
                    continue;
                }

                $customer = '';
                if (preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $trHtml, $tdMatches) && count($tdMatches[1]) >= 3) {
                    $cells = array_map(
                        static fn ($cell) => trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5)),
                        $tdMatches[1]
                    );
                    // Typical columns: Date, Id, Customer Name, Payment, Charge, Action, Details
                    foreach ($cells as $cell) {
                        if ($cell === $consignmentId || $cell === '' || is_numeric($cell)) {
                            continue;
                        }
                        if (preg_match('/change status|view|select|confirm|resend/i', $cell)) {
                            continue;
                        }
                        if (preg_match('/\d{4}/', $cell) && preg_match('/am|pm|entry|july|jan|feb|mar|apr|may|jun|aug|sep|oct|nov|dec/i', $cell)) {
                            continue;
                        }
                        $customer = $cell;
                        break;
                    }

                    $charge = null;
                    foreach ($cells as $cell) {
                        if (preg_match('/^\d+(\.\d+)?$/', $cell) && $cell !== $consignmentId) {
                            $charge = (float) $cell;
                        }
                    }
                } else {
                    $charge = null;
                }

                $requestedAt = null;
                if (preg_match('/([A-Za-z]+ \d{1,2}, \d{4}\s+\d{1,2}:\d{2}:\d{2}\s*[AP]M)/', $text, $dateMatch)) {
                    $requestedAt = $dateMatch[1];
                }

                $rows[] = [
                    'id' => $consignmentId,
                    'consignment_id' => $consignmentId,
                    'status' => $status,
                    'reason' => '',
                    'customer_name' => $customer,
                    'charge' => $charge ?? null,
                    'requested_at' => $requestedAt,
                ];
            }
        }

        // Dedupe by consignment id (keep first).
        $unique = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = (string) $row['consignment_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @param  array{api_key: string, secret_key: string}  $apiConfig
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function packzyRequest(string $method, string $path, array $apiConfig, ?array $body = null): array
    {
        $apiKey = trim((string) ($apiConfig['api_key'] ?? ''));
        $secret = trim((string) ($apiConfig['secret_key'] ?? ''));
        if ($apiKey === '' || $secret === '') {
            throw new RuntimeException('Steadfast API key/secret are not configured.');
        }

        $request = Http::withHeaders([
            'Api-Key' => $apiKey,
            'Secret-Key' => $secret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(45);

        $url = self::PACKZY_BASE . $path;
        $response = strtoupper($method) === 'GET'
            ? $request->get($url)
            : $request->post($url, $body ?? []);

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        $status = $json['status'] ?? null;
        $ok = $response->successful()
            && (
                $status === true
                || $status === 'success'
                || (string) $status === '200'
                || (int) $status === 200
                || (int) $status === 1
            );

        if (! $ok) {
            $message = trim((string) ($json['message'] ?? ''));
            if ($message === '') {
                $message = 'Steadfast return request API failed (HTTP ' . $response->status() . ').';
            }
            throw new RuntimeException($message);
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeItem(array $row, array $defaults = []): array
    {
        $merged = array_merge($defaults, $row);
        $status = $this->normalizeStatus($merged['status'] ?? 'pending') ?: 'pending';

        $consignment = (string) ($merged['consignment_id'] ?? $merged['consignmentId'] ?? $defaults['consignment_id'] ?? '');
        $id = (string) ($merged['id'] ?? $merged['return_request_id'] ?? $merged['request_id'] ?? $consignment);

        $charge = $merged['charge'] ?? $merged['cod_amount'] ?? $merged['cod'] ?? null;

        return [
            'id' => $id,
            'consignment_id' => $consignment,
            'status' => $status,
            'reason' => (string) ($merged['reason'] ?? $defaults['reason'] ?? ''),
            'customer_name' => (string) ($merged['customer_name'] ?? $merged['customer'] ?? $merged['recipient_name'] ?? ''),
            'charge' => is_numeric($charge) ? (float) $charge : null,
            'invoice' => (string) ($merged['invoice'] ?? ''),
            'tracking_code' => (string) ($merged['tracking_code'] ?? ''),
            'requested_at' => (string) ($merged['requested_at'] ?? $merged['created_at'] ?? $merged['createdAt'] ?? ''),
            'updated_at' => (string) ($merged['updated_at'] ?? $merged['updatedAt'] ?? ''),
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        $raw = strtolower(trim((string) $status));
        $raw = str_replace([' ', '-'], '_', $raw);

        $map = [
            'pending' => 'pending',
            'approved' => 'confirmed',
            'confirmed' => 'confirmed',
            'confirm' => 'confirmed',
            'confirm_cancel' => 'confirmed',
            'processing' => 'confirmed',
            'resend_request' => 'resend_request',
            'request_to_resend' => 'resend_request',
            'request_resend' => 'resend_request',
            'resend' => 'resend_request',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            // Packzy return lifecycle often uses "completed" for a finished return.
            'completed' => 'confirmed',
            'resent' => 'resent',
        ];

        return $map[$raw] ?? (in_array($raw, array_values(self::PORTAL_TAB_STATUSES), true) ? $raw : '');
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'pending' => 0,
            'confirmed' => 0,
            'resend_request' => 0,
            'cancelled' => 0,
            'resent' => 0,
        ];
    }

    private function normalizeConsignmentId(string $consignmentId): string
    {
        $consignmentId = trim($consignmentId);
        if (! preg_match('/^\d{4,20}$/', $consignmentId)) {
            throw new RuntimeException('Invalid Steadfast consignment ID.');
        }

        return $consignmentId;
    }

    private function assertAuthenticatedHtml(
        SteadfastPortalSessionClient $client,
        string $html,
        int $status,
        string $context
    ): void {
        if ($status === 401 || $status === 419 || $client->looksLikeLoginPage($html)) {
            throw new RuntimeException('Steadfast portal session expired while loading ' . $context . '.');
        }
    }

    private function extractMetaCsrfToken(string $html): ?string
    {
        if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractInputValue(string $html, string $name): ?string
    {
        $quoted = preg_quote($name, '/');
        if (preg_match('/<input[^>]+name=["\']' . $quoted . '["\'][^>]+value=["\']([^"\']*)["\']/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
        if (preg_match('/<input[^>]+value=["\']([^"\']*)["\'][^>]+name=["\']' . $quoted . '["\']/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function sanitizeAction(string $key): string
    {
        $key = strtolower(trim($key));

        return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
    }

    private function absoluteUrl(string $host, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return 'https://' . $host . (str_starts_with($path, '/') ? $path : '/' . $path);
    }
}
