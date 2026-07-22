<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\SeoMetaService;
use Illuminate\Http\Response;

class LlmsTxtController extends Controller
{
    public function __invoke(SeoMetaService $seo): Response
    {
        return response($seo->llmsTxtBody(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
