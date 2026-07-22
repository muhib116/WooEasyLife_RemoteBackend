<?php

namespace App\Services\BlogAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Find public rival article URLs for a target keyword.
 * Order in auto mode: Brave → Bing → DuckDuckGo HTML → DuckDuckGo Lite.
 */
class BlogCompetitorDiscoveryService
{
    /**
     * @return list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>
     */
    public function discover(string $keyword, ?int $limit = null): array
    {
        $keyword = trim($keyword);
        if ($keyword === '' || ! config('blog_ai.competitors.discovery.enabled', true)) {
            return [];
        }

        $limit = max(1, min(10, $limit ?? (int) config('blog_ai.competitors.discovery.max_results', 5)));
        $provider = strtolower((string) config('blog_ai.competitors.discovery.provider', 'auto'));
        $braveKey = trim((string) config('blog_ai.competitors.discovery.api_key', ''));
        $bingKey = trim((string) config('blog_ai.competitors.discovery.bing_api_key', ''));

        $raw = [];

        if ($provider === 'brave' || ($provider === 'auto' && $braveKey !== '')) {
            if ($braveKey !== '') {
                $raw = $this->discoverViaBrave($keyword, $limit, $braveKey);
            }
        }

        if ($raw === [] && ($provider === 'bing' || ($provider === 'auto' && $bingKey !== ''))) {
            if ($bingKey !== '') {
                $raw = $this->discoverViaBing($keyword, $limit, $bingKey);
            }
        }

        if ($raw === [] && ($provider === 'brave' || $provider === 'bing')) {
            // Exclusive provider without usable key: fall through to free scrapers.
            $raw = $this->discoverViaDuckDuckGo($keyword, $limit);
            if ($raw === []) {
                $raw = $this->discoverViaDuckDuckGoLite($keyword, $limit);
            }
        }

        if ($raw === [] && ! in_array($provider, ['brave', 'bing'], true)) {
            $raw = $this->discoverViaDuckDuckGo($keyword, $limit);
            if ($raw === []) {
                $raw = $this->discoverViaDuckDuckGoLite($keyword, $limit);
            }
        }

        return $this->normalizeResults($raw, $limit);
    }

    /**
     * @return list<string>
     */
    public function discoverUrls(string $keyword, ?int $limit = null): array
    {
        return array_values(array_map(
            fn (array $row) => $row['url'],
            $this->discover($keyword, $limit)
        ));
    }

    /**
     * @return list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>
     */
    private function discoverViaBrave(string $keyword, int $limit, string $apiKey): array
    {
        try {
            $query = $keyword.' Bangladesh OR বাংলাদেশ';
            $response = Http::timeout(12)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Subscription-Token' => $apiKey,
                ])
                ->get('https://api.search.brave.com/res/v1/web/search', [
                    'q' => $query,
                    'count' => min(20, $limit + 5),
                    'country' => 'BD',
                    'search_lang' => 'bn',
                ]);

            if (! $response->successful()) {
                Log::debug('Brave competitor discovery failed', ['status' => $response->status()]);

                return [];
            }

            $web = $response->json('web.results');
            if (! is_array($web)) {
                return [];
            }

