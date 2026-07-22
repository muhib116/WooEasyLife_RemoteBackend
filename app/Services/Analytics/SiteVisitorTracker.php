<?php

namespace App\Services\Analytics;

use App\Models\SiteVisitorDailyStat;
use App\Models\SiteVisitorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteVisitorTracker
{
    public function __construct(
        private SiteVisitorPathAllowlist $allowlist,
    ) {}

    public function track(Request $request, array $payload): bool
    {
        if (! config('site_visitors.enabled', true)) {
            return false;
        }

        if ($this->isBot($request)) {
            return false;
        }

        $path = $this->normalizePath($payload['path'] ?? null);
        if ($path === null || ! $this->isAllowedPath($path)) {
            return false;
        }

        $eventType = (string) ($payload['event'] ?? '');
        if (! in_array($eventType, SiteVisitorEvent::EVENT_TYPES, true)) {
            return false;
        }

        $visitor = $this->resolveVisitorHash($request, $payload['visitor_id'] ?? null);
        $session = $this->sessionHash($request);

        if ($eventType === SiteVisitorEvent::TYPE_PAGE_VIEW) {
            $seconds = max(1, (int) config('site_visitors.page_view_dedupe_seconds', 30));
            $dedupeKey = 'site_visitors:view:'.$path.':'.$visitor;
            if (! Cache::add($dedupeKey, 1, now()->addSeconds($seconds))) {
                return false;
            }
        }

        if ($eventType === SiteVisitorEvent::TYPE_SCROLL) {
            $pct = max(1, min(100, (int) ($payload['scroll_pct'] ?? 0)));
            $dedupeKey = 'site_visitors:scroll:'.$path.':'.$visitor.':'.$pct.':'.now()->toDateString();
            if (! Cache::add($dedupeKey, 1, now()->endOfDay())) {
                return false;
            }
            $payload['scroll_pct'] = $pct;
        }

        if ($eventType === SiteVisitorEvent::TYPE_CTA_CLICK) {
            $label = Str::limit((string) ($payload['cta_label'] ?? 'cta'), 80, '');
            $dedupeKey = 'site_visitors:cta:'.$path.':'.$visitor.':'.md5($label).':'.now()->format('Y-m-d-H');
            if (! Cache::add($dedupeKey, 1, now()->addHour())) {
                return false;
            }
            $payload['cta_label'] = $label;
        }

        if ($eventType === SiteVisitorEvent::TYPE_TOOL_ACTION) {
            $action = Str::limit((string) ($payload['action_name'] ?? 'action'), 80, '');
            $dedupeKey = 'site_visitors:tool:'.$path.':'.$visitor.':'.md5($action).':'.now()->format('Y-m-d-H');
            if (! Cache::add($dedupeKey, 1, now()->addHour())) {
                return false;
            }
            $payload['action_name'] = $action;
        }

        if ($eventType === SiteVisitorEvent::TYPE_HEARTBEAT) {
            $engagedMs = isset($payload['engaged_ms'])
                ? max(0, min(86_400_000, (int) $payload['engaged_ms']))
                : 0;
            $payload['engaged_ms'] = $engagedMs;

            $interval = max(5, (int) config('site_visitors.heartbeat_min_interval_seconds', 15));
            $gateKey = 'site_visitors:hb:'.$visitor.':'.md5($path);
            if (! Cache::add($gateKey, 1, now()->addSeconds($interval))) {
                return $this->bumpHeartbeatEngagedMs($path, $visitor, $engagedMs);
            }
        }

        if ($this->isVisitorOverQuota($visitor, $eventType)) {
            return false;
        }

        $utms = $this->resolveUtms($request, $payload);
        $referrerHost = $this->resolveReferrerHost($request, $payload['referrer'] ?? null);
        $channel = $this->deriveSourceChannel($utms, $referrerHost);
        $searchKeyword = $this->resolveSearchKeyword($payload, $utms, $payload['referrer'] ?? null);

        SiteVisitorEvent::query()->create([
            'path' => $path,
            'event_type' => $eventType,
            'visitor_hash' => $visitor,
            'session_hash' => $session,
            'referrer_host' => $referrerHost,
            'utm_source' => $utms['utm_source'],
            'utm_medium' => $utms['utm_medium'],
            'utm_campaign' => $utms['utm_campaign'],
            'utm_content' => $utms['utm_content'],
            'utm_term' => $utms['utm_term'],
            'search_keyword' => $searchKeyword,
            'source_channel' => $channel,
            'device_type' => $this->deviceType($request),
            'country' => $this->countryFromHeaders($request),
            'scroll_pct' => isset($payload['scroll_pct']) ? (int) $payload['scroll_pct'] : null,
            'engaged_ms' => isset($payload['engaged_ms']) ? max(0, min(86_400_000, (int) $payload['engaged_ms'])) : null,
            'cta_label' => $payload['cta_label'] ?? null,
            'action_name' => $payload['action_name'] ?? null,
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : null,
        ]);

        $this->bumpVisitorQuota($visitor, $eventType);
        $this->bumpLiveDailyCounters($path, $eventType, (int) ($payload['scroll_pct'] ?? 0));

        return true;
    }

    public function normalizePath(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $parsed = parse_url($raw, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : trim($raw);
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }
        $path = rtrim($path, '/') ?: '/';
        if (strlen($path) > 500) {
            return null;
        }
        // Public marketing paths are alphanumeric with dashes/slashes; reject control chars.
        if (preg_match('/[\x00-\x1f\x7f]/', $path)) {
            return null;
        }

        return $path;
    }

    public function isAllowedPath(string $path): bool
    {
        $blocked = config('site_visitors.blocked_path_prefixes', []);
        foreach ($blocked as $prefix) {
            $prefix = (string) $prefix;
            if ($prefix === '') {
                continue;
            }
            if ($this->pathMatchesPrefix($path, $prefix)) {
                return false;
            }
        }

        $allowed = $this->allowlist->prefixes();
        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $prefix) {
            $prefix = (string) $prefix;
            if ($prefix === '') {
                continue;
            }
            if ($this->pathMatchesPrefix($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function pathMatchesPrefix(string $path, string $prefix): bool
    {
        if ($prefix === '/') {
            // Root allow only exact `/` when listed alone; use `/en` style for trees.
            return $path === '/';
        }

        if ($path === $prefix) {
            return true;
        }

        $normalized = rtrim($prefix, '/');

        return str_starts_with($path, $normalized.'/');
    }

    public function resolveVisitorHash(Request $request, mixed $clientId): string
    {
        $client = is_string($clientId) ? trim($clientId) : '';
        if ($client !== '' && preg_match('/^[a-f0-9]{16,64}$/i', $client)) {
            return strtolower($client);
        }

        $cookie = (string) $request->cookie('wel_site_vid', '');
        if ($cookie !== '' && preg_match('/^[a-f0-9]{16,64}$/i', $cookie)) {
            return strtolower($cookie);
        }

        return hash('sha256', $request->ip().'|'.$request->userAgent());
    }

    public function sessionHash(Request $request): string
    {
        try {
            $sid = $request->session()->getId();
        } catch (\Throwable) {
            $sid = $request->ip().'|'.$request->userAgent().'|'.now()->format('Y-m-d-H');
        }

        return hash('sha256', $sid.'|site');
    }

    private function isBot(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return true;
        }

        foreach (config('site_visitors.bot_ua_snippets', []) as $snippet) {
            if ($snippet !== '' && str_contains($ua, strtolower((string) $snippet))) {
                return true;
            }
        }

        return false;
    }

    private function isVisitorOverQuota(string $visitor, string $eventType): bool
    {
        if ($eventType === SiteVisitorEvent::TYPE_PAGE_VIEW) {
            $max = max(5, (int) config('site_visitors.max_views_per_visitor_day', 80));
            $key = 'site_visitors:quota:view:'.$visitor.':'.now()->toDateString();

            return (int) Cache::get($key, 0) >= $max;
        }

        if ($eventType === SiteVisitorEvent::TYPE_CTA_CLICK) {
            $max = max(3, (int) config('site_visitors.max_cta_per_visitor_hour', 40));
            $key = 'site_visitors:quota:cta:'.$visitor.':'.now()->format('Y-m-d-H');

            return (int) Cache::get($key, 0) >= $max;
        }

        if ($eventType === SiteVisitorEvent::TYPE_TOOL_ACTION) {
            $max = max(3, (int) config('site_visitors.max_tool_actions_per_visitor_hour', 60));
            $key = 'site_visitors:quota:tool:'.$visitor.':'.now()->format('Y-m-d-H');

            return (int) Cache::get($key, 0) >= $max;
        }

        if ($eventType === SiteVisitorEvent::TYPE_HEARTBEAT) {
            $max = max(10, (int) config('site_visitors.max_heartbeats_per_visitor_day', 200));
            $key = 'site_visitors:quota:hb:'.$visitor.':'.now()->toDateString();

            return (int) Cache::get($key, 0) >= $max;
        }

        return false;
    }

    private function bumpVisitorQuota(string $visitor, string $eventType): void
    {
        if ($eventType === SiteVisitorEvent::TYPE_PAGE_VIEW) {
            $key = 'site_visitors:quota:view:'.$visitor.':'.now()->toDateString();
            Cache::put($key, (int) Cache::get($key, 0) + 1, now()->endOfDay());
        }

        if ($eventType === SiteVisitorEvent::TYPE_CTA_CLICK) {
            $key = 'site_visitors:quota:cta:'.$visitor.':'.now()->format('Y-m-d-H');
            Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addHour());
        }

        if ($eventType === SiteVisitorEvent::TYPE_TOOL_ACTION) {
            $key = 'site_visitors:quota:tool:'.$visitor.':'.now()->format('Y-m-d-H');
            Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addHour());
        }

        if ($eventType === SiteVisitorEvent::TYPE_HEARTBEAT) {
            $key = 'site_visitors:quota:hb:'.$visitor.':'.now()->toDateString();
            Cache::put($key, (int) Cache::get($key, 0) + 1, now()->endOfDay());
        }
    }

    private function bumpLiveDailyCounters(string $path, string $eventType, int $scrollPct): void
    {
        $date = now()->toDateString();
        $pageviews = $eventType === SiteVisitorEvent::TYPE_PAGE_VIEW ? 1 : 0;
        $cta = $eventType === SiteVisitorEvent::TYPE_CTA_CLICK ? 1 : 0;
        $scroll = ($eventType === SiteVisitorEvent::TYPE_SCROLL && $scrollPct >= 50) ? 1 : 0;

        if ($pageviews + $cta + $scroll === 0) {
            return;
        }

        SiteVisitorDailyStat::query()->upsert(
            [[
                'date' => $date,
                'path' => $path,
                'pageviews' => $pageviews,
                'unique_visitors' => 0,
                'sessions' => 0,
                'avg_engaged_ms' => 0,
                'scroll_50_count' => $scroll,
                'cta_clicks' => $cta,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['date', 'path'],
            [
                'pageviews' => DB::raw('pageviews + VALUES(pageviews)'),
                'cta_clicks' => DB::raw('cta_clicks + VALUES(cta_clicks)'),
                'scroll_50_count' => DB::raw('scroll_50_count + VALUES(scroll_50_count)'),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * When heartbeat gate is closed, raise engaged_ms on the latest row if higher.
     */
    private function bumpHeartbeatEngagedMs(string $path, string $visitor, int $engagedMs): bool
    {
        if ($engagedMs <= 0) {
            return false;
        }

        $latest = SiteVisitorEvent::query()
            ->where('event_type', SiteVisitorEvent::TYPE_HEARTBEAT)
            ->where('path', $path)
            ->where('visitor_hash', $visitor)
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return false;
        }

        if ($engagedMs <= (int) $latest->engaged_ms) {
            return false;
        }

        $latest->engaged_ms = $engagedMs;
        $latest->save();

        return true;
    }

    /**
     * @return array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_content:?string,utm_term:?string}
     */
    private function resolveUtms(Request $request, array $payload): array
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
        $out = [];
        foreach ($keys as $key) {
            $val = $payload[$key] ?? $request->query($key);
            $out[$key] = is_string($val) && $val !== ''
                ? Str::limit(trim($val), 120, '')
                : null;
        }

        return $out;
    }

    private function resolveReferrerHost(Request $request, mixed $clientReferrer): ?string
    {
        $ref = is_string($clientReferrer) && $clientReferrer !== ''
            ? $clientReferrer
            : (string) $request->headers->get('referer', '');

        if ($ref === '') {
            return null;
        }

        $host = parse_url($ref, PHP_URL_HOST);

        return is_string($host) ? Str::limit($host, 120, '') : null;
    }

    /**
     * Prefer utm_term, then rare search-engine referrer query params.
     * Ignore free-form client search_keyword to reduce PII injection.
     *
     * @param  array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_content:?string,utm_term:?string}  $utms
     */
    private function resolveSearchKeyword(array $payload, array $utms, mixed $clientReferrer): ?string
    {
        $candidates = [
            $utms['utm_term'] ?? null,
        ];

        $ref = is_string($clientReferrer) ? $clientReferrer : '';
        if ($ref !== '' && $this->isSearchEngineReferrer($ref)) {
            $query = parse_url($ref, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                foreach (['q', 'query', 'p', 'text', 'keyword'] as $key) {
                    if (! empty($params[$key]) && is_string($params[$key])) {
                        $candidates[] = $params[$key];
                        break;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $trimmed = trim(preg_replace('/\s+/', ' ', $candidate) ?? '');
            if ($trimmed === '') {
                continue;
            }
            // Drop obvious emails / long phone-like strings.
            if (str_contains($trimmed, '@') || preg_match('/\d{8,}/', $trimmed)) {
                continue;
            }

            return Str::limit($trimmed, 255, '');
        }

        return null;
    }

    private function isSearchEngineReferrer(string $referrer): bool
    {
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach (['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.', 'search.yahoo.'] as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_content:?string,utm_term:?string}  $utms
     */
    public function deriveSourceChannel(array $utms, ?string $referrerHost): string
    {
        $medium = strtolower((string) ($utms['utm_medium'] ?? ''));
        $source = strtolower((string) ($utms['utm_source'] ?? ''));

        if ($medium !== '' || $source !== '') {
            if (in_array($medium, ['cpc', 'ppc', 'paid', 'paidsearch', 'paid_social', 'display'], true)
                || str_contains($medium, 'paid')
                || in_array($source, ['googleads', 'fbads', 'facebookads'], true)) {
                return 'paid';
            }
            if (in_array($medium, ['email', 'e-mail', 'newsletter'], true)) {
                return 'email';
            }
            if (in_array($medium, ['social', 'social-media'], true)
                || in_array($source, ['facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'youtube'], true)) {
                return 'social';
            }
            if (in_array($medium, ['organic', 'seo'], true)) {
                return 'organic';
            }
            if ($medium === 'referral') {
                return 'referral';
            }
        }

        if ($referrerHost === null || $referrerHost === '') {
            return 'direct';
        }

        $host = strtolower($referrerHost);
        $searchHosts = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.'];
        foreach ($searchHosts as $needle) {
            if (str_contains($host, $needle)) {
                return 'organic';
            }
        }

        $socialHosts = ['facebook.', 'fb.', 'instagram.', 't.co', 'twitter.', 'x.com', 'linkedin.', 'tiktok.', 'youtube.', 'youtu.be'];
        foreach ($socialHosts as $needle) {
            if (str_contains($host, $needle)) {
                return 'social';
            }
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '' && ($host === $appHost || str_ends_with($host, '.'.$appHost))) {
            return 'direct';
        }

        return 'referral';
    }

    private function deviceType(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return 'desktop';
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function countryFromHeaders(Request $request): ?string
    {
        $cf = $request->headers->get('CF-IPCountry');
        if (is_string($cf) && preg_match('/^[A-Z]{2}$/', strtoupper($cf))) {
            $code = strtoupper($cf);

            return $code === 'XX' ? null : $code;
        }

        return null;
    }
}
