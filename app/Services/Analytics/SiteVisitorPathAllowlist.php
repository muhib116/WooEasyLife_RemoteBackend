<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;

/**
 * Builds the public-path allowlist for visitor tracking.
 * Merges static extras with all SEO canonical/hreflang paths so new landings
 * are covered automatically when added to config/seo.php.
 */
class SiteVisitorPathAllowlist
{
    /**
     * @return list<string>
     */
    public function prefixes(): array
    {
        $ttl = max(60, (int) config('site_visitors.allowlist_cache_seconds', 300));

        return Cache::remember('site_visitors:allowed_path_prefixes', $ttl, function () {
            return $this->build();
        });
    }

    public function forget(): void
    {
        Cache::forget('site_visitors:allowed_path_prefixes');
    }

    /**
     * @return list<string>
     */
    public function build(): array
    {
        $paths = [];

        foreach ((array) config('site_visitors.allowed_path_prefixes_extra', []) as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $this->normalize($path);
            }
        }

        // Always track blog posts under /blog/{slug}.
        $paths[] = '/blog';
        // English marketing tree.
        $paths[] = '/en';

        foreach ((array) config('seo.pages', []) as $page) {
            if (! is_array($page)) {
                continue;
            }
            if (! empty($page['canonical_path']) && is_string($page['canonical_path'])) {
                $paths[] = $this->normalize($page['canonical_path']);
            }
            foreach ((array) ($page['hreflang_paths'] ?? []) as $href) {
                if (is_string($href) && str_starts_with($href, '/')) {
                    $paths[] = $this->normalize($href);
                }
            }
        }

        $paths = array_values(array_unique(array_filter($paths)));
        sort($paths);

        return $paths;
    }

    private function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return rtrim($path, '/') ?: '/';
    }
}
