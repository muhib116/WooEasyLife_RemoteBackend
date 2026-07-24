<?php

namespace App\Services\Courier;

use App\LogHelper;
use RuntimeException;

/**
 * Steadfast merchant-portal notification feed (/user/notifications).
 */
class SteadfastNotificationsService
{
    public function __construct(
        private SteadfastPortalSessionClient $portal,
    ) {}

    /**
     * @param  array{username: string, password: string}  $credentials
     * @return array{
     *   items: list<array{
     *     message: string,
     *     consignment_id: string|null,
     *     url: string,
     *     relative_time: string,
     *     is_read: bool
     *   }>,
     *   next_cursor: string|null,
     *   has_more: bool,
     *   unread_count: int
     * }
     */
    public function list(array $credentials, ?string $cursor = null): array
    {
        $cursor = $this->normalizeCursor($cursor);

        return $this->portal->withSession($credentials, function (
            SteadfastPortalSessionClient $client,
            string $host,
            array $cookies
        ) use ($cursor) {
            $path = '/user/notifications';
            if ($cursor !== null) {
                $path .= '?cursor=' . rawurlencode($cursor);
            }

            $page = $client->get($path, $host, $cookies, expectJson: false);
            $this->assertAuthenticatedHtml($client, $page->body(), $page->status(), 'notifications');

            if (! $page->successful()) {
                throw new RuntimeException('Unable to load Steadfast notifications (HTTP ' . $page->status() . ').');
            }

            $body = $page->body();

            // Some Steadfast builds return JSON { html: "..." } for cursor pages.
            $json = json_decode($body, true);
            if (is_array($json)) {
                if (isset($json['html']) && is_string($json['html'])) {
                    $body = $json['html'];
                } elseif (isset($json['data']['html']) && is_string($json['data']['html'])) {
                    $body = $json['data']['html'];
                } elseif (isset($json['items']) && is_array($json['items'])) {
                    return $this->normalizeJsonPayload($json, $host);
                }
            }

            return $this->parseNotificationsHtml($body, $host);
        });
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *   items: list<array{message: string, consignment_id: string|null, url: string, relative_time: string, is_read: bool}>,
     *   next_cursor: string|null,
     *   has_more: bool,
     *   unread_count: int
     * }
     */
    private function normalizeJsonPayload(array $json, string $host): array
    {
        $rawItems = [];
        if (isset($json['items']) && is_array($json['items'])) {
            $rawItems = $json['items'];
        } elseif (isset($json['data']['items']) && is_array($json['data']['items'])) {
            $rawItems = $json['data']['items'];
        }

        $items = [];
        foreach ($rawItems as $row) {
            if (! is_array($row)) {
                continue;
            }
            $message = trim(html_entity_decode(strip_tags((string) ($row['message'] ?? $row['text'] ?? '')), ENT_QUOTES | ENT_HTML5));
            if ($message === '') {
                continue;
            }

            $consignmentId = $this->extractConsignmentId(
                (string) ($row['consignment_id'] ?? $row['consignmentId'] ?? ''),
                (string) ($row['url'] ?? $row['link'] ?? ''),
                $message
            );
            $url = trim((string) ($row['url'] ?? $row['link'] ?? ''));
            if ($url === '' && $consignmentId !== null) {
                $url = $this->absoluteSteadfastUrl($host, '/user/consignment/' . $consignmentId);
            } elseif ($url !== '' && ! preg_match('#^https?://#i', $url)) {
                $url = $this->absoluteSteadfastUrl($host, $url);
            }

            $items[] = [
                'message' => $message,
                'consignment_id' => $consignmentId,
                'url' => $url,
                'relative_time' => trim((string) ($row['relative_time'] ?? $row['time'] ?? $row['created_at_human'] ?? '')),
                'is_read' => (bool) ($row['is_read'] ?? $row['read'] ?? true),
            ];
        }

        $nextCursor = $this->normalizeCursor(
            (string) ($json['next_cursor'] ?? $json['data']['next_cursor'] ?? $json['cursor'] ?? '')
        );
        if ($nextCursor === null) {
            $nextUrl = (string) ($json['next_url'] ?? $json['data']['next_url'] ?? $json['next'] ?? '');
            $nextCursor = $this->extractCursorFromUrl($nextUrl);
        }

        $unread = 0;
        foreach ($items as $item) {
            if (! $item['is_read']) {
                $unread++;
            }
        }

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $nextCursor !== null || ! empty($json['has_more']),
            'unread_count' => $unread,
        ];
    }

    /**
     * @return array{
     *   items: list<array{message: string, consignment_id: string|null, url: string, relative_time: string, is_read: bool}>,
     *   next_cursor: string|null,
     *   has_more: bool,
     *   unread_count: int
     * }
     */
    public function parseNotificationsHtml(string $html, string $host = 'www.steadfast.com.bd'): array
    {
        $items = [];

        if (preg_match_all('/<a\b([^>]*)>(.*?)<\/a>/is', $html, $anchorMatches, PREG_SET_ORDER)) {
            foreach ($anchorMatches as $match) {
                $attrs = (string) ($match[1] ?? '');
                $inner = (string) ($match[2] ?? '');

                if (! preg_match('/\bsingle_notification\b/i', $attrs)) {
                    continue;
                }

                $href = '';
                if (preg_match('/\bhref=["\']([^"\']+)["\']/i', $attrs, $hrefMatch)) {
                    $href = html_entity_decode(trim($hrefMatch[1]), ENT_QUOTES | ENT_HTML5);
                }

                $message = '';
                if (preg_match('/<p\b[^>]*class=["\'][^"\']*\bmb-0\b[^"\']*["\'][^>]*>(.*?)<\/p>/is', $inner, $msgMatch)) {
                    $message = trim(html_entity_decode(strip_tags($msgMatch[1]), ENT_QUOTES | ENT_HTML5));
                }
                if ($message === '') {
                    if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $inner, $msgMatch)) {
                        $message = trim(html_entity_decode(strip_tags($msgMatch[1]), ENT_QUOTES | ENT_HTML5));
                    }
                }
                if ($message === '') {
                    continue;
                }

                $relativeTime = '';
                if (preg_match('/<span\b[^>]*>(.*?)<\/span>/is', $inner, $timeMatch)) {
                    $relativeTime = trim(html_entity_decode(strip_tags($timeMatch[1]), ENT_QUOTES | ENT_HTML5));
                }

                $isRead = (bool) preg_match('/\bread_notification\b/i', $attrs)
                    || (bool) preg_match('/\bread_notification\b/i', $inner);
                if (preg_match('/\bunread_notification\b/i', $attrs)
                    || preg_match('/\bunread[_-]notification\b/i', $inner)
                ) {
                    $isRead = false;
                }

                $consignmentId = $this->extractConsignmentId('', $href, $message);
                $url = $href;
                if ($url === '' && $consignmentId !== null) {
                    $url = $this->absoluteSteadfastUrl($host, '/user/consignment/' . $consignmentId);
                } elseif ($url !== '' && ! preg_match('#^https?://#i', $url)) {
                    $url = $this->absoluteSteadfastUrl($host, $url);
                }

                $items[] = [
                    'message' => $message,
                    'consignment_id' => $consignmentId,
                    'url' => $url,
                    'relative_time' => $relativeTime,
                    'is_read' => $isRead,
                ];
            }
        }

        $nextCursor = null;
        if (preg_match(
            '/id=["\']load-more-btn["\'][^>]*data-next=["\']([^"\']+)["\']/i',
            $html,
            $nextMatch
        ) || preg_match(
            '/data-next=["\']([^"\']+)["\'][^>]*id=["\']load-more-btn["\']/i',
            $html,
            $nextMatch
        )) {
            $nextCursor = $this->extractCursorFromUrl(
                html_entity_decode(trim($nextMatch[1]), ENT_QUOTES | ENT_HTML5)
            );
        }

        $unread = 0;
        foreach ($items as $item) {
            if (! $item['is_read']) {
                $unread++;
            }
        }

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $nextCursor !== null,
            'unread_count' => $unread,
        ];
    }

    private function extractConsignmentId(string $explicit, string $url, string $message): ?string
    {
        $explicit = trim($explicit);
        if (preg_match('/^\d{4,20}$/', $explicit)) {
            return $explicit;
        }

        if (preg_match('#/user/consignment/(\d{4,20})#i', $url, $m)) {
            return $m[1];
        }

        if (preg_match('/#\s*(\d{4,20})\b/', $message, $m)) {
            return $m[1];
        }

        if (preg_match('/\bParcel\s*#?\s*(\d{4,20})\b/i', $message, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractCursorFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            // Already a bare cursor token.
            return $this->normalizeCursor($url);
        }

        parse_str($query, $params);
        if (! empty($params['cursor']) && is_string($params['cursor'])) {
            return $this->normalizeCursor($params['cursor']);
        }

        return null;
    }

    private function normalizeCursor(?string $cursor): ?string
    {
        $cursor = trim((string) $cursor);
        if ($cursor === '') {
            return null;
        }

        // Reject accidental full URLs without a cursor param.
        if (preg_match('#^https?://#i', $cursor) && ! str_contains($cursor, 'cursor=')) {
            return null;
        }

        if (strlen($cursor) > 2000) {
            throw new RuntimeException('Invalid notifications cursor.');
        }

        return $cursor;
    }

    private function absoluteSteadfastUrl(string $host, string $path): string
    {
        $host = preg_replace('#^https?://#i', '', $host) ?: 'www.steadfast.com.bd';
        if ($path === '') {
            return 'https://' . $host . '/';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return 'https://' . $host . (str_starts_with($path, '/') ? $path : '/' . $path);
    }

    private function assertAuthenticatedHtml(
        SteadfastPortalSessionClient $client,
        string $html,
        int $status,
        string $context
    ): void {
        if ($status === 401 || $status === 419 || $client->looksLikeLoginPage($html)) {
            LogHelper::saveLog('Steadfast notifications auth failure', $context . ' HTTP ' . $status);
            throw new RuntimeException('Steadfast portal session expired while loading ' . $context . '.');
        }
    }
}
