<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\SeoMetaService;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(SeoMetaService $seo): Response
    {
        $sitemap = $seo->absoluteUrl('/sitemap.xml');

        $body = "User-agent: *\n"
            ."Disallow:\n"
            ."\n"
            .'Sitemap: '.$sitemap."\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
