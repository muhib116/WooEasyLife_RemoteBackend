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
            'author_name' => $post['author_name'] ?? config('blog_ai.author_name', 'Muhibbullah Ansary'),
            'author_role' => config('blog_ai.author_role', 'Developer of WooEasyLife'),
            'date_published' => $post['date_published'] ?? ($post['date'] ?: null),
            'date_modified' => $post['date_modified'] ?? ($post['date_published'] ?? ($post['date'] ?: null)),
            'focus_keyword' => $post['focus_keyword'] ?? null,
            'faqs' => $this->resolveFaqs($post),
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
                'html' => $post['html'] ?? $post['body'] ?? '',
                'author_name' => $post['author_name'] ?? config('blog_ai.author_name'),
                'og_image' => $post['og_image'] ?? null,
            ],
            'whatsappUrl' => WhatsappLink::url($whatsapp),
        ])->withViewData(['seo' => $seoMeta]);
    }

    /**
     * Prefer structured FAQs from CMS; fall back to HTML heuristics only if empty.
     *
     * @param  array<string, mixed>  $post
     * @return list<array{q: string, a: string}>
     */
    private function resolveFaqs(array $post): array
    {
        $structured = collect($post['faqs'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['q'] ?? null) && filled($row['a'] ?? null))
            ->map(fn (array $row) => [
                'q' => \Illuminate\Support\Str::limit(trim((string) $row['q']), 200, ''),
                'a' => \Illuminate\Support\Str::limit(trim((string) $row['a']), 500, ''),
            ])
            ->take(8)
            ->values()
            ->all();

        if ($structured !== []) {
            return $structured;
        }

        return $this->extractFaqsFromHtml((string) ($post['html'] ?? $post['body'] ?? ''));
    }

    /**
     * @return list<array{q: string, a: string}>
     */
    private function extractFaqsFromHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $faqs = [];
        if (preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>\s*(<p[^>]*>.*?<\/p>(?:\s*<p[^>]*>.*?<\/p>){0,2})/isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $q = trim(html_entity_decode(strip_tags($match[2])));
                $a = trim(html_entity_decode(strip_tags($match[3])));
                if ($q === '' || $a === '') {
                    continue;
                }
                // Require a clear question mark to avoid false-positive FAQ schema.
                if (! str_contains($q, '?') && ! str_contains($q, '؟') && ! str_ends_with($q, 'কি') && ! str_ends_with($q, 'কী')) {
                    continue;
                }
                $faqs[] = ['q' => $q, 'a' => \Illuminate\Support\Str::limit($a, 400, '')];
                if (count($faqs) >= 8) {
                    break;
                }
            }
        }

        return $faqs;
    }
}
