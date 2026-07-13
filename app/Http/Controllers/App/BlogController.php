<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\BlogService;
use App\Services\LandingSettingsService;
use App\Services\SeoMetaService;
use App\Support\WhatsappLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BlogController extends Controller
{
    public function index(
        Request $request,
        BlogService $blog,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $seoMeta = $seo->forPage('blog_index');
        $posts = collect($blog->all('bn'))
            ->map(fn (array $post) => [
                'title' => $post['title'],
                'description' => $post['description'],
                'date' => $post['date'],
                'slug' => $post['slug'],
                'locale' => $post['locale'],
            ])
            ->all();

        $whatsapp = $landingSettings->adminWhatsapp();

        return Inertia::render('Blog/Index', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'posts' => $posts,
            'whatsappUrl' => WhatsappLink::url($whatsapp),
        ])->withViewData(['seo' => $seoMeta]);
    }

    public function show(
        Request $request,
        string $slug,
        BlogService $blog,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $post = $blog->find($slug);

        if ($post === null) {
            throw new NotFoundHttpException;
        }

        $seoTitle = trim((string) ($post['meta_title'] ?? ''));
        if ($seoTitle === '') {
            $seoTitle = $post['title'].' | WooEasyLife ব্লগ';
        }

        $seoOverrides = [
            'title' => $seoTitle,
            'description' => $post['description'] !== ''
                ? $post['description']
                : $post['title'],
            'canonical_path' => '/blog/'.$post['slug'],
            'prerender_h1' => $post['title'],
            'prerender_lead' => $post['description'],
            'og_type' => 'article',
            'faqs' => [],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'ব্লগ', 'path' => '/blog'],
                ['name' => $post['title'], 'path' => '/blog/'.$post['slug']],
            ],
        ];

        if (! empty($post['og_image'])) {
            $seoOverrides['og_image'] = $post['og_image'];
        }

        if (! empty($post['robots'])) {
            $seoOverrides['robots'] = $post['robots'];
        }

        $seoMeta = $seo->forPage('blog_index', $seoOverrides);

        $whatsapp = $landingSettings->adminWhatsapp();

        return Inertia::render('Blog/Show', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'post' => [
                'title' => $post['title'],
                'description' => $post['description'],
                'date' => $post['date'],
                'slug' => $post['slug'],
                'locale' => $post['locale'],
                'html' => $post['html'],
            ],
            'whatsappUrl' => WhatsappLink::url($whatsapp),
        ])->withViewData(['seo' => $seoMeta]);
    }
}
