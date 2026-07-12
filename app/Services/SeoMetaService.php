<?php

namespace App\Services;

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
        $ogImage = $this->absoluteUrl((string) ($config['og_image'] ?? config('seo.default_og_image', '/images/seo/og-default.png')));
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
            'og_type' => $config['og_type'] ?? 'website',
            'robots' => $config['robots'] ?? 'index,follow',
            'html_lang' => $htmlLang,
            'hreflang' => $hreflang,
            'faqs' => $faqs,
            'breadcrumbs' => $this->normalizeBreadcrumbs($breadcrumbs),
            'prerender_h1' => $prerenderH1,
            'prerender_lead' => $prerenderLead,
            'json_ld' => $this->buildJsonLd($title, $description, $canonical, $faqs, $breadcrumbs, $ogImage),
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
                'inLanguage' => 'bn-BD',
            ],
            [
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description,
                'isPartOf' => ['@id' => $this->absoluteUrl('/').'#website'],
                'inLanguage' => 'bn-BD',
                'primaryImageOfPage' => [
                    '@type' => 'ImageObject',
                    'url' => $ogImage,
                ],
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => $siteName,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web, Android',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'BDT',
                    'description' => 'Free trial available',
                ],
                'description' => $org['description'] ?? $description,
                'url' => $this->absoluteUrl('/'),
                'image' => $ogImage,
            ],
        ];

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
}
