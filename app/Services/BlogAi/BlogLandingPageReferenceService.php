<?php

namespace App\Services\BlogAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Build a landing-page reference payload (URL + SEO config + optional live snapshot)
 * so Blog AI treats the landing as content source of truth.
 */
class BlogLandingPageReferenceService
{
    /**
     * @param  array<string, mixed>  $landing  from BlogLandingContextService::forCluster
     * @return array<string, mixed>
     */
    public function forLanding(array $landing): array
    {
        $path = is_string($landing['primary_path'] ?? null) ? trim((string) $landing['primary_path']) : '';
        $url = $this->absoluteUrl($path);

        $pages = is_array($landing['pages'] ?? null) ? $landing['pages'] : [];
        $primaryPage = $pages[0] ?? null;

        $reference = [
            'role' => 'content_source_of_truth',
            'primary_path' => $path !== '' ? $path : null,
            'primary_url' => $url,
            'angle_hint' => (string) ($landing['angle_hint'] ?? ''),
            'claims' => is_array($landing['claims'] ?? null) ? array_values($landing['claims']) : [],
            'must_link_paths' => is_array($landing['must_link_paths'] ?? null) ? $landing['must_link_paths'] : [],
            'seo_page' => is_array($primaryPage) ? $primaryPage : null,
            'live_snapshot' => null,
            'instructions' => [
                'Use this landing page as product/content truth (H1, lead, FAQs, claims).',
                'Do NOT copy the landing layout as the blog skeleton — use editorial playbook + article_type skeleton for blog flow.',
                'Blog must soft-link primary_path and stay aligned with landing problem/solution.',
            ],
        ];

        if ($url && $this->shouldFetchLive()) {
            $reference['live_snapshot'] = $this->fetchOwnLandingSnapshot($url);
        }

        return $reference;
    }

    private function shouldFetchLive(): bool
    {
        if (! (bool) config('blog_ai.landing_reference.fetch_live', true)) {
            return false;
        }

        // Avoid network in PHPUnit unless a test opts in via config.
        if (app()->runningUnitTests() && ! (bool) config('blog_ai.landing_reference.fetch_live_in_tests', false)) {
            return false;
        }

        return true;
    }

    public function absoluteUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $base = rtrim((string) (config('blog_ai.landing_reference.public_base_url') ?: config('app.url')), '/');

        return $base.$path;
    }

    /**
     * @return array{url: string, title: ?string, h1: ?string, headings: list<string>, excerpt: ?string, status: int|null, source: string}|null
     */
    public function fetchOwnLandingSnapshot(string $url): ?array
    {
        if (! $this->isAllowedOwnHost($url)) {
            return [
                'url' => $url,
                'title' => null,
                'h1' => null,
                'headings' => [],
                'excerpt' => null,
                'status' => null,
                'source' => 'blocked_host',
            ];
        }

        $timeout = max(3, (int) config('blog_ai.landing_reference.fetch_timeout', 8));
        $maxBytes = max(50_000, (int) config('blog_ai.landing_reference.max_html_bytes', 400_000));

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'WooEasyLifeBlogAI/1.0 (+landing-reference)',
                    'Accept' => 'text/html',
                ])
                ->withOptions(['allow_redirects' => false])
                ->get($url);

            // Follow one same-host redirect safely.
            if ($response->redirect() && filled($response->header('Location'))) {
                $next = $this->resolveRedirect($url, (string) $response->header('Location'));
                if ($next && $this->isAllowedOwnHost($next)) {
                    $response = Http::timeout($timeout)
                        ->withHeaders([
                            'User-Agent' => 'WooEasyLifeBlogAI/1.0 (+landing-reference)',
                            'Accept' => 'text/html',
                        ])
                        ->withOptions(['allow_redirects' => false])
                        ->get($next);
                    $url = $next;
                }
            }

            $html = (string) $response->body();
            if (strlen($html) > $maxBytes) {
                $html = substr($html, 0, $maxBytes);
            }

            return [
                'url' => $url,
                'title' => $this->firstMatch($html, '/<title[^>]*>(.*?)<\/title>/is'),
                'h1' => $this->firstMatch($html, '/<h1[^>]*>(.*?)<\/h1>/is'),
                'headings' => $this->extractHeadings($html),
                'excerpt' => $this->excerpt($html),
                'status' => $response->status(),
                'source' => 'live_fetch',
            ];
        } catch (\Throwable $e) {
            Log::debug('blog_ai.landing_reference.fetch_failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'url' => $url,
                'title' => null,
                'h1' => null,
                'headings' => [],
                'excerpt' => null,
                'status' => null,
                'source' => 'fetch_failed',
            ];
        }
    }

    private function isAllowedOwnHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        $allowed = array_values(array_filter(array_map(
            'strtolower',
            config('blog_ai.landing_reference.allowed_hosts', [])
        )));

        if ($allowed === []) {
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $publicHost = strtolower((string) parse_url((string) config('blog_ai.landing_reference.public_base_url', ''), PHP_URL_HOST));
            $exclude = array_map('strtolower', config('blog_ai.competitors.discovery.exclude_hosts', []));
            $allowed = array_values(array_unique(array_filter([$appHost, $publicHost, ...$exclude])));
        }

        foreach ($allowed as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        // Local dev
        return in_array($host, ['localhost', '127.0.0.1'], true);
    }

    private function resolveRedirect(string $fromUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($fromUrl);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $base = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $base .= ':'.$parts['port'];
        }
        if (str_starts_with($location, '/')) {
            return $base.$location;
        }

        return $base.'/'.$location;
    }

    /**
     * @return list<string>
     */
    private function extractHeadings(string $html): array
    {
        $out = [];
        if (preg_match_all('/<h([1-3])[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $text = $this->cleanText($row[2] ?? '');
                if ($text !== '') {
                    $out[] = $text;
                }
                if (count($out) >= 12) {
                    break;
                }
            }
        }

        return $out;
    }

    private function excerpt(string $html): ?string
    {
        $plain = $this->cleanText(strip_tags(
            preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html
        ));
        if ($plain === '') {
            return null;
        }

        return Str::limit($plain, 1200, '');
    }

    private function firstMatch(string $html, string $pattern): ?string
    {
        if (! preg_match($pattern, $html, $m)) {
            return null;
        }
        $text = $this->cleanText($m[1] ?? '');

        return $text !== '' ? $text : null;
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
