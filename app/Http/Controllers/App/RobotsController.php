<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\SeoMetaService;
use Illuminate\Http\Response;

class RobotsController extends Controller
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

    public function __invoke(SeoMetaService $seo): Response
    {
        $sitemap = $seo->absoluteUrl('/sitemap.xml');

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

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
