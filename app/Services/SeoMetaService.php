<?php

namespace App\Services;

use Illuminate\Support\Str;

class SeoMetaService
{
    public function __construct(
        protected LandingSettingsService $landingSettings,
    ) {
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function forPage(string $page, array $overrides = []): array
    {
        $pages = config('seo.pages', []);
        $config = array_merge($pages[$page] ?? [], $overrides);

        if ($config === []) {
            return [];
        }

        $title = (string) ($config['title'] ?? config('seo.site_name', config('app.name')));
        $description = (string) ($config['description'] ?? '');
        $canonicalPath = (string) ($config['canonical_path'] ?? '/');
        $canonical = $this->absoluteUrl($canonicalPath);
        $ogImagePath = (string) ($config['og_image'] ?? config('seo.default_og_image', '/images/seo/og-default.jpg'));
        $ogImage = $this->absoluteUrl($ogImagePath);
        $ogImage = $this->withOgImageCacheBust($ogImage, $ogImagePath);
        $faqs = $config['faqs'] ?? [];
        $breadcrumbs = $config['breadcrumbs'] ?? [];
        $prerenderH1 = (string) ($config['prerender_h1'] ?? $title);
        $prerenderLead = (string) ($config['prerender_lead'] ?? $description);
        $htmlLang = (string) ($config['html_lang'] ?? config('seo.html_lang', 'bn-BD'));
        $hreflang = $this->buildHreflang($config['hreflang_paths'] ?? [
            $htmlLang => $canonicalPath,
            'x-default' => $canonicalPath,
        ]);

        return [
            'page' => $page,
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'canonical_path' => $canonicalPath,
            'og_image' => $ogImage,
            'og_image_width' => (int) config('seo.og_image_width', 1200),
            'og_image_height' => (int) config('seo.og_image_height', 630),
            'og_image_type' => str_ends_with(strtolower(parse_url($ogImagePath, PHP_URL_PATH) ?: $ogImagePath), '.webp')
                ? 'image/webp'
                : 'image/jpeg',
            'facebook_app_id' => filled(config('seo.facebook_app_id'))
                ? (string) config('seo.facebook_app_id')
                : null,
            'og_type' => $config['og_type'] ?? 'website',
            'robots' => $config['robots'] ?? 'index,follow',
            'html_lang' => $htmlLang,
            'hreflang' => $hreflang,
            'faqs' => $faqs,
            'breadcrumbs' => $this->normalizeBreadcrumbs($breadcrumbs),
            'prerender_h1' => $prerenderH1,
            'prerender_lead' => $prerenderLead,
            'json_ld' => $this->buildJsonLd($title, $description, $canonical, $faqs, $breadcrumbs, $ogImage, $config),
        ];
    }

    /**
     * Lightweight SEO payload for marketing 404 pages.
     *
     * @return array<string, mixed>
     */
    public function forNotFound(): array
    {
        return $this->forPage('home', [
            'title' => 'পেজ পাওয়া যায়নি — WooEasyLife',
            'description' => 'এই পেজটি নেই। BD fraud checker, প্রাইসিং বা হোমপেজে ফিরে যান।',
            'canonical_path' => '/',
            'robots' => 'noindex,follow',
            'prerender_h1' => 'পেজ পাওয়া যায়নি',
            'prerender_lead' => 'লিংকটি ভুল হতে পারে। নিচের পেজগুলো থেকে চালিয়ে যান।',
            'faqs' => [],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => '404', 'path' => '/'],
            ],
        ]);
    }

    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public function sitemapEntries(): array
    {
        $lastmod = now()->toAtomString();
        $entries = [];

        foreach (config('seo.sitemap.paths', []) as $item) {
            $path = (string) ($item['path'] ?? '/');
            $entries[] = [
                'loc' => $this->absoluteUrl($path),
                'lastmod' => $lastmod,
                'changefreq' => (string) ($item['changefreq'] ?? 'monthly'),
                'priority' => (string) ($item['priority'] ?? '0.5'),
            ];
        }

        return $entries;
    }

    public function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim((string) config('app.url'), '/');
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return $base.'/';
        }

        return $base.$path;
    }

    /**
     * Bust social scrapers' cache when the asset file changes on disk.
     */
    private function withOgImageCacheBust(string $absoluteUrl, string $relativePath): string
    {
        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return $absoluteUrl;
        }

        $file = public_path(ltrim($relativePath, '/'));
        if (! is_file($file)) {
            return $absoluteUrl;
        }

        $version = (string) filemtime($file);
        $separator = str_contains($absoluteUrl, '?') ? '&' : '?';

        return $absoluteUrl.$separator.'v='.$version;
    }

    /**
     * @param  array<string, string>  $paths  hreflang => path
     * @return list<array{hreflang: string, url: string}>
     */
    private function buildHreflang(array $paths): array
    {
        $out = [];

        foreach ($paths as $hreflang => $path) {
            $out[] = [
                'hreflang' => (string) $hreflang,
                'url' => $this->absoluteUrl((string) $path),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{name: string, path: string}>  $breadcrumbs
     * @return list<array{name: string, path: string, url: string}>
     */
    private function normalizeBreadcrumbs(array $breadcrumbs): array
    {
        return array_map(fn (array $crumb) => [
            'name' => (string) ($crumb['name'] ?? ''),
            'path' => (string) ($crumb['path'] ?? '/'),
            'url' => $this->absoluteUrl((string) ($crumb['path'] ?? '/')),
        ], $breadcrumbs);
    }

    /**
     * @return list<string>
     */
    private function sameAsLinks(): array
    {
        $configured = config('seo.organization.same_as', []);
        $playStore = $this->landingSettings->playStoreUrl();

        return array_values(array_unique(array_filter([
            ...$configured,
            $playStore,
        ])));
    }

    /**
     * @param  list<array{q: string, a: string}>  $faqs
     * @param  list<array{name: string, path: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    private function buildJsonLd(
        string $title,
        string $description,
        string $canonical,
        array $faqs,
        array $breadcrumbs,
        string $ogImage,
        array $config = [],
    ): array {
        $org = config('seo.organization', []);
        $siteName = (string) config('seo.site_name', 'WooEasyLife');
        $logo = $this->absoluteUrl('/apple-touch-icon.png');
        $sameAs = $this->sameAsLinks();

        $graphs = [
            [
                '@type' => 'Organization',
                '@id' => $this->absoluteUrl('/').'#organization',
                'name' => $org['name'] ?? $siteName,
                'url' => $this->absoluteUrl('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $logo,
                    'width' => 180,
                    'height' => 180,
                ],
                'description' => $org['description'] ?? $description,
                'sameAs' => $sameAs,
            ],
            [
                '@type' => 'WebSite',
                '@id' => $this->absoluteUrl('/').'#website',
                'name' => $siteName,
                'url' => $this->absoluteUrl('/'),
                'publisher' => ['@id' => $this->absoluteUrl('/').'#organization'],
                'inLanguage' => (string) ($config['html_lang'] ?? config('seo.html_lang', 'bn-BD')),
            ],
            [
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description,
                'isPartOf' => ['@id' => $this->absoluteUrl('/').'#website'],
                'inLanguage' => (string) ($config['html_lang'] ?? config('seo.html_lang', 'bn-BD')),
                'primaryImageOfPage' => [
                    '@type' => 'ImageObject',
                    'url' => $ogImage,
                ],
            ],
        ];

        // Only emit SoftwareApplication on product landing pages.
        // Repeating it on every calculator/guide URL is a common Semrush/Google invalidation cause.
        if (! empty($config['software_application'])) {
            $graphs[] = $this->softwareApplicationNode($siteName, $description, $ogImage, $org, $config);
        }

        if (($config['og_type'] ?? null) === 'article') {
            $authorName = (string) ($config['author_name'] ?? config('blog_ai.author_name', 'Muhibbullah Ansary'));
            $authorRole = (string) ($config['author_role'] ?? config('blog_ai.author_role', 'Developer of WooEasyLife'));
            $personId = $this->absoluteUrl('/').'#person-'.Str::slug($authorName);

            $graphs[] = [
                '@type' => 'Person',
                '@id' => $personId,
                'name' => $authorName,
                'jobTitle' => $authorRole,
                'worksFor' => ['@id' => $this->absoluteUrl('/').'#organization'],
            ];

            $graphs[] = [
                '@type' => 'BlogPosting',
                '@id' => $canonical.'#article',
                'headline' => $title,
                'description' => $description,
                'image' => [$ogImage],
                'datePublished' => $config['date_published'] ?? null,
                'dateModified' => $config['date_modified'] ?? ($config['date_published'] ?? null),
                'author' => ['@id' => $personId],
                'publisher' => ['@id' => $this->absoluteUrl('/').'#organization'],
                'mainEntityOfPage' => ['@id' => $canonical.'#webpage'],
                'inLanguage' => (string) ($config['html_lang'] ?? 'bn-BD'),
            ];

            if (filled($config['focus_keyword'] ?? null)) {
                $graphs[array_key_last($graphs)]['keywords'] = $config['focus_keyword'];
            }
        }

        if (count($breadcrumbs) > 1) {
            $graphs[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_values(array_map(function (array $crumb, int $index) {
                    return [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $crumb['name'],
                        'item' => $this->absoluteUrl((string) ($crumb['path'] ?? '/')),
                    ];
                }, $breadcrumbs, array_keys($breadcrumbs))),
            ];
        }

        if ($faqs !== []) {
            $graphs[] = [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['a'],
                    ],
                ], $faqs),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graphs,
        ];
    }

    /**
     * Google SoftwareApplication rich-result shape (name + offers.price required).
     *
     * @param  array<string, mixed>  $org
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function softwareApplicationNode(
        string $siteName,
        string $description,
        string $ogImage,
        array $org,
        array $config,
    ): array {
        $home = $this->absoluteUrl('/');
        $pricing = $this->absoluteUrl('/pricing');
        $playStore = filled(config('seo.organization.same_as'))
            ? collect(config('seo.organization.same_as'))->first(
                fn ($url) => is_string($url) && str_contains($url, 'play.google.com')
            )
            : null;

        $node = [
            '@type' => 'SoftwareApplication',
            '@id' => $home.'#software',
            'name' => $siteName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web browser, Android',
            'description' => (string) ($org['description'] ?? $description),
            'url' => $home,
            'image' => [
                '@type' => 'ImageObject',
                'url' => $ogImage,
            ],
            'author' => ['@id' => $home.'#organization'],
            'publisher' => ['@id' => $home.'#organization'],
            'offers' => [
                '@type' => 'Offer',
                // Google docs use numeric 0 for free apps.
                'price' => 0,
                'priceCurrency' => 'BDT',
                'availability' => 'https://schema.org/InStock',
                'url' => $pricing,
                'category' => 'FreeTrial',
                'description' => 'Free trial available — see pricing for paid plans',
            ],
            'featureList' => 'BD courier fraud checker, Fake order protection, Courier auto-entry (Pathao, Steadfast, RedX), WooCommerce plugin + Android app',
        ];

        if (is_string($playStore) && $playStore !== '') {
            $node['installUrl'] = $playStore;
            $node['downloadUrl'] = $playStore;
        }

        if (filled($config['software_version'] ?? null)) {
            $node['softwareVersion'] = (string) $config['software_version'];
        }

        return $node;
    }
}
