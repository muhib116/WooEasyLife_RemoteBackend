<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\BlogService;
use App\Services\LandingPageService;
use App\Services\LandingSettingsService;
use App\Services\SeoMetaService;
use App\Support\WhatsappLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class EnglishMarketingController extends Controller
{
    public function home(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->page($request, $landing, $seo, $landingSettings, 'en_home', 'Seo/EnHome');
    }

    public function bdFraudChecker(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->page($request, $landing, $seo, $landingSettings, 'en_bd_fraud_checker', 'Seo/EnBdFraudChecker');
    }

    public function blogIndex(
        BlogService $blog,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $seoMeta = $seo->forPage('en_blog_index');
        $posts = collect($blog->all('en'))
            ->map(fn (array $post) => [
                'title' => $post['title'],
                'description' => $post['description'],
                'date' => $post['date'],
                'slug' => $post['slug'],
                'locale' => $post['locale'],
            ])
            ->all();

        return Inertia::render('Blog/Index', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'posts' => $posts,
            'whatsappUrl' => WhatsappLink::url($landingSettings->adminWhatsapp()),
            'locale' => 'en',
            'indexPath' => '/en/blog',
        ])->withViewData(['seo' => $seoMeta]);
    }

    private function page(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
        string $seoPage,
        string $component,
    ): Response {
        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage($seoPage);

        return Inertia::render($component, [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'fraudCheck' => $payload['fraudCheck'] ?? [],
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                'Hi, I want a WooEasyLife subscription.',
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
        ])->withViewData(['seo' => $seoMeta]);
    }
}
