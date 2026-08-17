<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class SeoRobotsTxt
{
    /**
     * Legal/legacy paths that must stay out of the index even though they are public.
     *
     * @var list<string>
     */
    private const EXTRA_DISALLOW = [
        '/woodnutsbolts/privacy-policy',
        '/woodnutsbolts/terms-of-service',
    ];

    public static function body(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $sitemap = $base.'/sitemap.xml';

        $disallow = collect(config('site_visitors.blocked_path_prefixes', []))
            ->merge(self::EXTRA_DISALLOW)
            ->filter(fn ($path) => is_string($path) && str_starts_with($path, '/') && $path !== '/')
            ->unique()
            ->sort()
            ->values();

        $lines = ['User-agent: *'];
        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.$sitemap;
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Write public/robots.txt so LiteSpeed can serve it without PHP
     * (survives app downtime — the common GSC robots.txt fetch failure mode).
     */
    public static function syncPublicFile(?string $body = null): string
    {
        $body ??= self::body();
        $path = public_path('robots.txt');
        File::put($path, $body);

        return $path;
    }
}
