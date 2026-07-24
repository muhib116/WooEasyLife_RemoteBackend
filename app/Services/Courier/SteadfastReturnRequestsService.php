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

    /** Safety cap when walking SteadFast cancel-requests ?page=N links. */
    private const MAX_PORTAL_PAGES_PER_TAB = 40;

    /** Soft deadline (seconds) so hub can return partial portal data before PHP/proxy kills the request. */
    private const PORTAL_LIST_DEADLINE_SECONDS = 100;

    /** Faster auto-sync: active cancel-request tabs only. */
    private const PORTAL_QUICK_DEADLINE_SECONDS = 45;

    /** @var list<string> */
    private const PORTAL_QUICK_TAB_STATUSES = [
        'pending',
        'confirmed',
        'resend_request',
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
     * @param  string|null  $mode  `quick` (active tabs) or `full` (all tabs/pages)
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>, source: string, mode: string}
     */
    public function list(
        array $apiConfig,
        ?array $portalCredentials = null,
        ?string $status = null,
        ?string $date = null,
        ?string $mode = 'full',
    ): array {
        $status = $this->normalizeStatus($status);
        $mode = $this->normalizeListMode($mode);
        /** @var array<string, array<string, mixed>> $packzyByConsignment */
        $packzyByConsignment = [];
        /** @var array<string, array<string, mixed>> $byConsignment */
        $byConsignment = [];
        $packzyOk = false;
        $portalOk = false;
        $lastError = null;

        // Packzy return-request API (different queue from portal cancel-requests).
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
                $packzyByConsignment[$key] = $item;
            }
            $packzyOk = true;
        } catch (\Throwable $th) {
            $lastError = $th;
            LogHelper::saveLog('Steadfast return list API failed', $th->getMessage());
        }

        // Portal cancel-requests is the merchant UI source of truth.
        if ($portalCredentials !== null
            && trim((string) ($portalCredentials['username'] ?? '')) !== ''
            && trim((string) ($portalCredentials['password'] ?? '')) !== ''
        ) {
            try {
                $portal = $this->listViaPortal($portalCredentials, null, $date, $mode);
                foreach ($portal['items'] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $item = $this->normalizeItem($row);
                    $key = (string) ($item['consignment_id'] ?? '');
                    if ($key === '') {
                        continue;
                    }

                    // Enrich with Packzy reason/invoice when the same consignment exists there.
                    if (isset($packzyByConsignment[$key])) {
                        $packzy = $packzyByConsignment[$key];
                        foreach (['reason', 'invoice', 'tracking_code', 'customer_name', 'charge', 'id'] as $field) {
                            $incoming = $packzy[$field] ?? null;
                            if ($incoming === null || $incoming === '') {
                                continue;
                            }
                            if (($item[$field] ?? null) === null || $item[$field] === '') {
                                $item[$field] = $incoming;
                            }
                        }
                    }

                    $byConsignment[$key] = $item;
                }
                $portalOk = true;
            } catch (\Throwable $th) {
                $lastError = $th;
                LogHelper::saveLog('Steadfast return list portal failed', $th->getMessage());
            }
        }

        // Only fall back to Packzy-only rows when portal cancel-requests is unavailable.
        if (! $portalOk) {
            $byConsignment = $packzyByConsignment;
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
            'source' => $portalOk ? 'portal' : 'packzy',
            'mode' => $mode,
        ];
    }

    private function normalizeListMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return $mode === 'quick' ? 'quick' : 'full';
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
    private function listViaPortal(array $credentials, ?string $status, ?string $date, string $mode = 'full'): array
    {
        $mode = $this->normalizeListMode($mode);

        return $this->portal->withSession($credentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($credentials, $status, $date, $mode) {
            $counts = $this->emptyCounts();
            $items = [];
            $targetStatus = $this->normalizeStatus($status);
            $deadlineSeconds = $mode === 'quick'
                ? self::PORTAL_QUICK_DEADLINE_SECONDS
                : self::PORTAL_LIST_DEADLINE_SECONDS;
            $deadlineAt = microtime(true) + $deadlineSeconds;

            foreach (self::PORTAL_TAB_STATUSES as $index => $slug) {
                if ($mode === 'quick' && ! in_array($slug, self::PORTAL_QUICK_TAB_STATUSES, true)) {
                    continue;
                }

                if (microtime(true) >= $deadlineAt) {
                    LogHelper::saveLog(
                        'Steadfast cancel-requests list deadline reached',
                        'Stopped before tab '.$slug.' (mode='.$mode.')'
                    );
                    break;
                }

                try {
                    $tab = $this->fetchCancelRequestTabPages(
                        $client,
                        $cookies,
                        $host,
                        $credentials,
                        $index,
                        $slug,
                        $date,
                        requireAuthAssert: $index === 0,
                        deadlineAt: $deadlineAt,
                    );
                    $cookies = $tab['cookies'];
                    $rows = $tab['rows'];
                } catch (\Throwable $th) {
                    // Pending tab is required; other tabs are best-effort.
                    if ($index === 0) {
                        throw $th;
                    }
                    LogHelper::saveLog(
                        'Steadfast cancel-requests tab skipped',
                        $slug.': '.$th->getMessage()
                    );
                    continue;
                }

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
     * Walk SteadFast cancel-requests pagination (?page=1..N) for one status tab.
     *
     * @param  array{username: string, password: string}  $credentials
     * @param  array<string, string>  $cookies
     * @return array{rows: list<array<string, mixed>>, cookies: array<string, string>}
     */
    private function fetchCancelRequestTabPages(
        SteadfastPortalSessionClient $client,
        array $cookies,
        string $host,
        array $credentials,
        int $tabIndex,
        string $slug,
        ?string $date,
        bool $requireAuthAssert = false,
        ?float $deadlineAt = null,
    ): array {
        /** @var array<string, array<string, mixed>> $byConsignment */
        $byConsignment = [];
        $maxPage = 1;

        for ($pageNum = 1; $pageNum <= $maxPage; $pageNum++) {
            if ($deadlineAt !== null && microtime(true) >= $deadlineAt) {
                LogHelper::saveLog(
                    'Steadfast cancel-requests page deadline reached',
                    $slug.' stopped at page '.$pageNum
                );
                break;
            }

            $path = $this->cancelRequestsTabPath($tabIndex, $date, $pageNum);

            try {
                $response = $this->portalGetWithRetries($client, $path, $host, $cookies, 3);
            } catch (\Throwable $th) {
                if ($pageNum === 1) {
                    throw $th;
                }
                LogHelper::saveLog(
                    'Steadfast cancel-requests page skipped',
                    $slug.' page '.$pageNum.': '.$th->getMessage()
                );
                break;
            }

            $cookies = $client->absorbCookies($cookies, $response, $host, $credentials);

            if ($requireAuthAssert && $pageNum === 1) {
                $this->assertAuthenticatedHtml($client, $response->body(), $response->status(), 'cancel-requests');
            }

            if (! $response->successful()) {
                if ($pageNum === 1) {
                    throw new RuntimeException(
                        'Steadfast cancel-requests '.$slug.' page returned HTTP '.$response->status().'.'
                    );
                }
                LogHelper::saveLog(
                    'Steadfast cancel-requests page skipped',
                    $slug.' page '.$pageNum.': HTTP '.$response->status()
                );
                break;
            }

            $body = $response->body();
            $rows = $this->parseCancelRequestRows($body, $slug);

            // Page uses div.tbody-row / <cancel-request> (not <tr>). If markers exist but
            // parse yields nothing, treat as scrape failure so Packzy fallback can run.
            if ($rows === [] && $this->portalHtmlHasCancelRequestRows($body)) {
                throw new RuntimeException(
                    'Failed to parse Steadfast cancel-requests rows for status '.$slug
                    .' (page '.$pageNum.').'
                );
            }

            foreach ($rows as $row) {
                $key = (string) ($row['consignment_id'] ?? '');
                if ($key === '' || isset($byConsignment[$key])) {
                    continue;
                }
                $byConsignment[$key] = $row;
            }

            if ($pageNum === 1) {
                $maxPage = $this->detectCancelRequestsMaxPage($body);
            }

            // Stop early when a page returns no rows (pagination lied / trailing empty).
            if ($pageNum > 1 && $rows === []) {
                break;
            }

            if ($pageNum < $maxPage) {
                usleep(120000);
            }
        }

        return [
            'rows' => array_values($byConsignment),
            'cookies' => $cookies,
        ];
    }

    private function cancelRequestsTabPath(int $tabIndex, ?string $date, int $page = 1): string
    {
        $path = '/user/consignment/cancel-requests/show/'.$tabIndex;
        $query = [];
        if ($date !== null && $date !== '') {
            $query['date'] = $date;
        }
        if ($page > 1) {
            $query['page'] = $page;
        }
        if ($query === []) {
            return $path;
        }

        return $path.'?'.http_build_query($query);
    }

    private function detectCancelRequestsMaxPage(string $html): int
    {
        $max = 1;

        if (preg_match_all('/[?&]page=(\d+)/i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $max = max($max, (int) $value);
            }
        }

        if (preg_match_all('/class="[^"]*\bpage-link\b[^"]*"[^>]*>\s*(\d+)\s*</i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $max = max($max, (int) $value);
            }
        }

        return max(1, min($max, self::MAX_PORTAL_PAGES_PER_TAB));
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function portalGetWithRetries(
        SteadfastPortalSessionClient $client,
        string $path,
        string $host,
        array $cookies,
        int $attempts = 3,
    ): \Illuminate\Http\Client\Response {
        $attempts = max(1, $attempts);
        $lastError = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $page = $client->get($path, $host, $cookies, expectJson: false);
                if ($page->successful() || $page->status() < 500) {
                    return $page;
                }
                $lastError = new RuntimeException(
                    'Steadfast portal HTTP '.$page->status().' for '.$path
                );
            } catch (\Throwable $th) {
                $lastError = $th;
            }

            if ($i < $attempts) {
                usleep(350000 * $i);
            }
        }

        throw $lastError instanceof \Throwable
            ? $lastError
            : new RuntimeException('Steadfast portal request failed for '.$path);
    }

    /**
     * SteadFast cancel-requests UI renders div.tbody-row + <cancel-request :item="...">,
     * not classic <table>/<tr> markup.
     */
    private function portalHtmlHasCancelRequestRows(string $html): bool
    {
        return (bool) preg_match('/<cancel-request\b/i', $html)
            || (bool) preg_match('/class="[^"]*\btbody-row\b/i', $html);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCancelRequestRows(string $html, string $status): array
    {
        $rows = [];

        // 1) Preferred: Vue <cancel-request :item="{...}"> payload (full note + consignment).
        if (preg_match_all('/<cancel-request\s+:item="([^"]+)"/i', $html, $itemMatches)) {
            foreach ($itemMatches[1] as $encoded) {
                $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
                $payload = json_decode($decoded, true);
                if (! is_array($payload)) {
                    continue;
                }

                $consignment = is_array($payload['consignment'] ?? null) ? $payload['consignment'] : [];
                $consignmentId = (string) ($payload['consignment_id'] ?? $consignment['id'] ?? '');
                if ($consignmentId === '' || ! preg_match('/^\d{6,20}$/', $consignmentId)) {
                    continue;
                }

                $requestId = (string) ($payload['id'] ?? $consignmentId);
                $note = trim((string) ($payload['note'] ?? ''));
                $customer = trim((string) ($consignment['cus_name'] ?? $payload['customer_name'] ?? ''));
                $charge = $consignment['cod_amount'] ?? $consignment['entry_cod_amount'] ?? null;
                $invoice = (string) ($consignment['invoice'] ?? '');
                $tracking = (string) ($consignment['track_id'] ?? '');
                $requestedAt = (string) ($payload['created_at'] ?? $payload['updated_at'] ?? '');

                $rows[] = [
                    'id' => $requestId,
                    'consignment_id' => $consignmentId,
                    'status' => $status,
                    'reason' => $note,
                    'customer_name' => $customer,
                    'charge' => is_numeric($charge) ? (float) $charge : null,
                    'invoice' => $invoice,
                    'tracking_code' => $tracking,
                    'requested_at' => $requestedAt,
                    'updated_at' => (string) ($payload['updated_at'] ?? ''),
                ];
            }
        }

        // 2) Fallback: div-based grid (Date | Id | Name | … | COD).
        if ($rows === []) {
            $parts = preg_split('/<div\b[^>]*class="[^"]*\btbody-row\b[^"]*"/i', $html);
            if (is_array($parts) && count($parts) > 1) {
                array_shift($parts);
                foreach ($parts as $part) {
                    $chunk = $part;
                    // Limit chunk to before next structural close roughly via cell_2 Id.
                    if (! preg_match('/cell_2.*?>(.*?)<\/div>/is', $chunk, $idCell)) {
                        continue;
                    }
                    $idText = trim(html_entity_decode(strip_tags($idCell[1]), ENT_QUOTES | ENT_HTML5));
                    $idText = preg_replace('/^\s*Id\s*/i', '', $idText) ?? $idText;
                    if (! preg_match('/(\d{6,20})/', $idText, $idMatch)) {
                        continue;
                    }
                    $consignmentId = $idMatch[1];

                    $customer = '';
                    if (preg_match('/cell_3.*?>(.*?)<\/div>/is', $chunk, $nameCell)) {
                        $customer = trim(html_entity_decode(strip_tags($nameCell[1]), ENT_QUOTES | ENT_HTML5));
                        $customer = trim(preg_replace('/^\s*Name\s*/i', '', $customer) ?? $customer);
                    }

                    $charge = null;
                    if (preg_match('/cell_5.*?>(.*?)<\/div>/is', $chunk, $codCell)) {
                        $codText = trim(html_entity_decode(strip_tags($codCell[1]), ENT_QUOTES | ENT_HTML5));
                        $codText = trim(preg_replace('/^\s*COD\s*/i', '', $codText) ?? $codText);
                        if (preg_match('/^\d+(\.\d+)?$/', $codText)) {
                            $charge = (float) $codText;
                        }
                    }

                    $requestedAt = null;
                    if (preg_match('/cell_1.*?>(.*?)<\/div>/is', $chunk, $dateCell)) {
                        $dateText = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $dateCell[1])), ENT_QUOTES | ENT_HTML5);
                        if (preg_match('/Entry\s*@\s*([A-Za-z]+ \d{1,2}, \d{4}\s+\d{1,2}:\d{2}:\d{2}\s*[AP]M)/i', $dateText, $dateMatch)) {
                            $requestedAt = $dateMatch[1];
                        } elseif (preg_match('/([A-Za-z]+ \d{1,2}, \d{4}\s+\d{1,2}:\d{2}:\d{2}\s*[AP]M)/', $dateText, $dateMatch)) {
                            $requestedAt = $dateMatch[1];
                        }
                    }

                    $rows[] = [
                        'id' => $consignmentId,
                        'consignment_id' => $consignmentId,
                        'status' => $status,
                        'reason' => '',
                        'customer_name' => $customer,
                        'charge' => $charge,
                        'requested_at' => $requestedAt,
                    ];
                }
            }
        }

        // 3) Legacy <tr>/<td> tables (older portal markup).
        if ($rows === [] && preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $trMatches)) {
            foreach ($trMatches[1] as $trHtml) {
                if (stripos($trHtml, '<th') !== false) {
                    continue;
                }

                $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $trHtml)), ENT_QUOTES | ENT_HTML5));
                if ($text === '') {
                    continue;
                }

                $cells = [];
                if (preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $trHtml, $tdMatches)) {
                    $cells = array_map(
                        static fn ($cell) => trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5)),
                        $tdMatches[1]
                    );
                }

                // SteadFast columns: Date | Id | Customer Name | Payment | Charge | Action | Details
                // Date cells often contain leading numeric noise — never take the first \d from the row.
                $consignmentId = '';
                if (isset($cells[1]) && preg_match('/^\d{6,20}$/', $cells[1])) {
                    $consignmentId = $cells[1];
                } else {
                    foreach ($cells as $index => $cell) {
                        if ($index === 0) {
                            continue; // skip Date column
                        }
                        if (preg_match('/^\d{6,20}$/', $cell)) {
                            $consignmentId = $cell;
                            break;
                        }
                    }
                }

                if ($consignmentId === '') {
                    continue;
                }

                $customer = '';
                if (count($cells) >= 3) {
                    $candidate = $cells[2] ?? '';
                    if ($candidate !== ''
                        && $candidate !== $consignmentId
                        && ! preg_match('/change status|view|select|confirm|resend/i', $candidate)
                        && ! preg_match('/^\d+(\.\d+)?$/', $candidate)
                    ) {
                        $customer = $candidate;
                    } else {
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
                    }
                }

                $charge = null;
                if (isset($cells[4]) && preg_match('/^\d+(\.\d+)?$/', $cells[4])) {
                    $charge = (float) $cells[4];
                } else {
                    foreach ($cells as $index => $cell) {
                        if ($index <= 1) {
                            continue;
                        }
                        if (preg_match('/^\d+(\.\d+)?$/', $cell) && $cell !== $consignmentId) {
                            $charge = (float) $cell;
                        }
                    }
                }

                $requestedAt = null;
                $dateCell = $cells[0] ?? $text;
                if (preg_match('/Entry\s*@\s*([A-Za-z]+ \d{1,2}, \d{4}\s+\d{1,2}:\d{2}:\d{2}\s*[AP]M)/i', $dateCell, $dateMatch)) {
                    $requestedAt = $dateMatch[1];
                } elseif (preg_match('/([A-Za-z]+ \d{1,2}, \d{4}\s+\d{1,2}:\d{2}:\d{2}\s*[AP]M)/', $dateCell, $dateMatch)) {
                    $requestedAt = $dateMatch[1];
                }

                $rows[] = [
                    'id' => $consignmentId,
                    'consignment_id' => $consignmentId,
                    'status' => $status,
                    'reason' => '',
                    'customer_name' => $customer,
                    'charge' => $charge,
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

        // Packzy often returns `{ "data": [...] }` with no top-level status.
        $ok = $response->successful();
        if ($ok && array_key_exists('status', $json)) {
            $status = $json['status'];
            $ok = $status === true
                || $status === 'success'
                || (string) $status === '200'
                || (int) $status === 200
                || (int) $status === 1;
        }

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

        // Packzy nests parcel fields under consignment{}.
        if (isset($merged['consignment']) && is_array($merged['consignment'])) {
            $nested = $merged['consignment'];
            unset($merged['consignment']);
            $merged = array_merge($nested, $merged);
        }

        $status = $this->normalizeStatus($merged['status'] ?? 'pending') ?: 'pending';

        $consignment = (string) ($merged['consignment_id'] ?? $merged['consignmentId'] ?? $defaults['consignment_id'] ?? '');
        $id = (string) ($merged['id'] ?? $merged['return_request_id'] ?? $merged['request_id'] ?? $consignment);

        $charge = $merged['charge'] ?? $merged['cod_amount'] ?? $merged['cod'] ?? null;
        $reason = (string) ($merged['reason'] ?? $merged['note'] ?? $defaults['reason'] ?? '');
        $customer = (string) (
            $merged['customer_name']
            ?? $merged['customer']
            ?? $merged['recipient_name']
            ?? $merged['cus_name']
            ?? ''
        );

        return [
            'id' => $id,
            'consignment_id' => $consignment,
            'status' => $status,
            'reason' => $reason,
            'customer_name' => $customer,
            'charge' => is_numeric($charge) ? (float) $charge : null,
            'invoice' => (string) ($merged['invoice'] ?? ''),
            'tracking_code' => (string) ($merged['tracking_code'] ?? $merged['track_id'] ?? ''),
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
