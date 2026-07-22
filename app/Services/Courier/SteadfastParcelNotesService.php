<?php

namespace App\Services\Courier;

use App\LogHelper;
use RuntimeException;

class SteadfastParcelNotesService
{
    /** Fields that must not be wiped when updating the merchant note. */
    private const CRITICAL_EDIT_FIELDS = [
        'cus_name',
        'cus_phone',
        'cus_address',
    ];

    public function __construct(
        private SteadfastPortalSessionClient $portal,
    ) {}

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array{
     *   consignment_id: string,
     *   merchant_note: ?string,
     *   cus_address: ?string,
     *   cod_amount: float|int|null,
     *   notes: list<array{message: string, at: ?string, source: string}>,
     *   rider: ?array{name: string, phone: string}
     * }
     */
    public function fetchNotes(string $consignmentId, array $credentials, ?string $trackingCode = null): array
    {
        $consignmentId = $this->normalizeConsignmentId($consignmentId);
        $trackingCode = $this->normalizeTrackingCode($trackingCode);

        return $this->portal->withSession($credentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($consignmentId, $trackingCode, $credentials) {
            $bundle = $this->fetchTrackingNotesFromConsignment(
                $client,
                $host,
                $cookies,
                $consignmentId,
                $trackingCode,
                $credentials
            );
            $editFields = $this->fetchEditParcelFields($client, $host, $cookies, $consignmentId);

            return [
                'consignment_id' => $consignmentId,
                'merchant_note' => $editFields['note'],
                'cus_address' => $editFields['cus_address'],
                'cod_amount' => $editFields['cod_amount'],
                'notes' => $bundle['notes'],
                'rider' => $bundle['rider'],
            ];
        });
    }

