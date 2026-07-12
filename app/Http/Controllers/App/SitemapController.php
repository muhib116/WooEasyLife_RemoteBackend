<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\BlogService;
use App\Services\SeoMetaService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function __invoke(SeoMetaService $seo, BlogService $blog): Response
    {
        $entries = $seo->sitemapEntries();
        $lastmod = now()->toAtomString();

        foreach ($blog->all() as $post) {
            $slug = (string) ($post['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $postLastmod = $lastmod;
            if (! empty($post['date'])) {
                try {
                    $postLastmod = Carbon::parse($post['date'])->toAtomString();
                } catch (\Throwable) {
                    // keep default lastmod
                }
            }

            $entries[] = [
                'loc' => $seo->absoluteUrl('/blog/'.$slug),
                'lastmod' => $postLastmod,
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($entry['loc'])."</loc>\n";
            $xml .= '    <lastmod>'.e($entry['lastmod'])."</lastmod>\n";
            $xml .= '    <changefreq>'.e($entry['changefreq'])."</changefreq>\n";
            $xml .= '    <priority>'.e($entry['priority'])."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