            $out = [];
            $rank = 1;
            foreach ($web as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $out[] = [
                    'url' => $url,
                    'title' => filled($row['title'] ?? null) ? (string) $row['title'] : null,
                    'snippet' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                    'rank' => $rank++,
                    'provider' => 'brave',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::debug('Brave competitor discovery error', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>
     */
    private function discoverViaBing(string $keyword, int $limit, string $apiKey): array
    {
        try {
            $query = $keyword.' Bangladesh OR বাংলাদেশ';
            $response = Http::timeout(12)
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get('https://api.bing.microsoft.com/v7.0/search', [
                    'q' => $query,
                    'count' => min(20, $limit + 5),
                    'mkt' => 'en-BD',
                    'responseFilter' => 'Webpages',
                ]);

            if (! $response->successful()) {
                Log::debug('Bing competitor discovery failed', ['status' => $response->status()]);

                return [];
            }

            $pages = $response->json('webPages.value');
            if (! is_array($pages)) {
                return [];
            }

            $out = [];
            $rank = 1;
            foreach ($pages as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $out[] = [
                    'url' => $url,
                    'title' => filled($row['name'] ?? null) ? (string) $row['name'] : null,
                    'snippet' => filled($row['snippet'] ?? null) ? (string) $row['snippet'] : null,
                    'rank' => $rank++,
                    'provider' => 'bing',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::debug('Bing competitor discovery error', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>
     */
    private function discoverViaDuckDuckGo(string $keyword, int $limit): array
    {
        try {
            $query = $keyword.' Bangladesh OR বাংলাদেশ';
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => 'WooEasyLifeBlogBot/1.0 (+competitor-discovery)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'bn-BD,bn;q=0.9,en;q=0.8',
                ])
                ->asForm()
                ->post('https://html.duckduckgo.com/html/', [
                    'q' => $query,
                ]);

            if (! $response->successful()) {
                // GET fallback (some environments block POST).
                $response = Http::timeout(12)
                    ->withHeaders([
                        'User-Agent' => 'WooEasyLifeBlogBot/1.0 (+competitor-discovery)',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->get('https://html.duckduckgo.com/html/', ['q' => $query]);
            }

            if (! $response->successful()) {
                return [];
            }

            return $this->parseDuckDuckGoHtml((string) $response->body(), $limit, 'duckduckgo');
        } catch (\Throwable $e) {
            Log::debug('DuckDuckGo competitor discovery error', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>
     */
    private function discoverViaDuckDuckGoLite(string $keyword, int $limit): array
    {
        try {
            $query = $keyword.' Bangladesh';
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => 'WooEasyLifeBlogBot/1.0 (+competitor-discovery)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->asForm()
                ->post('https://lite.duckduckgo.com/lite/', [
                    'q' => $query,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $html = (string) $response->body();
            $out = [];
            $rank = 1;

            if (preg_match_all(
                '/<a[^>]+rel="nofollow"[^>]+href="(https?:\/\/[^"]+)"[^>]*>(.*?)<\/a>/is',
                $html,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $match) {
                    $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $title = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if (! filter_var($url, FILTER_VALIDATE_URL)) {
                        continue;
                    }
                    $out[] = [
                        'url' => $url,
                        'title' => $title !== '' ? $title : null,
                        'snippet' => null,
                        'rank' => $rank++,
                        'provider' => 'duckduckgo_lite',
                    ];
                    if (count($out) >= $limit + 8) {
                        break;
                    }
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Log::debug('DuckDuckGo lite discovery error', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>
     */
    private function parseDuckDuckGoHtml(string $html, int $limit, string $provider): array
    {
        $out = [];
        $rank = 1;

        if (preg_match_all(
            '/class="result__a"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $url = $this->unwrapDuckDuckGoUrl($href);
                if ($url === null) {
                    continue;
                }
                $out[] = [
                    'url' => $url,
                    'title' => $title !== '' ? $title : null,
                    'snippet' => null,
                    'rank' => $rank++,
                    'provider' => $provider,
                ];
                if (count($out) >= $limit + 8) {
                    break;
                }
            }
        }

        return $out;
    }

    private function unwrapDuckDuckGoUrl(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (str_contains($href, 'uddg=')) {
            $parts = parse_url($href);
            if (is_array($parts) && isset($parts['query'])) {
                parse_str($parts['query'], $query);
                if (! empty($query['uddg'])) {
                    $href = urldecode((string) $query['uddg']);
                }
            }
        }

        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        if (! preg_match('#^https?://#i', $href)) {
            return null;
        }

        return $href;
    }

    /**
     * @param  list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>  $raw
     * @return list<array{url: string, title: string|null, snippet: string|null, rank: int, provider: string}>
     */
    private function normalizeResults(array $raw, int $limit): array
    {
        $excluded = $this->excludedHosts();
        $seen = [];
        $out = [];
        $rank = 1;

        foreach ($raw as $row) {
            $url = $this->normalizeUrl((string) ($row['url'] ?? ''));
            if ($url === null) {
                continue;
            }
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || $this->hostIsExcluded($host, $excluded)) {
                continue;
            }
            if (preg_match('#\.(pdf|jpg|jpeg|png|gif|webp|zip|mp4)(\?|$)#i', $url)) {
                continue;
            }
            $key = $host.parse_url($url, PHP_URL_PATH);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'url' => $url,
                'title' => filled($row['title'] ?? null) ? Str::limit((string) $row['title'], 180, '') : null,
                'snippet' => filled($row['snippet'] ?? null) ? Str::limit((string) $row['snippet'], 280, '') : null,
                'rank' => $rank++,
                'provider' => (string) ($row['provider'] ?? 'unknown'),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $hostLower = strtolower($host);
        if ($hostLower === 'localhost' || str_ends_with($hostLower, '.localhost')) {
            return null;
        }
        if (preg_match('/\.(local|internal|lan|home|corp)$/i', $hostLower)) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function excludedHosts(): array
    {
        $hosts = config('blog_ai.competitors.discovery.exclude_hosts', []);
        $hosts = is_array($hosts) ? $hosts : [];

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = $appHost;
        }

        return array_values(array_unique(array_map(
            fn ($h) => strtolower(trim((string) $h)),
            $hosts
        )));
    }

    /**
     * @param  list<string>  $excluded
     */
    private function hostIsExcluded(string $host, array $excluded): bool
    {
        $host = strtolower($host);
        foreach ($excluded as $needle) {
            if ($needle === '') {
                continue;
            }
            if ($host === $needle || str_ends_with($host, '.'.$needle)) {
                return true;
            }
        }

        return false;
    }
}