    /**
     * @param  array{username: string, password: string}  $credentials
     * @param  array{note?: string, cus_address?: string, cod_amount?: float|int|string|null, customer_name?: string, customer_phone?: string}  $overrides
     * @return array{consignment_id: string, note: string, cus_address: string, cod_amount: float|int|string, customer_name?: string, customer_phone?: string}
     */
    public function updateMerchantNote(string $consignmentId, string $note, array $credentials, array $overrides = []): array
    {
        $consignmentId = $this->normalizeConsignmentId($consignmentId);
        $note = trim($note);
        $cusAddress = array_key_exists('cus_address', $overrides)
            ? trim((string) $overrides['cus_address'])
            : null;
        $codAmount = array_key_exists('cod_amount', $overrides)
            ? $overrides['cod_amount']
            : null;
        $customerName = array_key_exists('customer_name', $overrides)
            ? trim((string) $overrides['customer_name'])
            : null;
        $customerPhone = array_key_exists('customer_phone', $overrides)
            ? trim((string) $overrides['customer_phone'])
            : null;

        if (
            $note === ''
            && ($cusAddress === null || $cusAddress === '')
            && $codAmount === null
            && ($customerName === null || $customerName === '')
            && ($customerPhone === null || $customerPhone === '')
        ) {
            throw new RuntimeException('Provide a note, address, COD amount, or customer details to update.');
        }

        if ($customerName !== null && $customerName === '') {
            throw new RuntimeException('Customer name cannot be empty.');
        }

        if (mb_strlen($note) > 500) {
            throw new RuntimeException('Note must be 500 characters or fewer.');
        }

        if ($cusAddress !== null && $cusAddress === '') {
            throw new RuntimeException('Address cannot be empty.');
        }

        if ($cusAddress !== null && mb_strlen($cusAddress) > 500) {
            throw new RuntimeException('Address must be 500 characters or fewer.');
        }

        if ($codAmount !== null) {
            if (! is_numeric($codAmount)) {
                throw new RuntimeException('COD amount must be a number.');
            }
            if ((float) $codAmount < 0) {
                throw new RuntimeException('COD amount cannot be negative.');
            }
        }

        return $this->portal->withSession($credentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($consignmentId, $note, $cusAddress, $codAmount, $customerName, $customerPhone, $credentials) {
            $editPath = '/user/edit-parcel/' . rawurlencode($consignmentId);
            $page = $client->get($editPath, $host, $cookies, expectJson: false);
            // Steadfast/Laravel rotates XSRF on page load — must use the fresh cookie for POST.
            $cookies = $client->absorbCookies($cookies, $page, $host, $credentials);
            $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'edit-parcel');

            if (! $page->successful()) {
                throw new RuntimeException('Unable to load Steadfast edit-parcel page (HTTP ' . $page->status() . ').');
            }

            $payload = $this->buildSingleUpdatePayload(
                $page->body(),
                $consignmentId,
                // Empty note with address/COD-only update keeps the portal note.
                ($note === '' && ($cusAddress !== null || $codAmount !== null || $customerName !== null || $customerPhone !== null)) ? null : $note,
                $cusAddress,
                $codAmount,
                $customerName,
                $customerPhone
            );
            if ($payload === null) {
                throw new RuntimeException('Unable to parse Steadfast edit-parcel consignment data safely.');
            }

            // Include CSRF body token when the page exposes one (belt-and-suspenders with header).
            $csrf = $this->extractMetaCsrfToken($page->body());
            if ($csrf !== null) {
                $payload['_token'] = $csrf;
            }

            $response = $client->postMultipart(
                '/user/consignment/single/update',
                $payload,
                $host,
                $cookies,
                $this->absoluteSteadfastUrl($host, $editPath)
            );
            $cookies = $client->absorbCookies($cookies, $response, $host, $credentials);

            if ($response->status() === 401 || $response->status() === 419) {
                LogHelper::saveLog(
                    'Steadfast parcel note update auth failure',
                    'HTTP ' . $response->status() . ' body=' . mb_substr($response->body(), 0, 300)
                );

                throw new RuntimeException('Steadfast portal session expired while saving note.');
            }

            if ($client->looksLikeLoginPage($response->body())) {
                throw new RuntimeException('Steadfast portal session expired while saving note.');
            }

            $saved = [
                'consignment_id' => $consignmentId,
                'note' => (string) ($payload['note'] ?? $note),
                'cus_address' => (string) ($payload['cus_address'] ?? ''),
                'cod_amount' => $payload['cod_amount'] ?? 0,
                'customer_name' => (string) ($payload['cus_name'] ?? ''),
                'customer_phone' => (string) ($payload['cus_phone'] ?? ''),
            ];

            $body = $response->json();
            if (! is_array($body)) {
                // Some Steadfast builds return HTML success redirects; treat non-JSON carefully.
                if ($response->successful() || $response->redirect()) {
                    return $saved;
                }

                LogHelper::saveLog(
                    'Steadfast parcel note update unexpected body',
                    'HTTP ' . $response->status() . ' body=' . mb_substr($response->body(), 0, 400)
                );

                throw new RuntimeException('Steadfast note update returned an unexpected response.');
            }

            $status = $body['status'] ?? null;
            if ((int) $status === 1) {
                return $saved;
            }

            if ((int) $status === 2) {
                throw new RuntimeException(
                    (string) ($body['message'] ?? 'Possible duplicate Steadfast note submission. Try again later.')
                );
            }

            $message = trim((string) ($body['message'] ?? ''));
            if ($message === '' && isset($body['errors']) && is_array($body['errors'])) {
                $parts = [];
                array_walk_recursive($body['errors'], function ($value) use (&$parts) {
                    if (is_string($value) && trim($value) !== '') {
                        $parts[] = trim($value);
                    }
                });
                $message = implode(' ', $parts);
            }

            LogHelper::saveLog(
                'Steadfast parcel note update failed',
                'status=' . json_encode($status) . ' consignment=' . $consignmentId . ' message=' . $message
            );

            throw new RuntimeException($message !== '' ? $message : 'Failed to update Steadfast parcel note.');
        });
    }

    private function extractMetaCsrfToken(string $html): ?string
    {
        if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/name=["\']_token["\'][^>]*value=["\']([^"\']+)["\']/i', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']_token["\']/i', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function absoluteSteadfastUrl(string $host, string $path): string
    {
        return 'https://' . $host . $path;
    }

    /**
     * @param  array<string, string>  $cookies
     * @param  array{username: string, password: string}|null  $credentials
     * @return array{
     *   notes: list<array{message: string, at: ?string, source: string, rider_name?: ?string, rider_phone?: ?string}>,
     *   rider: ?array{name: string, phone: string}
     * }
     */
    private function fetchTrackingNotesFromConsignment(
        SteadfastPortalSessionClient $client,
        string $host,
        array $cookies,
        string $consignmentId,
        ?string $trackingCode = null,
        ?array $credentials = null,
    ): array {
        // Consignment page validates the session and may expose a tracking code.
        // The Tracking Updates list is Vue-rendered in the browser, so raw HTML often
        // lacks .step nodes — never treat a weak HTML parse as final.
        $path = '/user/consignment/' . rawurlencode($consignmentId);
        $page = $client->get($path, $host, $cookies, expectJson: false);
        $cookies = $client->absorbCookies($cookies, $page, $host, $credentials);
        $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'consignment');

        if (! $page->successful()) {
            throw new RuntimeException('Unable to load Steadfast consignment page (HTTP ' . $page->status() . ').');
        }

        $html = $page->body();
        $rider = $this->extractRiderFromHtml($html);

        // 1) Structural DOM parse (only when Steadfast SSR/includes the steps).
        $fromSteps = $this->parseTrackingStepsHtml($html);

        // 2) Authenticated track JSON — this is what their UI uses for the full timeline.
        $fromApi = [];
        foreach ($this->candidateTrackCodes($html, $consignmentId, $trackingCode) as $trackCode) {
            $bundle = $this->fetchTrackingsViaTrackEndpoint($client, $host, $cookies, $trackCode);
            if (($bundle['notes'] ?? []) !== []) {
                $fromApi = $bundle['notes'];
                if ($rider === null && is_array($bundle['rider'] ?? null)) {
                    $rider = $bundle['rider'];
                }
                break;
            }
        }

        $notes = [];
        if (count($fromApi) > count($fromSteps)) {
            $notes = $fromApi;
        } elseif ($fromSteps !== []) {
            $notes = $fromSteps;
        } elseif ($fromApi !== []) {
            $notes = $fromApi;
        } else {
            $notes = $this->parseTrackingHtml($html);
        }

        if ($rider === null) {
            $rider = $this->inferRiderFromNotes($notes);
        }

        return [
            'notes' => $notes,
            'rider' => $rider,
        ];
    }

    /**
     * @return list<string>
     */
    private function candidateTrackCodes(string $html, string $consignmentId, ?string $trackingCode = null): array
    {
        $codes = [];

        if (is_string($trackingCode) && $trackingCode !== '') {
            $codes[] = $trackingCode;
        }

        foreach ([
            '/Tracking\s*Code\s*:?\s*<\/?(?:span|strong|b|td|p|div)[^>]*>\s*([A-Za-z0-9_-]{4,40})/i',
            '/Tracking\s*Code\s*:?\s*([A-Za-z0-9_-]{4,40})/i',
            '/"track_id"\s*:\s*"([A-Za-z0-9_-]{4,40})"/i',
            '/\/user\/tracking\/([A-Za-z0-9_-]{4,40})/i',
            '/\/user\/track\/([A-Za-z0-9_-]{4,40})/i',
            '/\/t\/([A-Za-z0-9_-]{4,40})/i',
        ] as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $code) {
                    $codes[] = (string) $code;
                }
            }
        }

        $codes[] = $consignmentId;

        // Keep values as strings (numeric IDs must not become int array keys).
        $unique = [];
        $seen = [];
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $unique[] = $code;
        }

        return $unique;
    }

    /**
     * @param  array<string, string>  $cookies
     * @return array{
     *   notes: list<array{message: string, at: ?string, source: string, rider_name?: ?string, rider_phone?: ?string}>,
     *   rider: ?array{name: string, phone: string}
     * }
     */
    private function fetchTrackingsViaTrackEndpoint(
        SteadfastPortalSessionClient $client,
        string $host,
        array $cookies,
        string $trackCode,
    ): array {
        $empty = ['notes' => [], 'rider' => null];
        $trackCode = trim($trackCode);
        if ($trackCode === '') {
            return $empty;
        }

        try {
            $response = $client->get(
                '/user/track/' . rawurlencode($trackCode),
                $host,
                $cookies,
                expectJson: true
            );
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast track endpoint error', $trackCode . ': ' . $th->getMessage());

            return $empty;
        }

        if ($response->status() === 401 || $response->status() === 419) {
            throw new RuntimeException('Steadfast portal session expired.');
        }

        if (! $response->successful()) {
            return $empty;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $fromHtml = $this->parseTrackingStepsHtml($response->body());
            if ($fromHtml !== []) {
                return [
                    'notes' => $fromHtml,
                    'rider' => $this->extractRiderFromHtml($response->body())
                        ?? $this->inferRiderFromNotes($fromHtml),
                ];
            }

            return [
                'notes' => $this->parseTrackingsFromEmbeddedJson($response->body()),
                'rider' => $this->extractRiderFromHtml($response->body()),
            ];
        }

        if (($payload['status'] ?? null) === 0) {
            return $empty;
        }

        $trackings = $payload['trackings'] ?? null;
        $notes = is_array($trackings) && $trackings !== []
            ? $this->mapTrackingsArray($trackings)
            : [];

        $rider = $this->normalizeRider(
            data_get($payload, 'result.rider')
                ?? data_get($payload, 'consignment.rider')
                ?? data_get($payload, 'result.current_rider')
        );

        if ($rider === null) {
            $rider = $this->inferRiderFromNotes($notes);
        }

        return [
            'notes' => $notes,
            'rider' => $rider,
        ];
    }

    private function normalizeTrackingCode(?string $trackingCode): ?string
    {
        $trackingCode = trim((string) $trackingCode);
        if ($trackingCode === '' || strtolower($trackingCode) === 'not-available') {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_-]{4,40}$/', $trackingCode)) {
            return null;
        }

        return $trackingCode;
    }

    /**
     * @param  array<string, string>  $cookies
     * @return array{note: ?string, cus_address: ?string, cod_amount: float|int|null}
     */
    private function fetchEditParcelFields(
        SteadfastPortalSessionClient $client,
        string $host,
        array $cookies,
        string $consignmentId,
    ): array {
        $empty = [
            'note' => null,
            'cus_address' => null,
            'cod_amount' => null,
        ];

        try {
            $path = '/user/edit-parcel/' . rawurlencode($consignmentId);
            $page = $client->get($path, $host, $cookies, expectJson: false);
            $cookies = $client->absorbCookies($cookies, $page, $host);

            if (! $page->successful() || $client->looksLikeLoginPage($page->body())) {
                return $empty;
            }

            $consignment = $this->extractEditParcelConsignment($page->body());
            if (! is_array($consignment)) {
                $note = $this->extractTextareaValue($page->body(), 'note');
                $address = $this->extractTextareaValue($page->body(), 'cus_address')
                    ?? $this->extractTextareaValue($page->body(), 'address')
                    ?? $this->extractInputValue($page->body(), 'cus_address')
                    ?? $this->extractInputValue($page->body(), 'address');

                return [
                    'note' => $note,
                    'cus_address' => is_string($address) && trim($address) !== '' ? trim($address) : null,
                    'cod_amount' => $this->normalizeCodAmount(
                        $this->extractInputValue($page->body(), 'cod_amount')
                            ?? $this->extractInputValue($page->body(), 'cod')
                    ),
                ];
            }

            $note = trim((string) ($consignment['note'] ?? ''));
            if ($note === '' && isset($consignment['additional_data']) && is_array($consignment['additional_data'])) {
                $note = trim((string) ($consignment['additional_data']['note'] ?? ''));
            }

            $address = trim((string) ($consignment['address'] ?? $consignment['cus_address'] ?? ''));
            $cod = $this->normalizeCodAmount($consignment['cod'] ?? $consignment['cod_amount'] ?? null);

            return [
                'note' => $note === '' ? null : $note,
                'cus_address' => $address === '' ? null : $address,
                'cod_amount' => $cod,
            ];
        } catch (\Throwable $th) {
            LogHelper::saveLog('Steadfast edit-parcel fields fetch error', $th->getMessage());

            return $empty;
        }
    }

    /**
     * @deprecated Prefer fetchEditParcelFields()
     * @param  array<string, string>  $cookies
     */
    private function fetchMerchantNoteFromEditParcel(
        SteadfastPortalSessionClient $client,
        string $host,
        array $cookies,
        string $consignmentId,
    ): ?string {
        return $this->fetchEditParcelFields($client, $host, $cookies, $consignmentId)['note'];
    }

    private function normalizeCodAmount(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if (floor($float) == $float) {
            return (int) $float;
        }

        return round($float, 2);
    }

    private function extractInputValue(string $html, string $name): ?string
    {
        $quoted = preg_quote($name, '/');
        if (preg_match(
            '/<input[^>]*name=["\']' . $quoted . '["\'][^>]*value=["\']([^"\']*)["\']/i',
            $html,
            $match
        )) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match(
            '/<input[^>]*value=["\']([^"\']*)["\'][^>]*name=["\']' . $quoted . '["\']/i',
            $html,
            $match
        )) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function assertAuthenticatedHtml(
        SteadfastPortalSessionClient $client,
        string $html,
        int $status,
        string $context,
    ): void {
        if ($status === 401 || $status === 419 || $client->looksLikeLoginPage($html)) {
            throw new RuntimeException('Steadfast portal session expired.');
        }

        if ($status === 404) {
            throw new RuntimeException("Steadfast {$context} page not found for this consignment.");
        }

        if ($status === 403) {
            throw new RuntimeException("Steadfast denied access to this {$context} page.");
        }
    }

    private function normalizeConsignmentId(string $consignmentId): string
    {
        $consignmentId = trim($consignmentId);

        if ($consignmentId === '' || $consignmentId === 'not-available') {
            throw new RuntimeException('Consignment ID is required.');
        }

        // Steadfast consignment IDs are numeric; keep path-safe.
        if (! preg_match('/^\d{4,20}$/', $consignmentId)) {
            throw new RuntimeException('Invalid Steadfast consignment ID.');
        }

        return $consignmentId;
    }

    /**
     * @return list<array{message: string, at: ?string, source: string}>
     */
    private function parseTrackingHtml(string $html): array
    {
        // Real Steadfast markup: date + time are separate <p> tags inside .date-time,
        // message is <p class="txt-black"> inside .tracking_content.
        $fromSteps = $this->parseTrackingStepsHtml($html);
        if ($fromSteps !== []) {
            return $fromSteps;
        }

        $fromJson = $this->parseTrackingsFromEmbeddedJson($html);
        if ($fromJson !== []) {
            return $fromJson;
        }

        $text = $this->htmlToTimelineText($html);
        // Accept both "Jul 20, 2026, 02:17 pm" and split "Jul 20, 2026" + "02:17 pm".
        $datePattern = '/((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},\s+\d{4})(?:[,\s]+|[\s\n]+)(\d{1,2}:\d{2}\s*(?:am|pm))/i';

        if (! preg_match_all($datePattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return $this->parseRiderNotesFallback($text);
        }

        $notes = [];
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $atRaw = trim($matches[1][$i][0] . ', ' . $matches[2][$i][0]);
            $start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($text);
            $chunk = substr($text, $start, max(0, $end - $start));
            $message = $this->normalizeTimelineMessage($chunk);

            // Drop duplicated inner date stamps that appear after each message.
            $message = preg_replace(
                '/^(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},\s+\d{4}(?:[,\s]+|[\s\n]+)\d{1,2}:\d{2}\s*(?:am|pm)\s*/iu',
                '',
                $message
            ) ?? $message;
            $message = $this->normalizeTimelineMessage($message);

            if ($message === '' || preg_match('/^tracking updates$/i', $message)) {
                continue;
            }

            $notes[] = [
                'message' => $message,
                'at' => $this->normalizeSteadfastDatetime($atRaw),
                'source' => $this->classifySource($message),
            ];
        }

        if ($notes === []) {
            return $this->parseRiderNotesFallback($text);
        }

        return $this->uniqueNotes($notes);
    }

    /**
     * Parse Steadfast .tracking-steps DOM (date/time split across two <p> tags).
     *
     * @return list<array{message: string, at: ?string, source: string, rider_name?: ?string, rider_phone?: ?string}>
     */
    private function parseTrackingStepsHtml(string $html): array
    {
        // Outer step header: .date-time → .step-icon → .tracking_content > p.txt-black
        // (inner .txt-in.date-time is skipped because it has no following .step-icon)
        if (! preg_match_all(
            '/<div class="date-time">\s*<p>\s*([^<]+?)\s*<\/p>\s*<p>\s*([^<]+?)\s*<\/p>\s*<\/div>\s*<div class="step-icon">[\s\S]*?<div class="tracking_content[^"]*">([\s\S]*?)(?:<div class="txt-in date-time">|<\/div>\s*<\/div>\s*<div class="step">|<\/div>\s*<\/div>\s*<\/div>)/iu',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $notes = [];

        foreach ($matches as $match) {
            $atRaw = trim($match[1] . ', ' . $match[2]);
            $contentHtml = $match[3];
            $riderName = null;
            $riderPhone = null;

            $messageParts = [];
            if (preg_match_all('/<p class="txt-black">([\s\S]*?)<\/p>/iu', $contentHtml, $textMatches)) {
                foreach ($textMatches[1] as $index => $part) {
                    $part = $this->normalizeTimelineMessage($part);
                    if ($part === '') {
                        continue;
                    }
                    $messageParts[] = $part;
                    // First txt-black is usually "Assigned to rider."; later ones are rider name.
                    if ($index > 0 && ! preg_match('/assigned to rider/i', $part)) {
                        $riderName = $part;
                    }
                }
            }

            if (preg_match('/<span>\s*(0\d{9,13})\s*<\/span>/u', $contentHtml, $phoneMatch)) {
                $riderPhone = trim($phoneMatch[1]);
            } elseif (preg_match('/\b(0\d{9,13})\b/u', strip_tags($contentHtml), $phoneMatch)) {
                $riderPhone = trim($phoneMatch[1]);
            }

            if ($messageParts === []) {
                $message = $this->normalizeTimelineMessage(strip_tags($contentHtml));
            } elseif ($riderName || $riderPhone) {
                $headline = $messageParts[0] ?? 'Assigned to rider.';
                $lines = [$headline];
                if ($riderName) {
                    $lines[] = $riderName;
                }
                if ($riderPhone) {
                    $lines[] = $riderPhone;
                }
                $message = implode("\n", $lines);
            } else {
                $message = $this->normalizeTimelineMessage(implode(' · ', $messageParts));
            }

            if ($message === '') {
                continue;
            }

            $note = [
                'message' => $message,
                'at' => $this->normalizeSteadfastDatetime($atRaw),
                'source' => $this->classifySource($message),
            ];

            if ($riderName) {
                $note['rider_name'] = $riderName;
            }
            if ($riderPhone) {
                $note['rider_phone'] = $riderPhone;
            }

            $notes[] = $note;
        }

        return $this->uniqueNotes($notes);
    }

    /**
     * @return array{name: string, phone: string}|null
     */
    private function extractRiderFromHtml(string $html): ?array
    {
        $name = null;
        $phone = null;

        if (preg_match('/"rider"\s*:\s*\{([^{}]{0,500})\}/iu', $html, $block)) {
            if (preg_match('/"name"\s*:\s*"([^"]{2,120})"/u', $block[1], $m)) {
                $name = $this->normalizeTimelineMessage($m[1]);
            }
            if (preg_match('/"phone"\s*:\s*"([^"]{5,20})"/u', $block[1], $m)) {
                $phone = $this->normalizeTimelineMessage($m[1]);
            }
        }

        if ((! $name || ! $phone) && preg_match(
            '/short-info-rider[\s\S]{0,800}?<p class="txt-black">\s*Assigned to rider\.?\s*<\/p>[\s\S]{0,400}?<p class="txt-black">\s*([^<]{2,120}?)\s*<\/p>[\s\S]{0,300}?<span>\s*(0\d{9,13})\s*<\/span>/iu',
            $html,
            $m
        )) {
            $name = $name ?: $this->normalizeTimelineMessage($m[1]);
            $phone = $phone ?: $this->normalizeTimelineMessage($m[2]);
        }

        if ((! $name || ! $phone) && preg_match(
            '/rider-name[\s\S]{0,200}?<p[^>]*>\s*([^<]{2,120}?)\s*<\/p>[\s\S]{0,200}?<span>\s*(0\d{9,13})\s*<\/span>/iu',
            $html,
            $m
        )) {
            $name = $name ?: $this->normalizeTimelineMessage($m[1]);
            $phone = $phone ?: $this->normalizeTimelineMessage($m[2]);
        }

        return $this->normalizeRider([
            'name' => $name,
            'phone' => $phone,
        ]);
    }

    /**
     * @param  list<array{message: string, rider_name?: ?string, rider_phone?: ?string}>  $notes
     * @return array{name: string, phone: string}|null
     */
    private function inferRiderFromNotes(array $notes): ?array
    {
        foreach ($notes as $note) {
            $name = isset($note['rider_name']) ? trim((string) $note['rider_name']) : '';
            $phone = isset($note['rider_phone']) ? trim((string) $note['rider_phone']) : '';

            if ($name === '' && $phone === '') {
                $parsed = $this->parseRiderFromMessage((string) ($note['message'] ?? ''));
                $name = $parsed['name'] ?? '';
                $phone = $parsed['phone'] ?? '';
            }

            $rider = $this->normalizeRider(['name' => $name, 'phone' => $phone]);
            if ($rider !== null) {
                return $rider;
            }
        }

        return null;
    }

    /**
     * @return array{name?: string, phone?: string}
     */
    private function parseRiderFromMessage(string $message): array
    {
        $message = trim($message);
        if ($message === '' || ! preg_match('/assigned to rider/i', $message)) {
            return [];
        }

        $name = null;
        $phone = null;

        if (preg_match('/\b(0\d{9,13})\b/u', $message, $m)) {
            $phone = $m[1];
        }

        $lines = preg_split('/\r\n|\r|\n|·/u', $message) ?: [];
        foreach ($lines as $line) {
            $line = $this->normalizeTimelineMessage($line);
            if ($line === '' || preg_match('/assigned to rider/i', $line)) {
                continue;
            }
            if ($phone && $line === $phone) {
                continue;
            }
            if (preg_match('/^\d+$/', $line)) {
                continue;
            }
            $name = $line;
            break;
        }

        return array_filter([
            'name' => $name,
            'phone' => $phone,
        ]);
    }

    /**
     * @param  mixed  $rider
     * @return array{name: string, phone: string}|null
     */
    private function normalizeRider(mixed $rider): ?array
    {
        if (! is_array($rider)) {
            return null;
        }

        $name = $this->normalizeTimelineMessage((string) ($rider['name'] ?? $rider['rider_name'] ?? ''));
        $phone = $this->normalizeTimelineMessage((string) ($rider['phone'] ?? $rider['rider_phone'] ?? ''));
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? $phone;

        if ($name === '' && $phone === '') {
            return null;
        }

        // Ignore Steadfast placeholder riders.
        if ($name !== '' && preg_match('/^n\/?a$/i', $name)) {
            $name = '';
        }

        if ($name === '' && $phone === '') {
            return null;
        }

        return [
            'name' => $name !== '' ? $name : 'Rider',
            'phone' => $phone,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $trackings
     * @return list<array{message: string, at: ?string, source: string}>
     */
    private function mapTrackingsArray(array $trackings): array
    {
        $notes = [];

        foreach ($trackings as $item) {
            if (! is_array($item)) {
                continue;
            }

            $message = trim((string) ($item['text'] ?? $item['message'] ?? $item['note'] ?? ''));
            $message = $this->normalizeTimelineMessage($message);
            if ($message === '') {
                continue;
            }

            $atRaw = $item['created_at'] ?? $item['updated_at'] ?? null;
            $source = $this->classifySource($message);
            $note = [
                'message' => $message,
                'at' => is_string($atRaw) || is_numeric($atRaw)
                    ? $this->normalizeSteadfastDatetime((string) $atRaw)
                    : null,
                'source' => $source,
            ];

            if ($source === 'assigned_rider') {
                $parsed = $this->parseRiderFromMessage($message);
                if (! empty($parsed['name'])) {
                    $note['rider_name'] = $parsed['name'];
                }
                if (! empty($parsed['phone'])) {
                    $note['rider_phone'] = $parsed['phone'];
                }

                // Rebuild multiline message when API only returned a short headline.
                if (! empty($parsed['name']) || ! empty($parsed['phone'])) {
                    $lines = ['Assigned to rider.'];
                    if (! empty($parsed['name'])) {
                        $lines[] = $parsed['name'];
                    }
                    if (! empty($parsed['phone'])) {
                        $lines[] = $parsed['phone'];
                    }
                    if (! preg_match('/\b0\d{9,13}\b/u', $message) && ! empty($parsed['phone'])) {
                        $note['message'] = implode("\n", $lines);
                    }
                }
            }

            $notes[] = $note;
        }

        return $this->uniqueNotes($notes);
    }

    /**
     * @return list<array{message: string, at: ?string, source: string}>
     */
    private function parseTrackingsFromEmbeddedJson(string $html): array
    {
        // Inertia data-page attribute.
        if (preg_match('/data-page=(["\'])(.*?)\1/s', $html, $match)) {
            $decoded = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
            $payload = json_decode($decoded, true);
            if (is_array($payload)) {
                $trackings = data_get($payload, 'props.trackings');
                if (! is_array($trackings)) {
                    $trackings = data_get($payload, 'props.consignment.trackings');
                }
                if (is_array($trackings) && $trackings !== []) {
                    return $this->mapTrackingsArray($trackings);
                }
            }
        }

        // Any embedded trackings JSON blob in the page/scripts.
        if (preg_match('/"trackings"\s*:\s*(\[[\s\S]*?\])\s*[,}]/u', $html, $match)) {
            $trackings = json_decode($match[1], true);
            if (is_array($trackings) && $trackings !== []) {
                return $this->mapTrackingsArray($trackings);
            }
        }

        return [];
    }

    /**
     * @return list<array{message: string, at: ?string, source: string}>
     */
    private function parseRiderNotesFallback(string $text): array
    {
        $notes = [];

        if (preg_match_all(
            '/Rider\s+Note\s*:\s*[\'"]?([^\'"\n]{1,500})/iu',
            $text,
            $riderMatches
        )) {
            foreach ($riderMatches[1] as $riderMessage) {
                $message = 'Rider Note: \'' . trim($riderMessage) . '\'';
                $notes[] = [
                    'message' => $message,
                    'at' => null,
                    'source' => 'rider',
                ];
            }
        }

        return $this->uniqueNotes($notes);
    }

    private function htmlToTimelineText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript)\b[^>]*>[\s\S]*?<\/\1>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<(br|hr)\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|tr|h\d|section|article)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/[ \t\f\v]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{2,}/u", "\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeTimelineMessage(string $message): string
    {
        $message = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5);
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        $message = trim($message, " \t\n\r\0\x0B-:|");

        return trim($message);
    }

    /**
     * @param  list<array{message: string, at: ?string, source: string}>  $notes
     * @return list<array{message: string, at: ?string, source: string}>
     */
    private function uniqueNotes(array $notes): array
    {
        $seen = [];
        $unique = [];

        foreach ($notes as $note) {
            $key = strtolower(($note['at'] ?? '') . '|' . $note['message']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $note;
        }

        return $unique;
    }

    /**
     * Build the FormData payload Steadfast's edit-parcel Vue posts to
     * POST /user/consignment/single/update.
     *
     * @param  string|null  $note  null keeps the page note
     * @param  string|null  $cusAddress  null keeps the page address
     * @param  float|int|string|null  $codAmount  null keeps the page COD
     * @return array<string, scalar>|null
     */
    private function buildSingleUpdatePayload(
        string $html,
        string $consignmentId,
        ?string $note = null,
        ?string $cusAddress = null,
        float|int|string|null $codAmount = null,
        ?string $customerName = null,
        ?string $customerPhone = null,
    ): ?array {
        $consignment = $this->extractEditParcelConsignment($html);
        if (! is_array($consignment)) {
            LogHelper::saveLog(
                'Steadfast parcel note update aborted',
                'Could not extract consignment JSON from edit-parcel page.'
            );

            return null;
        }

        $additional = is_array($consignment['additional_data'] ?? null)
            ? $consignment['additional_data']
            : [];

        $pageNote = trim((string) ($consignment['note'] ?? ''));
        if ($pageNote === '' && isset($additional['note'])) {
            $pageNote = trim((string) $additional['note']);
        }

        $payload = [
            'consignment_id' => (string) ($consignment['id'] ?? $consignmentId),
            'cus_phone' => $customerPhone !== null
                ? trim($customerPhone)
                : trim((string) ($consignment['phone'] ?? $consignment['cus_phone'] ?? '')),
            'cus_name' => $customerName !== null
                ? trim($customerName)
                : trim((string) ($consignment['name'] ?? $consignment['cus_name'] ?? '')),
            'cus_address' => $cusAddress !== null
                ? trim($cusAddress)
                : trim((string) ($consignment['address'] ?? $consignment['cus_address'] ?? '')),
            'note' => $note !== null ? $note : $pageNote,
            'invoice' => trim((string) ($consignment['invoice'] ?? '')),
            'cod_amount' => $codAmount !== null
                ? $this->normalizeCodAmount($codAmount) ?? 0
                : ($consignment['cod'] ?? $consignment['cod_amount'] ?? 0),
            'alt_phone' => trim((string) (
                $additional['alternative_phone']
                ?? $consignment['alt_phone']
                ?? $consignment['alternative_phone']
                ?? ''
            )),
            'email' => trim((string) ($additional['email'] ?? $consignment['email'] ?? '')),
            'item_description' => trim((string) (
                $additional['item_description']
                ?? $consignment['item_description']
                ?? $consignment['product_description']
                ?? ''
            )),
            'policestation_id' => $consignment['area_id']
                ?? $consignment['policestation_id']
                ?? '',
            'exchange' => ! empty($consignment['exchange']) ? '1' : '0',
            'pickup_address_id' => $this->extractEditParcelAddressId($html, $consignment),
        ];

        foreach (self::CRITICAL_EDIT_FIELDS as $critical) {
            if (trim((string) ($payload[$critical] ?? '')) === '') {
                LogHelper::saveLog(
                    'Steadfast parcel note update aborted',
                    "Critical field {$critical} parsed empty — refusing to submit."
                );

                return null;
            }
        }

        if ((string) $payload['consignment_id'] !== $consignmentId
            && (string) ($consignment['id'] ?? '') !== ''
            && (string) $consignment['id'] !== $consignmentId
        ) {
            LogHelper::saveLog(
                'Steadfast parcel note update aborted',
                'Consignment ID mismatch on edit-parcel page.'
            );

            return null;
        }

        $payload['consignment_id'] = $consignmentId;

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractEditParcelConsignment(string $html): ?array
    {
        foreach ([':consignment=', 'consignment='] as $marker) {
            $decoded = $this->extractJsonObjectAfterMarker($html, $marker);
            if (is_array($decoded) && $this->looksLikeConsignment($decoded)) {
                return $decoded;
            }
        }

        // Inertia / embedded page JSON.
        if (preg_match('/data-page=(["\'])(.*?)\1/s', $html, $match)) {
            $payload = $this->decodeEmbeddedJson($match[2]);
            if (is_array($payload)) {
                foreach ([
                    'props.consignment',
                    'props.editParcel.consignment',
                    'props.parcel',
                ] as $path) {
                    $candidate = data_get($payload, $path);
                    if (is_array($candidate) && $this->looksLikeConsignment($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract the first JSON object that follows an HTML/Vue attribute marker.
     *
     * @return array<string, mixed>|null
     */
    private function extractJsonObjectAfterMarker(string $html, string $marker): ?array
    {
        $offset = 0;
        $markerLength = strlen($marker);

        while (($pos = stripos($html, $marker, $offset)) !== false) {
            $cursor = $pos + $markerLength;
            $length = strlen($html);

            while ($cursor < $length && ctype_space($html[$cursor])) {
                $cursor++;
            }

            if ($cursor >= $length) {
                break;
            }

            $quote = $html[$cursor];
            if ($quote === '"' || $quote === "'") {
                $cursor++;
            }

            while ($cursor < $length && ctype_space($html[$cursor])) {
                $cursor++;
            }

            if ($cursor >= $length || $html[$cursor] !== '{') {
                // HTML-escaped object may start with &quot; or {&quot;
                $slice = substr($html, $cursor, 20);
                if (! str_contains($slice, '{')) {
                    $offset = $pos + $markerLength;
                    continue;
                }
                $bracePos = strpos($html, '{', $cursor);
                if ($bracePos === false) {
                    break;
                }
                $cursor = $bracePos;
            }

            $json = $this->sliceBalancedJsonObject($html, $cursor);
            if ($json !== null) {
                $decoded = $this->decodeEmbeddedJson($json);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            $offset = $pos + $markerLength;
        }

        return null;
    }

    private function sliceBalancedJsonObject(string $html, int $start): ?string
    {
        if (($html[$start] ?? '') !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $stringQuote = null;
        $length = strlen($html);

        for ($i = $start; $i < $length; $i++) {
            $ch = $html[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === $stringQuote) {
                    $inString = false;
                    $stringQuote = null;
                }
                continue;
            }

            // Treat HTML entity quotes inside attribute-encoded JSON as string quotes.
            if ($ch === '"' || $ch === "'") {
                $inString = true;
                $stringQuote = $ch;
                continue;
            }

            if ($ch === '{') {
                $depth++;
                continue;
            }

            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $consignment
     */
    private function extractEditParcelAddressId(string $html, array $consignment): string
    {
        foreach ([
            '/:address_id=(["\']?)(\d+)\1/i',
            '/:address-id=(["\']?)(\d+)\1/i',
            '/"address_id"\s*:\s*(\d+)/i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return (string) ($match[2] ?? $match[1]);
            }
        }

        return trim((string) (
            $consignment['pickup_address_id']
            ?? $consignment['address_id']
            ?? ''
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function looksLikeConsignment(array $data): bool
    {
        $phone = trim((string) ($data['phone'] ?? $data['cus_phone'] ?? ''));
        $name = trim((string) ($data['name'] ?? $data['cus_name'] ?? ''));
        $address = trim((string) ($data['address'] ?? $data['cus_address'] ?? ''));

        return $phone !== '' && $name !== '' && $address !== '';
    }

    private function decodeEmbeddedJson(string $raw): mixed
    {
        $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
        if ($raw === '') {
            return null;
        }

        // Vue may bind with single-quoted JSON using &quot; or escaped quotes.
        $candidates = [$raw];
        if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            $candidates[] = substr($raw, 1, -1);
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            // Sometimes attributes use HTML-escaped quotes only.
            $unescaped = stripcslashes($candidate);
            $decoded = json_decode($unescaped, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractTextareaValue(string $html, string $name): ?string
    {
        $pattern = '/<textarea\b[^>]*\b(?:name|id)=["\']' . preg_quote($name, '/') . '["\'][^>]*>([\s\S]*?)<\/textarea>/i';
        if (! preg_match($pattern, $html, $match)) {
            return null;
        }

        $value = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));

        return $value === '' ? null : $value;
    }

    private function classifySource(string $message): string
    {
        if (preg_match('/^\s*Rider\s+Note\s*:/i', $message)) {
            return 'rider';
        }

        if (preg_match('/assigned to rider/i', $message)) {
            return 'assigned_rider';
        }

        return 'status';
    }

    private function normalizeSteadfastDatetime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            // Display timestamps on Steadfast are Asia/Dhaka; ISO strings keep their own offset.
            $tz = new \DateTimeZone('Asia/Dhaka');
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                $dt = new \DateTimeImmutable($raw);
            } else {
                $dt = new \DateTimeImmutable($raw, $tz);
            }

            return $dt->setTimezone($tz)->format('Y-m-d H:i:s');
        } catch (\Throwable $th) {
            return $raw;
        }
    }
}
