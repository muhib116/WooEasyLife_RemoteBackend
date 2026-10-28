<?php

namespace App\Services\Courier;

use App\LogHelper;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Steadfast return / cancel-request hub service.
 *
 * Two queues:
 * - Merchant return requests ("Ask to return") live in the Packzy API:
 *   create_return_request, get_return_requests, get_return_request/{id}.
 * - Recipient cancel requests only exist on the merchant portal
 *   (cancel-requests/show/{tab}); Packzy has no endpoint for them.
 *
 * Decide reads the parcel page first, then makes the portal's own call once:
 * POST /user/consignment/cancel-request/confirm-request
 * FormData: item_id + verify_status (1=Confirm Cancel, 2=Request to Resend).
 *
 * Dates leave this service as Bangladesh time, `Y-m-d H:i:s`.
 */
class SteadfastReturnRequestsService
{
    private const PACKZY_BASE = 'https://portal.packzy.com/api/v1';

    /** SteadFast prints portal times in Bangladesh time. */
    private const PORTAL_TIMEZONE = 'Asia/Dhaka';

    private const CONFIRM_REQUEST_PATH = '/user/consignment/cancel-request/confirm-request';

    /** Delivery statuses where confirming a cancel would cancel a parcel the customer already has. */
    private const CONFIRM_BLOCKING_DELIVERY_STATUSES = [
        'delivered',
        'partial_delivered',
        'delivered_approval_pending',
        'partial_delivered_approval_pending',
    ];

    /** Pages per tab in a quick sync. New requests and fresh decisions sit at the front. */
    private const PORTAL_QUICK_PAGES_PER_TAB = 3;

    /** Undated pages to filter when the portal's dated list fails. */
    private const DATED_FALLBACK_PAGES = 5;

    /** Merchant return requests read by id in one list call. */
    private const MAX_TRACKED_RETURN_REQUESTS = 10;

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

    /**
     * Tab walk order. Resent comes before Cancelled: Cancelled can hold
     * thousands of requests (100+ pages) and would use up the deadline first.
     */
    private const PORTAL_TAB_ORDER = [0, 1, 2, 4, 3];

    /** Newest pages of the Cancelled history read in a full sync. */
    private const PORTAL_CANCELLED_PAGES = 10;

