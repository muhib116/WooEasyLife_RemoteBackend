<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Support\SeoRobotsTxt;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class RobotsController extends Controller
{
    public const CACHE_KEY = 'seo:robots.txt';

    public const CACHE_SECONDS = 86400;

    public function __invoke(): Response
    {
        // Body is config-only (no DB) — always rebuild so Disallow/Sitemap cannot drift for 24h.
        $body = SeoRobotsTxt::body();
        SeoRobotsTxt::syncPublicFile($body);
        Cache::put(self::CACHE_KEY, $body, self::CACHE_SECONDS);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS.', stale-while-revalidate=3600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
