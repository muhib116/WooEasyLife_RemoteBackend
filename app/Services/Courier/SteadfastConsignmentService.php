<?php

namespace App\Services\Courier;

use App\LogHelper;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Steadfast consignment portal actions (delete In-Review / Courier Entry parcels).
 *
 * Portal UI (Vue app-CuVBsdgp.js):
 * - Delete only toggles local UI (enableStatus).
 * - Confirm posts FormData { consignment_id } to POST /user/consignment/remove-parcel
 *   then redirects the browser to /user/consignment/status/in-review.
 */
class SteadfastConsignmentService
{
    public function __construct(
        protected SteadfastPortalSessionClient $portal,
    ) {
    }

    /**
     * Delete a consignment that is still In Review on the Steadfast portal.
     *
     * @param  array{username: string, password: string}  $credentials
     * @return array{consignment_id: string, deleted: bool}
     */
    public function delete(string $consignmentId, array $credentials): array
    {
        $consignmentId = preg_replace('/\D+/', '', $consignmentId) ?? '';
        if ($consignmentId === '' || ! preg_match('/^\d{4,20}$/', $consignmentId)) {
            throw new RuntimeException('Invalid Steadfast consignment ID.');
        }

        return $this->portal->withSession($credentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($consignmentId, $credentials) {
            $pagePath = '/user/consignment/' . rawurlencode($consignmentId);
            $page = $client->get($pagePath, $host, $cookies, expectJson: false);
            $cookies = $client->absorbCookies($cookies, $page, $host, $credentials);
            $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'consignment page');

            // HTTP 404 only — never treat a bare "404" substring in JS/CSS as missing.
            if ($page->status() === 404) {
                throw new RuntimeException('Steadfast consignment not found (already deleted?).');
            }

            if ($this->looksLikeMissingConsignment($page->body(), $consignmentId)) {
                throw new RuntimeException('Steadfast consignment not found (already deleted?).');
            }

            if (! $this->pageAllowsDelete($page->body(), $consignmentId)) {
                throw new RuntimeException(
                    'Parcel already approved — cannot delete. Only In Review (Courier Entry) parcels can be deleted.'
                );
            }

            $csrf = $this->extractMetaCsrfToken($page->body())
                ?? $this->extractInputValue($page->body(), '_token')
                ?? $this->xsrfFromCookies($cookies);

            $referer = $this->absoluteUrl($host, $pagePath);
            $deleted = $this->confirmRemoveParcel(
                $client,
                $host,
                $cookies,
                $credentials,
                $consignmentId,
                $csrf,
                $referer
            );

            if (! $deleted) {
                // Keep older route guesses only as a last resort if Confirm endpoint changes.
                $deleted = $this->tryLegacyDeleteCandidates(
                    $client,
                    $host,
                    $cookies,
                    $credentials,
                    $consignmentId,
                    $csrf,
                    $referer
                );
            }

            if (! $deleted && $this->verifyDeleted($client, $host, $cookies, $credentials, $consignmentId)) {
                $deleted = true;
            }

            if (! $deleted) {
                throw new RuntimeException(
                    'Unable to delete Steadfast consignment. Try Confirm once more from the SteadFast portal.'
                );
            }

            return [
                'consignment_id' => $consignmentId,
                'deleted' => true,
            ];
        });
    }

    /**
     * Exact Confirm action from SteadFast merchant Vue:
     * axios.post('/user/consignment/remove-parcel', FormData{consignment_id}).
     *
     * @param  array<string, string>  $cookies
     * @param  array{username: string, password: string}  $credentials
     */
    private function confirmRemoveParcel(
        SteadfastPortalSessionClient $client,
        string $host,
        array &$cookies,
        array $credentials,
        string $consignmentId,
        ?string $csrf,
        string $referer
    ): bool {
        $path = '/user/consignment/remove-parcel';
        $payloadExact = ['consignment_id' => $consignmentId];
        $payloadWithToken = array_filter([
            'consignment_id' => $consignmentId,
            '_token' => $csrf,
        ], static fn ($v) => $v !== null && $v !== '');

        $attempts = [
            // Proven Steadfast scrape encoding (same as parcel-notes / return-requests).
            ['encoding' => 'form', 'payload' => $payloadExact],
            ['encoding' => 'form', 'payload' => $payloadWithToken],
            // Browser axios FormData style.
            ['encoding' => 'multipart', 'payload' => $payloadExact],
            ['encoding' => 'multipart', 'payload' => $payloadWithToken],
        ];

        $sawAuthLooking = false;

        for ($round = 0; $round < 2; $round++) {
            foreach ($attempts as $attempt) {
                $response = $this->postRemoveParcel(
                    $client,
                    $path,
                    $attempt['payload'],
                    $host,
                    $cookies,
                    $referer,
                    $attempt['encoding']
                );
                $cookies = $client->absorbCookies($cookies, $response, $host, $credentials);

                if ($this->responseIndicatesAuthLoss($client, $response)) {
                    $sawAuthLooking = true;
                    LogHelper::saveLog(
                        'Steadfast remove-parcel auth-looking response',
                        'HTTP ' . $response->status() . ' encoding=' . $attempt['encoding']
                        . ' body=' . mb_substr($response->body(), 0, 300)
                    );

                    continue;
                }

                if ($this->responseLooksSuccessfulDelete($response)
                    || $this->responseLooksLikeRemoveParcelSuccess($response)
                ) {
                    return true;
                }

                if (($response->successful() || $response->redirect())
                    && $this->verifyDeleted($client, $host, $cookies, $credentials, $consignmentId)
                ) {
                    return true;
                }
            }

            if ($round === 0 && $sawAuthLooking) {
                // CSRF mismatch often redirects to login. Refresh cookies once, then retry.
                $fresh = $client->get(
                    '/user/consignment/' . rawurlencode($consignmentId),
                    $host,
                    $cookies,
                    expectJson: false
                );
                $cookies = $client->absorbCookies($cookies, $fresh, $host, $credentials);
                if ($client->looksLikeLoginPage($fresh->body()) || $fresh->status() === 401) {
                    throw new RuntimeException('Steadfast portal session expired while deleting consignment.');
                }

                $csrf = $this->extractMetaCsrfToken($fresh->body())
                    ?? $this->extractInputValue($fresh->body(), '_token')
                    ?? $this->xsrfFromCookies($cookies);
                $attempts = [
                    ['encoding' => 'form', 'payload' => ['consignment_id' => $consignmentId]],
                    ['encoding' => 'form', 'payload' => array_filter([
                        'consignment_id' => $consignmentId,
                        '_token' => $csrf,
                    ], static fn ($v) => $v !== null && $v !== '')],
                    ['encoding' => 'multipart', 'payload' => ['consignment_id' => $consignmentId]],
                ];
                $sawAuthLooking = false;

                continue;
            }

            break;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $cookies
     */
    private function postRemoveParcel(
        SteadfastPortalSessionClient $client,
        string $path,
        array $payload,
        string $host,
        array $cookies,
        string $referer,
        string $encoding
    ): Response {
        // Do not follow redirects: CSRF failures often 302 to /login and were
        // misreported as "session expired". Success may 302 to in-review list.
        if ($encoding === 'multipart') {
            return $client->postMultipartFormData($path, $payload, $host, $cookies, $referer, false);
        }

        return $client->postMultipart($path, $payload, $host, $cookies, $referer, false);
    }

    /**
     * @param  array<string, string>  $cookies
     * @param  array{username: string, password: string}  $credentials
     */
    private function tryLegacyDeleteCandidates(
        SteadfastPortalSessionClient $client,
        string $host,
        array &$cookies,
        array $credentials,
        string $consignmentId,
        ?string $csrf,
        string $referer
    ): bool {
        $candidates = $this->fallbackDeleteCandidates($consignmentId, $csrf);

        foreach ($candidates as $candidate) {
            $path = (string) ($candidate['path'] ?? '');
            if ($path === '' || $path === '/user/consignment/remove-parcel') {
                continue;
            }

            $payload = is_array($candidate['payload'] ?? null) ? $candidate['payload'] : [];
            $method = strtoupper((string) ($candidate['method'] ?? 'POST'));

            $response = $this->sendDeleteRequest(
                $client,
                $method,
                $path,
                $payload,
                $host,
                $cookies,
                $referer
            );
            $cookies = $client->absorbCookies($cookies, $response, $host, $credentials);

            if ($this->responseIndicatesAuthLoss($client, $response)) {
                continue;
            }

            if ($this->responseLooksSuccessfulDelete($response)) {
                return true;
            }

            if (($response->successful() || $response->redirect())
                && $this->verifyDeleted($client, $host, $cookies, $credentials, $consignmentId)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $cookies
     */
    private function sendDeleteRequest(
        SteadfastPortalSessionClient $client,
        string $method,
        string $path,
        array $payload,
        string $host,
        array $cookies,
        string $referer
    ): Response {
        if ($method === 'DELETE') {
            try {
                return $client->deleteJson($path, $host, $cookies, $referer);
            } catch (\Throwable) {
                $payload['_method'] = 'DELETE';

                return $client->postMultipart($path, $payload, $host, $cookies, $referer);
            }
        }

        // Prefer proven asForm encoding used by other Steadfast scrapes.
        return $client->postMultipart($path, $payload, $host, $cookies, $referer);
    }

    private function responseIndicatesAuthLoss(SteadfastPortalSessionClient $client, Response $response): bool
    {
        if ($response->status() === 401 || $response->status() === 419) {
            return true;
        }

        if ($client->looksLikeLoginPage($response->body())) {
            return true;
        }

        if ($response->redirect()) {
            $location = (string) ($response->header('Location') ?? '');
            if (preg_match('#/(?:merchant/)?login(?:\?|/|$)#i', $location)) {
                return true;
            }
        }

        return false;
    }

    /**
     * SteadFast Confirm success: axios then redirects to /user/consignment/status/in-review.
     */
    private function responseLooksLikeRemoveParcelSuccess(Response $response): bool
    {
        if ($response->redirect()) {
            $location = (string) ($response->header('Location') ?? '');
            if (preg_match('#/user/consignment/status/in-review#i', $location)) {
                return true;
            }
        }

        $body = $response->body();
        if (($response->successful() || $response->redirect())
            && preg_match('#/user/consignment/status/in-review#i', $body)
        ) {
            return true;
        }

        // Empty/minimal 2xx from axios Confirm is common; caller still verifies.
        return false;
    }

    private function responseLooksSuccessfulDelete(Response $response): bool
    {
        $body = $response->json();
        if (is_array($body)) {
            if (($body['status'] ?? null) === true
                || (int) ($body['status'] ?? 0) === 1
                || ($body['success'] ?? false) === true
                || ! empty($body['deleted'])
            ) {
                return true;
            }

            $message = strtolower(trim((string) ($body['message'] ?? '')));
            if ($message !== '' && preg_match('/\b(deleted|removed|success)\b/', $message)) {
                return ! $this->messageLooksLikeWrongRoute($message);
            }
        }

        return false;
    }

    private function messageLooksLikeWrongRoute(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'not found')
            || str_contains($lower, '404')
            || str_contains($lower, 'method not allowed')
            || str_contains($lower, 'page expired');
    }

    /**
     * @param  array<string, string>  $cookies
     * @param  array{username: string, password: string}  $credentials
     */
    private function verifyDeleted(
        SteadfastPortalSessionClient $client,
        string $host,
        array &$cookies,
        array $credentials,
        string $consignmentId
    ): bool {
        $pagePath = '/user/consignment/' . rawurlencode($consignmentId);
        $page = $client->get($pagePath, $host, $cookies, expectJson: false);
        $cookies = $client->absorbCookies($cookies, $page, $host, $credentials);

        if ($page->status() === 404) {
            return true;
        }

        if ($client->looksLikeLoginPage($page->body())) {
            throw new RuntimeException('Steadfast portal session expired while verifying delete.');
        }

        if ($this->looksLikeMissingConsignment($page->body(), $consignmentId)) {
            return true;
        }

        // Still showing In Review + Confirm/Delete means the parcel was NOT deleted.
        if ($this->pageAllowsDelete($page->body(), $consignmentId)) {
            return false;
        }

        // Details gone / no longer In Review after a successful delete redirect.
        return ! $this->pageHasConsignmentDetails($page->body(), $consignmentId);
    }

    /**
     * Portal Delete/Confirm controls exist only while status is In Review.
     */
    private function pageAllowsDelete(string $html, string $consignmentId = ''): bool
    {
        $inReview = (bool) preg_match('/in[\s_-]*review/i', $html);
        $notApproved = (bool) preg_match('/approved\s*at\s*:\s*no\s*yet/i', $html);

        // Exact portal markup from SteadFast: Confirm button after Delete click.
        $hasConfirm = (bool) preg_match(
            '/<button[^>]*class="[^"]*btn-danger[^"]*"[^>]*>\s*Confirm\s*<\/button>/i',
            $html
        );
        $hasDeleteLabel = (bool) preg_match('/\bDelete\b/i', $html);

        if ($inReview && ($hasConfirm || $hasDeleteLabel)) {
            return true;
        }

        if ($inReview && $notApproved) {
            return true;
        }

        if ($this->pageHasConsignmentDetails($html, $consignmentId) && $inReview) {
            return true;
        }

        return (bool) preg_match(
            '/consignment[^"\']{0,40}delete|delete[^"\']{0,40}consignment|_method["\']?\s*[:=]\s*["\']?DELETE/i',
            $html
        );
    }

    /**
     * Live parcel page markers from SteadFast HTML (see merchant portal parcel-details).
     */
    private function pageHasConsignmentDetails(string $html, string $consignmentId = ''): bool
    {
        if (stripos($html, 'parcel-details') !== false
            || stripos($html, 'parcel-information') !== false
            || stripos($html, 'parcel-short-info') !== false
        ) {
            return true;
        }

        if ($consignmentId !== ''
            && preg_match('/Id\s*:\s*<span[^>]*>\s*' . preg_quote($consignmentId, '/') . '\s*<\/span>/i', $html)
        ) {
            return true;
        }

        if (preg_match('/Tracking\s*Code/i', $html) && preg_match('/in[\s_-]*review/i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * True only for clear missing-page copy — never a bare "404" digit sequence in assets/JS.
     */
    private function looksLikeMissingConsignment(string $html, string $consignmentId = ''): bool
    {
        if ($this->pageHasConsignmentDetails($html, $consignmentId)) {
            return false;
        }

        return (bool) preg_match(
            '/consignment\s+(not\s+found|does\s+not\s+exist)|no\s+consignment\s+(found|available|exists)|record\s+not\s+found|sorry[,!]?\s+this\s+page\s+(could\s+not\s+be\s+found|doesn.?t\s+exist)/i',
            $html
        );
    }

    /**
     * @return list<array{path: string, payload: array<string, mixed>, method?: string}>
     */
    private function discoverDeleteCandidates(string $html, string $consignmentId, ?string $csrf): array
    {
        $candidates = [];

        // Vue/axios route strings sometimes appear in the bootstrapped page HTML.
        if (preg_match_all(
            '#["\'](/user/(?:consignment|delete-parcel)[^"\']*' . preg_quote($consignmentId, '#') . '[^"\']*)["\']#i',
            $html,
            $routeMatches
        )) {
            foreach (array_unique($routeMatches[1]) as $route) {
                if (! preg_match('/delete|destroy|remove|confirm/i', $route)) {
                    continue;
                }
                $candidates[] = [
                    'path' => $route,
                    'payload' => array_filter([
                        '_token' => $csrf,
                        'consignment_id' => $consignmentId,
                        'confirm' => '1',
                    ], static fn ($v) => $v !== null && $v !== ''),
                    'method' => 'POST',
                ];
            }
        }

        if (preg_match_all('/<form\b[^>]*>(.*?)<\/form>/is', $html, $forms, PREG_SET_ORDER)) {
            foreach ($forms as $form) {
                $inner = $form[1] ?? '';
                $blob = substr($form[0], 0, 500) . ' ' . $inner;
                if (! preg_match('/delete|_method["\']?\s*[:=]\s*["\']?DELETE|destroy|confirm/i', $blob)) {
                    continue;
                }

                $action = '';
                if (preg_match('/<form[^>]+action=["\']([^"\']+)["\']/i', $form[0], $m)) {
                    $action = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
                }
                $path = $this->pathFromAction($action, $consignmentId);
                if ($path === '') {
                    continue;
                }

                $payload = ['_token' => $csrf, 'consignment_id' => $consignmentId];
                if (preg_match_all('/<input\b[^>]*>/i', $inner, $inputs)) {
                    foreach ($inputs[0] as $inputHtml) {
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
                if (! isset($payload['_method']) && preg_match('/delete|destroy/i', $blob)) {
                    $payload['_method'] = 'DELETE';
                }

                $candidates[] = [
                    'path' => $path,
                    'payload' => array_filter($payload, static fn ($v) => $v !== null && $v !== ''),
                    'method' => 'POST',
                ];
            }
        }

        return $candidates;
    }

    /**
     * Ordered fallbacks matching SteadFast merchant portal URL conventions
     * (edit uses /user/edit-parcel/{id}; delete Confirm posts nearby routes).
     *
     * @return list<array{path: string, payload: array<string, mixed>, method?: string}>
     */
    private function fallbackDeleteCandidates(string $consignmentId, ?string $csrf): array
    {
        $id = rawurlencode($consignmentId);
        $basePayload = array_filter([
            '_token' => $csrf,
            'consignment_id' => $consignmentId,
        ], static fn ($v) => $v !== null && $v !== '');

        $confirmPayload = $basePayload + [
            'confirm' => '1',
            'action' => 'confirm',
            'status' => 'confirm',
        ];

        $deleteMethodPayload = $basePayload + ['_method' => 'DELETE'];

        return [
            // Current SteadFast Vue flow: Delete only reveals the confirmation UI.
            // Confirm submits FormData containing only consignment_id to this route.
            [
                'path' => '/user/consignment/remove-parcel',
                'payload' => ['consignment_id' => $consignmentId],
                'method' => 'POST',
            ],
            // Most likely Confirm targets (Vue axios).
            ['path' => "/user/consignment/{$id}/delete", 'payload' => $confirmPayload, 'method' => 'POST'],
            ['path' => "/user/consignment/{$id}/destroy", 'payload' => $confirmPayload, 'method' => 'POST'],
            ['path' => "/user/consignment/delete/{$id}", 'payload' => $confirmPayload, 'method' => 'POST'],
            ['path' => "/user/delete-parcel/{$id}", 'payload' => $confirmPayload, 'method' => 'POST'],
            ['path' => "/user/consignment/{$id}/confirm", 'payload' => $confirmPayload, 'method' => 'POST'],
            ['path' => "/user/consignment/confirm-delete/{$id}", 'payload' => $confirmPayload, 'method' => 'POST'],
            ['path' => "/user/consignment/{$id}", 'payload' => $deleteMethodPayload, 'method' => 'POST'],
            ['path' => "/user/consignment/{$id}", 'payload' => [], 'method' => 'DELETE'],
            ['path' => "/consignments/{$id}", 'payload' => $deleteMethodPayload, 'method' => 'POST'],
            ['path' => "/consignments/{$id}", 'payload' => [], 'method' => 'DELETE'],
            ['path' => "/consignments/{$id}/delete", 'payload' => $confirmPayload, 'method' => 'POST'],
        ];
    }

    private function pathFromAction(string $action, string $consignmentId): string
    {
        $action = trim($action);
        if ($action === '' || $action === '#') {
            return '/user/consignment/' . rawurlencode($consignmentId);
        }

        if (str_starts_with($action, 'http://') || str_starts_with($action, 'https://')) {
            $parts = parse_url($action);
            $path = (string) ($parts['path'] ?? '');
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';

            return $path !== '' ? $path . $query : '';
        }

        return str_starts_with($action, '/') ? $action : '/' . $action;
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

    /**
     * @param  array<string, string>  $cookies
     */
    private function xsrfFromCookies(array $cookies): ?string
    {
        $raw = $cookies['XSRF-TOKEN'] ?? '';
        if ($raw === '') {
            return null;
        }

        $decoded = urldecode($raw);

        return $decoded !== '' ? $decoded : $raw;
    }

    private function absoluteUrl(string $host, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return 'https://' . $host . (str_starts_with($path, '/') ? $path : '/' . $path);
    }
}