    /** Cards on a full SteadFast cancel-request page. A short page is the end of that tab. */
    private const PORTAL_TAB_PAGE_SIZE = 20;

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
     * @param  list<string>|null  $returnRequestIds  Open merchant return requests to read by id.
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>, source: string, mode: string, authoritative_statuses: list<string>, return_requests: list<array<string, mixed>>}
     */
    public function list(
        array $apiConfig,
        ?array $portalCredentials = null,
        ?string $status = null,
        ?string $date = null,
        ?string $mode = 'full',
        ?array $returnRequestIds = null,
    ): array {
        $status = $this->normalizeStatus($status);
        $mode = $this->normalizeListMode($mode);
        /** @var array<string, array<string, mixed>> $packzyByConsignment */
        $packzyByConsignment = [];
        /** @var array<string, array<string, mixed>> $returnRequests */
        $returnRequests = [];
        /** @var array<string, array<string, mixed>> $byConsignment */
        $byConsignment = [];
        $packzyOk = false;
        $portalOk = false;
        $lastError = null;
        $portalError = null;
        /** @var list<string> $authoritativeStatuses */
        $authoritativeStatuses = [];

        // Packzy return-request API (different queue from portal cancel-requests).
        try {
            $json = $this->packzyRequest('GET', '/get_return_requests', $apiConfig);

            foreach ($this->packzyRows($json) as $row) {
                $item = $this->normalizeItem($row);
                $key = (string) ($item['consignment_id'] ?? '');
                if ($key === '') {
                    continue;
                }
                $packzyByConsignment[$key] = $item;
                $request = $this->normalizeReturnRequest($row);
                if ($request['id'] !== '') {
                    $returnRequests[$request['id']] = $request;
                }
            }
            $packzyOk = true;
        } catch (\Throwable $th) {
            $lastError = $th;
            LogHelper::saveLog('Steadfast return list API failed', $th->getMessage());
        }

        // Page 1 holds the newest ten. Older open requests are read one by one.
        $tracked = array_slice(array_values(array_unique(array_filter(
            array_map(static fn ($id) => preg_replace('/\D+/', '', (string) $id) ?? '', $returnRequestIds ?? []),
            static fn ($id) => $id !== ''
        ))), 0, self::MAX_TRACKED_RETURN_REQUESTS);
        foreach ($tracked as $returnId) {
            if (isset($returnRequests[$returnId])) {
                continue;
            }
            try {
                $json = $this->packzyRequest('GET', '/get_return_request/'.$returnId, $apiConfig, null, 15);
                $row = is_array($json['data'] ?? null) ? $json['data'] : $json;
                $request = is_array($row) ? $this->normalizeReturnRequest($row) : null;
                if ($request !== null && $request['id'] === $returnId) {
                    $returnRequests[$returnId] = $request;
                }
            } catch (\Throwable $th) {
                LogHelper::saveLog('Steadfast return request read failed', $returnId.': '.$th->getMessage());
            }
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
                    // Never the id: a Packzy return id is not a portal cancel item.
                    if (isset($packzyByConsignment[$key])) {
                        $packzy = $packzyByConsignment[$key];
                        foreach (['reason', 'invoice', 'tracking_code', 'customer_name', 'charge'] as $field) {
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
                $authoritativeStatuses = array_values(array_filter(
                    $portal['authoritative_statuses'] ?? [],
                    static fn ($slug) => is_string($slug) && $slug !== ''
                ));
            } catch (\Throwable $th) {
                $lastError = $th;
                $portalError = $th;
                LogHelper::saveLog('Steadfast return list portal failed', $th->getMessage());
            }
        }

        // Portal login is saved, so a failed scrape must not look like a fresh
        // Packzy list. Packzy is a different queue and was hiding the real error.
        if ($portalError instanceof \Throwable) {
            throw $portalError;
        }

        // Packzy remains the list only when no portal login is saved.
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
            'authoritative_statuses' => $portalOk ? $authoritativeStatuses : [],
            'return_requests' => array_values($returnRequests),
        ];
    }

    /**
     * Rows from a Packzy list response: a bare list, `{data: [...]}`, or a paginator.
     *
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function packzyRows(array $json): array
    {
        $raw = $json['data'] ?? $json;
        if (! is_array($raw)) {
            return [];
        }
        if (isset($raw['items']) && is_array($raw['items'])) {
            $raw = $raw['items'];
        } elseif (isset($raw['data']) && is_array($raw['data']) && array_is_list($raw['data'])) {
            $raw = $raw['data'];
        }
        if (! array_is_list($raw)) {
            return [];
        }

        return array_values(array_filter($raw, 'is_array'));
    }

    /**
     * One merchant return request with Packzy's own status word kept beside the slug.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeReturnRequest(array $row): array
    {
        $item = $this->normalizeItem($row);
        $item['id'] = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?? '';
        $item['api_status'] = strtolower(trim((string) ($row['status'] ?? '')));

        return $item;
    }

    private function normalizeListMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return $mode === 'quick' ? 'quick' : 'full';
    }

    /**
     * Confirm cancel or ask to resend, the way the portal's own buttons do it.
     *
     * SteadFast's state is read from the parcel page before anything is
     * posted. A request that is already decided is reported and never posted
     * again; otherwise exactly one POST goes to confirm-request.
     *
     * @param  array{username: string, password: string}  $portalCredentials
     * @param  array{api_key: string, secret_key: string}|null  $apiConfig  Packzy keys for the delivered-parcel check.
     * @return array{consignment_id: string, id: string, status: string, action: string, verify_status: int, already_decided: bool}
     */
    public function updateStatus(
        string $action,
        array $portalCredentials,
        ?string $consignmentId = null,
        ?string $remoteId = null,
        ?array $apiConfig = null,
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
        if ($remoteId === $consignmentId || ! preg_match('/^\d+$/', $remoteId)) {
            $remoteId = '';
        }

        if ($action === 'confirm_cancel' && $apiConfig !== null) {
            $this->assertParcelStillCancellable($consignmentId, $apiConfig);
        }

        $verifyStatus = $action === 'confirm_cancel' ? 1 : 2;

        return $this->portal->withSession($portalCredentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($action, $consignmentId, $remoteId, $portalCredentials, $verifyStatus) {
            // withSession runs this again after a re-login, so the state is
            // re-read before every POST and a decided request is not posted twice.
            [$request, $html, $cookies] = $this->loadCancelRequest(
                $client,
                $host,
                $cookies,
                $portalCredentials,
                $consignmentId
            );

            if ($remoteId !== '' && $remoteId !== $request['item_id']) {
                LogHelper::saveLog(
                    'Steadfast decide item differs from parcel page',
                    $consignmentId.': sent '.$remoteId.', portal '.$request['item_id']
                );
            }

            if ($request['status'] !== 'pending') {
                return $this->settledDecision($action, $consignmentId, $request);
            }

            $response = $client->postMultipart(
                $this->confirmRequestPath($html, $host),
                array_filter([
                    '_token' => $this->extractMetaCsrfToken($html) ?? $this->extractInputValue($html, '_token'),
                    'item_id' => $request['item_id'],
                    'verify_status' => (string) $verifyStatus,
                ], static fn ($v) => $v !== null && $v !== ''),
                $host,
                $cookies,
                $this->absoluteUrl($host, '/user/consignment/'.rawurlencode($consignmentId)),
                followRedirects: false,
            );
            $cookies = $client->absorbCookies($cookies, $response, $host, $portalCredentials);

            $location = strtolower((string) $response->header('Location'));
            if ($response->status() === 401
                || $response->status() === 419
                || ($response->redirect() && str_contains($location, '/login'))
                || $client->looksLikeLoginPage($response->body())
            ) {
                throw new RuntimeException('Steadfast portal session expired while updating return status.');
            }

            $body = $response->json();
            if ($response->successful() && is_array($body) && $this->portalJsonOk($body)) {
                $returned = $body['data']['verify_status'] ?? $body['verify_status'] ?? $verifyStatus;
                $status = self::PORTAL_TAB_STATUSES[(int) $returned]
                    ?? ($action === 'confirm_cancel' ? 'confirmed' : 'resend_request');

                return $this->decisionResult($action, $consignmentId, $request['item_id'], $status, false);
            }

            // Not the portal's JSON success: the parcel page says what happened.
            try {
                [$after] = $this->loadCancelRequest($client, $host, $cookies, $portalCredentials, $consignmentId);
            } catch (SteadfastCancelRequestStateException) {
                $after = null;
            }
            if ($after !== null && $after['status'] !== 'pending') {
                return $this->settledDecision($action, $consignmentId, $after, false);
            }

            $message = is_array($body) ? trim((string) ($body['message'] ?? '')) : '';
            throw new RuntimeException($message !== ''
                ? $message
                : 'SteadFast did not save the decision (HTTP '.$response->status().'). Nothing changed; refresh and try again, or decide on the SteadFast portal.');
        });
    }

    /**
     * Packzy's live delivery status. Fails open: no answer never blocks a decision.
     *
     * @param  array{api_key: string, secret_key: string}  $apiConfig
     */
    private function assertParcelStillCancellable(string $consignmentId, array $apiConfig): void
    {
        try {
            $json = $this->packzyRequest('GET', '/status_by_cid/'.rawurlencode($consignmentId), $apiConfig, null, 8);
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast decide status check skipped', $consignmentId.': '.$th->getMessage());

            return;
        }

        $status = str_replace([' ', '-'], '_', strtolower(trim((string) ($json['delivery_status'] ?? ''))));
        if (! in_array($status, self::CONFIRM_BLOCKING_DELIVERY_STATUSES, true)) {
            return;
        }

        throw new SteadfastCancelRequestStateException(
            'SteadFast shows parcel '.$consignmentId.' as '.str_replace('_', ' ', $status)
                .', so it cannot be cancelled. Nothing was changed.',
            [
                'code' => 'parcel_not_cancellable',
                'consignment_id' => $consignmentId,
                'courier_status' => $status,
            ]
        );
    }

    /**
     * SteadFast's cancel request for one parcel: the parcel page first, then the pending tab.
     *
     * @param  array<string, string>  $cookies
     * @param  array{username: string, password: string}  $credentials
     * @return array{0: array{item_id: string, status: string, label: string}, 1: string, 2: array<string, string>}
     */
    private function loadCancelRequest(
        SteadfastPortalSessionClient $client,
        string $host,
        array $cookies,
        array $credentials,
        string $consignmentId,
    ): array {
        $page = $client->get('/user/consignment/'.rawurlencode($consignmentId), $host, $cookies, expectJson: false);
        $cookies = $client->absorbCookies($cookies, $page, $host, $credentials);
        $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'parcel '.$consignmentId);

        $html = $page->successful() ? $page->body() : '';
        $request = $html !== '' ? $this->readParcelCancelRequest($html, $consignmentId) : null;

        if ($request === null) {
            $list = $client->get('/user/consignment/cancel-requests/show/0', $host, $cookies, expectJson: false);
            $cookies = $client->absorbCookies($cookies, $list, $host, $credentials);
            $this->assertAuthenticatedHtml($client, $list->body(), $list->status(), 'cancel-requests');
            if ($list->successful()) {
                $request = $this->readCardCancelRequest($list->body(), $consignmentId)
                    ?? $this->readLegacyCancelRequest($list->body(), $consignmentId);
                if ($request !== null) {
                    $html = $list->body();
                }
            }
        }

        if ($request === null) {
            throw new SteadfastCancelRequestStateException(
                'SteadFast shows no cancellation request on parcel '.$consignmentId.'. Nothing was changed; refresh the list.',
                ['code' => 'no_cancel_request', 'consignment_id' => $consignmentId]
            );
        }

        return [$request, $html, $cookies];
    }

    /**
     * The `sf-parcel__creq` notice on a parcel page (Oct 2026 markup), else the older Vue item.
     *
     * @return array{item_id: string, status: string, label: string}|null
     */
    private function readParcelCancelRequest(string $html, string $consignmentId): ?array
    {
        if (preg_match('/<div\b(?=[^>]*\bclass="[^"]*\bsf-parcel\b)[^>]*\bdata-id="(\d+)"/i', $html, $parcel)
            && $parcel[1] !== $consignmentId
        ) {
            throw new RuntimeException(
                'SteadFast opened parcel '.$parcel[1].' instead of '.$consignmentId.'. Nothing was changed.'
            );
        }

        if (preg_match('/<div\b[^>]*\bsf-parcel__creq\b[^>]*>/i', $html, $tag, PREG_OFFSET_CAPTURE)
            && preg_match('/\bdata-sf-creq="(\d+)"/', $tag[0][0], $item)
        ) {
            return ['item_id' => $item[1]] + $this->cancelRequestState(substr($html, (int) $tag[0][1], 8000));
        }

        return $this->readLegacyCancelRequest($html, $consignmentId);
    }

    /**
     * A cancel-request card (`li.sf-creq-item`) for this parcel on a tab page.
     *
     * @return array{item_id: string, status: string, label: string}|null
     */
    private function readCardCancelRequest(string $html, string $consignmentId): ?array
    {
        if (! preg_match_all('/<li\b[^>]*\bdata-sf-creq="(\d+)"[^>]*>(.*?)<\/li>/is', $html, $cards, PREG_SET_ORDER)) {
            return null;
        }

        $link = '#/user/consignment/'.preg_quote($consignmentId, '#').'(?!\d)#';
        foreach ($cards as $card) {
            if (preg_match($link, (string) $card[2])) {
                return ['item_id' => (string) $card[1]] + $this->cancelRequestState((string) $card[2]);
            }
        }

        return null;
    }

    /**
     * Older markup: `<cancel-request :item="{...}">`, shown while the request waits.
     *
     * @return array{item_id: string, status: string, label: string}|null
     */
    private function readLegacyCancelRequest(string $html, string $consignmentId): ?array
    {
        if (! preg_match_all('/<cancel-request\s+:item="([^"]+)"/i', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $encoded) {
            $payload = json_decode(html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5), true);
            if (! is_array($payload)) {
                continue;
            }
            $consignment = is_array($payload['consignment'] ?? null) ? $payload['consignment'] : [];
            if ((string) ($payload['consignment_id'] ?? $consignment['id'] ?? '') !== $consignmentId) {
                continue;
            }
            $id = trim((string) ($payload['id'] ?? ''));
            if ($id === '' || $id === $consignmentId || ! preg_match('/^\d+$/', $id)) {
                continue;
            }
            $status = self::PORTAL_TAB_STATUSES[(int) ($payload['verify_status'] ?? 0)] ?? '';

            return ['item_id' => $id, 'status' => $status, 'label' => $this->statusWord($status)];
        }

        return null;
    }

    /**
     * Pending while the portal still offers its decide buttons, else the badge it shows.
     *
     * @return array{status: string, label: string}
     */
    private function cancelRequestState(string $block): array
    {
        $actions = preg_match('/<div\b[^>]*\bdata-sf-creq-actions\b[^>]*>(.*?)<\/div>/is', $block, $match)
            ? (string) $match[1]
            : '';
        if (preg_match('/\bdata-sf-creq-decide="[12]"/', $actions)) {
            return ['status' => 'pending', 'label' => 'Pending'];
        }

        $label = trim((string) preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode(strip_tags($actions), ENT_QUOTES | ENT_HTML5)
        ));
        $word = strtolower($label);
        $status = match (true) {
            str_contains($word, 'resend') => 'resend_request',
            (bool) preg_match('/\bresent\b/', $word) => 'resent',
            str_contains($word, 'cancel') => 'cancelled',
            str_contains($word, 'confirm') => 'confirmed',
            default => '',
        };

        return ['status' => $status, 'label' => $label];
    }

    /**
     * The decide URL from the portal's `#sf-creq-config`, when it points at the portal.
     */
    private function confirmRequestPath(string $html, string $host): string
    {
        if (preg_match('/<script\b[^>]*\bid="sf-creq-config"[^>]*>(.*?)<\/script>/is', $html, $match)) {
            $config = json_decode(trim((string) $match[1]), true);
            $url = is_array($config) ? (string) ($config['url'] ?? '') : '';
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            $urlHost = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
            $portalHost = $urlHost === ''
                || $urlHost === strtolower($host)
                || $urlHost === 'steadfast.com.bd'
                || str_ends_with($urlHost, '.steadfast.com.bd');
            if ($portalHost && preg_match('#^/user/consignment/cancel-request/[a-z0-9-]+$#i', $path)) {
                return $path;
            }
        }

        return self::CONFIRM_REQUEST_PATH;
    }

    /**
     * The portal's own success test: `res.ok && Number(json.status) === 1`.
     *
     * @param  array<string, mixed>  $body
     */
    private function portalJsonOk(array $body): bool
    {
        $status = $body['status'] ?? null;

        return $status === true || (is_numeric($status) && (int) $status === 1);
    }

    /**
     * A request SteadFast already decided: fine when it matches the action, a 409 when it does not.
     *
     * @param  array{item_id: string, status: string, label: string}  $request
     * @return array{consignment_id: string, id: string, status: string, action: string, verify_status: int, already_decided: bool}
     */
    private function settledDecision(string $action, string $consignmentId, array $request, bool $alreadyDecided = true): array
    {
        $status = $request['status'];
        if ($status === '') {
            throw new RuntimeException(
                'Could not read the SteadFast decision on parcel '.$consignmentId.'. Nothing was changed; decide on the SteadFast portal.'
            );
        }

        $matches = $action === 'confirm_cancel'
            ? in_array($status, ['confirmed', 'cancelled'], true)
            : in_array($status, ['resend_request', 'resent'], true);
        if ($matches) {
            return $this->decisionResult($action, $consignmentId, $request['item_id'], $status, $alreadyDecided);
        }

        $label = $request['label'] !== '' ? $request['label'] : $this->statusWord($status);
        $verify = array_search($status, self::PORTAL_TAB_STATUSES, true);

        throw new SteadfastCancelRequestStateException(
            'SteadFast already shows this request as '.$label.'. Nothing was changed.',
            [
                'code' => 'already_decided',
                'consignment_id' => $consignmentId,
                'id' => $request['item_id'],
                'status' => $status,
                'verify_status' => $verify === false ? 0 : (int) $verify,
            ]
        );
    }

    /**
     * @return array{consignment_id: string, id: string, status: string, action: string, verify_status: int, already_decided: bool}
     */
    private function decisionResult(
        string $action,
        string $consignmentId,
        string $itemId,
        string $status,
        bool $alreadyDecided,
    ): array {
        $verify = array_search($status, self::PORTAL_TAB_STATUSES, true);

        return [
            'consignment_id' => $consignmentId,
            'id' => $itemId,
            'status' => $status,
            'action' => $action,
            'verify_status' => $verify === false ? 0 : (int) $verify,
            'already_decided' => $alreadyDecided,
        ];
    }

    private function statusWord(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'resend_request' => 'Resend requested',
            'cancelled' => 'Cancelled',
            'resent' => 'Resent',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
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
                'requested_at' => now(self::PORTAL_TIMEZONE)->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>, authoritative_statuses: list<string>}
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
            /** @var list<string> $authoritative */
            $authoritative = [];
            $targetStatus = $this->normalizeStatus($status);
            $dated = $date !== null && $date !== '';
            $deadlineSeconds = $mode === 'quick'
                ? self::PORTAL_QUICK_DEADLINE_SECONDS
                : self::PORTAL_LIST_DEADLINE_SECONDS;
            $deadlineAt = microtime(true) + $deadlineSeconds;

            foreach (self::PORTAL_TAB_ORDER as $index) {
                $slug = self::PORTAL_TAB_STATUSES[$index];
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

                $pageLimit = match (true) {
                    $mode === 'quick' => self::PORTAL_QUICK_PAGES_PER_TAB,
                    $slug === 'cancelled' => self::PORTAL_CANCELLED_PAGES,
                    default => null,
                };

                try {
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
                            pageLimit: $pageLimit,
                        );
                    } catch (\Throwable $th) {
                        if (! $dated || str_contains(strtolower($th->getMessage()), 'session expired')) {
                            throw $th;
                        }
                        // The dated list is its own portal route. If it fails, filter
                        // the newest pages of the tab by the requested day instead.
                        LogHelper::saveLog(
                            'Steadfast dated cancel-requests list failed',
                            $slug.' '.$date.': '.$th->getMessage()
                        );
                        $tab = $this->fetchCancelRequestTabPages(
                            $client,
                            $cookies,
                            $host,
                            $credentials,
                            $index,
                            $slug,
                            null,
                            requireAuthAssert: $index === 0,
                            deadlineAt: $deadlineAt,
                            pageLimit: self::DATED_FALLBACK_PAGES,
                        );
                        $tab['rows'] = array_values(array_filter(
                            $tab['rows'],
                            fn ($row) => str_starts_with($this->portalDateTime($row['requested_at'] ?? ''), (string) $date)
                        ));
                        $tab['complete'] = false;
                    }
                    $cookies = $tab['cookies'];
                    $rows = $tab['rows'];
                    // A dated scrape is one day, not the whole tab, so it must not
                    // authorize dropping local rows that fall on other days.
                    if (! $dated && ($tab['complete'] ?? false) === true) {
                        $authoritative[] = $slug;
                    }
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
                'authoritative_statuses' => $authoritative,
            ];
        });
    }

    /**
     * Walk SteadFast cancel-requests pagination (?page=1..N) for one status tab.
     *
     * The pager only links two pages either side of the current one, so the
     * page count comes from "Showing 1–20 of N" and from every page's links.
     *
     * @param  array{username: string, password: string}  $credentials
     * @param  array<string, string>  $cookies
     * @param  int|null  $pageLimit  Stop after this many pages (quick sync).
     * @return array{rows: list<array<string, mixed>>, cookies: array<string, string>, complete: bool}
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
        ?int $pageLimit = null,
    ): array {
        /** @var array<string, array<string, mixed>> $byConsignment */
        $byConsignment = [];
        $pageCap = max(1, min($pageLimit ?? self::MAX_PORTAL_PAGES_PER_TAB, self::MAX_PORTAL_PAGES_PER_TAB));
        $linkPath = $this->cancelRequestsTabLinkPath($tabIndex, $date);
        $maxPage = 1;
        $portalPages = 1;
        $total = null;
        $complete = true;
        $lastPageCount = 0;

        for ($pageNum = 1; $pageNum <= $maxPage; $pageNum++) {
            if ($deadlineAt !== null && microtime(true) >= $deadlineAt) {
                LogHelper::saveLog(
                    'Steadfast cancel-requests page deadline reached',
                    $slug.' stopped at page '.$pageNum
                );
                $complete = false;
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
                $complete = false;
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
                $complete = false;
                break;
            }

            $body = $response->body();
            $rows = $this->parseCancelRequestRows($body, $slug);

            // Page uses li.sf-creq-item, div.tbody-row, or <cancel-request> (not <tr>).
            // Markers with zero parsed rows are a scrape failure, not an empty queue.
            if ($rows === [] && $this->portalHtmlHasCancelRequestRows($body)) {
                throw new RuntimeException(
                    'Failed to parse Steadfast cancel-requests rows for status '.$slug
                    .' (page '.$pageNum.').'
                );
            }

            $lastPageCount = count($rows);

            foreach ($rows as $row) {
                $key = (string) ($row['consignment_id'] ?? '');
                if ($key === '' || isset($byConsignment[$key])) {
                    continue;
                }
                $byConsignment[$key] = $row;
            }

            $pageTotal = $this->detectCancelRequestsTotal($body);
            if ($pageTotal !== null) {
                $total = $pageTotal;
            }
            $portalPages = max(
                $portalPages,
                $this->detectCancelRequestsMaxPage($body, $linkPath),
                $total !== null ? (int) ceil($total / self::PORTAL_TAB_PAGE_SIZE) : 1,
            );
            $maxPage = min($portalPages, $pageCap);

            // Stop early when a page returns no rows (pagination lied / trailing empty).
            if ($pageNum > 1 && $rows === []) {
                $complete = false;
                break;
            }

            if ($pageNum < $maxPage) {
                usleep(120000);
            }
        }

        if ($portalPages > $pageCap) {
            $complete = false;
        }
        // Without the portal's total, a full last page may hide one more page.
        if ($complete && $total === null && $lastPageCount >= self::PORTAL_TAB_PAGE_SIZE) {
            $complete = false;
        }

        return [
            'rows' => array_values($byConsignment),
            'cookies' => $cookies,
            'complete' => $complete,
        ];
    }

    /**
     * One status tab. A date uses the portal's dated list, which shows that day only.
     */
    private function cancelRequestsTabPath(int $tabIndex, ?string $date, int $page = 1): string
    {
        if ($date !== null && $date !== '') {
            $query = ['type' => $tabIndex, 'date' => $date];
            if ($page > 1) {
                $query['page'] = $page;
            }

            return '/user/consignment/latest-cancelled/show?'.http_build_query($query);
        }

        $path = '/user/consignment/cancel-requests/show/'.$tabIndex;

        return $page > 1 ? $path.'?page='.$page : $path;
    }

    /** Path the pager links of this tab start with. */
    private function cancelRequestsTabLinkPath(int $tabIndex, ?string $date): string
    {
        return $date !== null && $date !== ''
            ? '/user/consignment/latest-cancelled/show'
            : '/user/consignment/cancel-requests/show/'.$tabIndex;
    }

    /**
     * Highest page this tab's pager links to. Links for other tabs do not count.
     */
    private function detectCancelRequestsMaxPage(string $html, string $linkPath): int
    {
        $max = 1;
        $html = str_replace('&amp;', '&', $html);
        $pattern = '#'.preg_quote($linkPath, '#').'\?(?:[^"\'\s<>]*&)?page=(\d+)#i';

        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $value) {
                $max = max($max, (int) $value);
            }
        }

        return $max;
    }

    /**
     * Total from the pager line "Showing 21–40 of 148". Single-page tabs have no pager.
     */
    private function detectCancelRequestsTotal(string $html): ?int
    {
        if (preg_match('/\bsf-pagination__info\b[^>]*>([^<]*)</i', $html, $info)
            && preg_match('/\bof\s+([\d,]+)/i', html_entity_decode((string) $info[1], ENT_QUOTES | ENT_HTML5), $count)
        ) {
            return (int) str_replace(',', '', $count[1]);
        }

        return null;
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
     * SteadFast cancel-requests UI renders li.sf-creq-item cards, or the older
     * div.tbody-row + <cancel-request :item="..."> markup. Classic <tr> is last.
     */
    private function portalHtmlHasCancelRequestRows(string $html): bool
    {
        return (bool) preg_match('/\bdata-sf-creq="/i', $html)
            || (bool) preg_match('/\bsf-creq-item\b/i', $html)
            || (bool) preg_match('/<cancel-request\b/i', $html)
            || (bool) preg_match('/class="[^"]*\btbody-row\b/i', $html);
    }

    /**
     * Current portal cards (Sep 2026): request id is data-sf-creq, parcel number is the consignment link.
     *
     * @return list<array<string, mixed>>
     */
    private function parseSfCreqCardRows(string $html, string $status): array
    {
        if (! preg_match_all('/<li\b([^>]*)>(.*?)<\/li>/is', $html, $cards, PREG_SET_ORDER)) {
            return [];
        }

        $rows = [];
        foreach ($cards as $card) {
            $attrs = (string) $card[1];
            $inner = (string) $card[2];
            if (! preg_match('/\bsf-creq-item\b/', $attrs) && ! preg_match('/\bdata-sf-creq="/', $attrs)) {
                continue;
            }
            if (! preg_match('/\bdata-sf-creq="(\d+)"/', $attrs, $idMatch)) {
                continue;
            }
            $requestId = (string) $idMatch[1];

            $consignmentId = '';
            if (preg_match('#/user/consignment/(\d{6,20})#', $inner, $consignmentMatch)) {
                $consignmentId = $consignmentMatch[1];
            } elseif (preg_match('/#(\d{6,20})\b/', $inner, $consignmentMatch)) {
                $consignmentId = $consignmentMatch[1];
            }
            if ($consignmentId === '' || $requestId === '') {
                continue;
            }

            $customer = '';
            if (preg_match('/<b>(.*?)<\/b>/is', $inner, $nameMatch)) {
                $customer = trim(html_entity_decode(strip_tags($nameMatch[1]), ENT_QUOTES | ENT_HTML5));
            }

            $reason = '';
            if (preg_match('/<p\b([^>]*\bsf-creq-item__reason\b[^>]*)>(.*?)<\/p>/is', $inner, $reasonMatch)) {
                $reasonAttrs = (string) $reasonMatch[1];
                $reasonText = trim(html_entity_decode(strip_tags((string) $reasonMatch[2]), ENT_QUOTES | ENT_HTML5));
                // The note's own time sits on a line after the quoted note: `"note"\n\n2026-09-26 10:49 am`.
                $reasonText = (string) preg_replace(
                    '/\s*\R\s*\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[ap]\.?m\.?)?\s*$/iu',
                    '',
                    $reasonText
                );
                $reasonText = trim($reasonText, " \t\n\r\0\x0B\"“”");
                $empty = str_contains($reasonAttrs, 'is-empty')
                    || preg_match('/^no reason given\.?$/i', $reasonText) === 1;
                if (! $empty) {
                    $reason = $reasonText;
                }
            }

            $charge = null;
            if (preg_match('/sf-creq-item__cod[^>]*>\s*COD\s*[^\d]*([\d,]+(?:\.\d+)?)/iu', $inner, $codMatch)) {
                $charge = (float) str_replace(',', '', $codMatch[1]);
            }

            $requestedAt = '';
            if (preg_match(
                '/Requested\s+(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4},\s+\d{1,2}:\d{2}(?::\d{2})?\s*[AP]M|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4},\s+\d{1,2}:\d{2}(?::\d{2})?\s*[AP]M)/i',
                $inner,
                $dateMatch
            )) {
                $requestedAt = trim(preg_replace('/\s+/', ' ', $dateMatch[1]) ?? $dateMatch[1]);
            }

            $rows[] = [
                'id' => $requestId,
                'consignment_id' => $consignmentId,
                'status' => $status,
                'reason' => $reason,
                'customer_name' => $customer,
                'charge' => $charge,
                'invoice' => '',
                'tracking_code' => '',
                'requested_at' => $requestedAt,
                'updated_at' => '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCancelRequestRows(string $html, string $status): array
    {
        $rows = $this->parseSfCreqCardRows($html, $status);

        // 1) Older Vue <cancel-request :item="{...}"> payload (full note + consignment).
        if ($rows === [] && preg_match_all('/<cancel-request\s+:item="([^"]+)"/i', $html, $itemMatches)) {
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
    private function packzyRequest(
        string $method,
        string $path,
        array $apiConfig,
        ?array $body = null,
        int $timeout = 45,
    ): array {
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
        ])->timeout(max(1, $timeout));

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
            'requested_at' => $this->portalDateTime(
                $merged['requested_at'] ?? $merged['created_at'] ?? $merged['createdAt'] ?? ''
            ),
            'updated_at' => $this->portalDateTime($merged['updated_at'] ?? $merged['updatedAt'] ?? ''),
        ];
    }

    /**
     * Portal text ("29 Sep 2026, 08:29 PM", "July 17, 2026, 07:09:52 PM") or
     * Packzy ISO UTC as Bangladesh time `Y-m-d H:i:s`. Unreadable values are ''.
     *
     * MySQL stores an unparsed portal string as 0000-00-00, so none may leave here.
     */
    private function portalDateTime(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $raw = trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', (string) $value));
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return '';
        }

        $zone = new \DateTimeZone(self::PORTAL_TIMEZONE);
        $read = static function (\DateTimeImmutable|false $date) use ($zone): string {
            if ($date === false) {
                return '';
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return '';
            }
            $year = (int) $date->format('Y');
            if ($year < 2000 || $year > 2100) {
                return '';
            }

            return $date->setTimezone($zone)->format('Y-m-d H:i:s');
        };

        try {
            // ISO or MySQL style: an offset (Z, +06:00) is converted, a bare value is already local.
            if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?\s*(?:Z|[+-]\d{2}:?\d{2})?$/i', $raw)) {
                return $read(new \DateTimeImmutable($raw, $zone));
            }

            foreach (['!j M Y, h:i A', '!j M Y, h:i:s A', '!F j, Y, h:i:s A', '!F j, Y, h:i A', '!F j, Y h:i:s A', '!F j, Y h:i A', '!j M Y'] as $format) {
                $parsed = $read(\DateTimeImmutable::createFromFormat($format, $raw, $zone));
                if ($parsed !== '') {
                    return $parsed;
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
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
