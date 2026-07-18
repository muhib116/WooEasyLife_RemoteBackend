<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogPostAnalytics;
use App\Services\BlogAi\BlogLearningService;
use App\Services\BlogSeoQuality;
use App\Services\BlogService;
use App\Services\Facebook\FacebookPagePublisher;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller
{
    public function __construct(
        private BlogService $blogService,
        private BlogSeoQuality $blogSeoQuality,
        private FacebookPagePublisher $facebookPagePublisher,
    ) {}

    public function index(Request $request): Response
    {
        $analyticsBySlug = BlogPostAnalytics::query()
            ->get(['slug', 'views_28d', 'cta_clicks_28d', 'engagement_score', 'gsc_clicks_28d'])
            ->keyBy('slug');

        $posts = BlogPost::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (BlogPost $post) use ($analyticsBySlug) {
                $stats = $analyticsBySlug->get($post->slug);

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'locale' => $post->locale,
                    'cluster' => $post->cluster,
                    'status' => $post->status,
                    'excerpt' => $post->excerpt,
                    'focus_keyword' => $post->focus_keyword,
                    'ai_quality_score' => $post->ai_quality_score,
                    'ai_quality_breakdown' => $post->ai_quality_breakdown,
                    'published_at' => optional($post->published_at)?->toIso8601String(),
                    'updated_at' => optional($post->updated_at)?->toIso8601String(),
                    'public_path' => filled($post->slug) ? $post->publicPath() : null,
                    'public_url' => ($post->status === 'published' && filled($post->slug))
                        ? $post->publicUrl()
                        : null,
                    'facebook_post_id' => $post->facebook_post_id,
                    'facebook_shared_at' => optional($post->facebook_shared_at)?->toIso8601String(),
                    'facebook_permalink' => filled($post->facebook_post_id)
                        ? $this->facebookPagePublisher->permalinkFor((string) $post->facebook_post_id)
                        : null,
                    'analytics' => $stats ? [
                        'views_28d' => $stats->views_28d,
                        'cta_clicks_28d' => $stats->cta_clicks_28d,
                        'gsc_clicks_28d' => $stats->gsc_clicks_28d,
                        'engagement_score' => $stats->engagement_score,
                    ] : null,
                ];
            });

        return Inertia::render('BlogPosts/Index', [
            'posts' => $posts,
            'learning' => app(BlogLearningService::class)->adminDashboard(),
            'facebook_sharing' => [
                'enabled' => $this->facebookPagePublisher->configured(),
                'public_links' => $this->facebookPagePublisher->isPublicShareUrl(),
                'share_base_url' => $this->facebookPagePublisher->shareBaseUrl(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('BlogPosts/Form', [
            'post' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $userId = $request->user()?->id;

        $post = BlogPost::create([
            ...$data,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()
            ->route('blogPosts.edit', $post)
            ->with('success', 'Blog post created.');
    }

    public function edit(BlogPost $blogPost): Response
    {
        return Inertia::render('BlogPosts/Form', [
            'post' => [
                'id' => $blogPost->id,
                'title' => $blogPost->title,
                'slug' => $blogPost->slug,
                'locale' => $blogPost->locale,
                'cluster' => $blogPost->cluster,
                'status' => $blogPost->status,
                'excerpt' => $blogPost->excerpt,
                'meta_title' => $blogPost->meta_title,
                'meta_description' => $blogPost->meta_description,
                'focus_keyword' => $blogPost->focus_keyword,
                'og_image' => $blogPost->og_image,
                'og_image_url' => $this->publicImageUrl($blogPost->og_image),
                'robots' => $blogPost->robots,
                'author_name' => $blogPost->author_name,
                'faqs_json' => $blogPost->faqs_json ?? [],
                'body_html' => $blogPost->body_html,
                'published_at' => optional($blogPost->published_at)?->format('Y-m-d\TH:i'),
                'ai_quality_score' => $blogPost->ai_quality_score,
                'ai_quality_breakdown' => $blogPost->ai_quality_breakdown,
                'ai_run_id' => $blogPost->ai_run_id,
                'public_path' => filled($blogPost->slug) ? $blogPost->publicPath() : null,
                'public_url' => ($blogPost->status === 'published' && filled($blogPost->slug))
                    ? $blogPost->publicUrl()
                    : null,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, BlogPost $blogPost): RedirectResponse
    {
        $data = $this->validated($request, $blogPost->id);

        $blogPost->update([
            ...$data,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('blogPosts.edit', $blogPost)
            ->with('success', 'Blog post updated.');
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $blogPost->delete();

        return redirect()
            ->route('blogPosts.index')
            ->with('success', 'Blog post deleted.');
    }

    public function shareToFacebook(Request $request, BlogPost $blogPost): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'force' => ['sometimes', 'boolean'],
        ]);

        if (! $blogPost->isPublished()) {
            return redirect()
                ->route('blogPosts.index')
                ->with('error', 'Publish the post before sharing to Facebook.');
        }

        if (filled($blogPost->facebook_post_id) && empty($data['force'])) {
            return redirect()
                ->route('blogPosts.index')
                ->with('error', 'Already shared to Facebook. Check “Post again” to share another time.');
        }

        try {
            $result = $this->facebookPagePublisher->shareBlogPost(
                $blogPost,
                isset($data['message']) ? (string) $data['message'] : null,
            );
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('blogPosts.index')
                ->with('error', $e->getMessage());
        }

        $blogPost->update([
            'facebook_post_id' => $result['post_id'],
            'facebook_shared_at' => now(),
        ]);

        return redirect()
            ->route('blogPosts.index')
            ->with('success', 'Shared to Facebook Page.')
            ->with('facebook_permalink', $result['permalink']);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'upload' => ['required', 'image', 'max:4096'],
        ]);

        $path = $request->file('upload')->store('blog/'.now()->format('Y/m'), 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
            'path' => $path,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        if ($request->input('cluster') === '') {
            $request->merge(['cluster' => null]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                Rule::requiredIf(fn () => $request->input('status') === 'published'),
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($ignoreId),
            ],
            'locale' => ['required', Rule::in(BlogPost::LOCALES)],
            'cluster' => ['nullable', 'string', 'max:64', Rule::in(array_keys(config('blog_ai.clusters', [])))],
            'status' => ['required', Rule::in(BlogPost::STATUSES)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'focus_keyword' => [
                Rule::requiredIf(fn () => $request->input('status') === 'published'),
                'nullable',
                'string',
                'max:120',
            ],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'robots' => ['nullable', 'string', 'max:64'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'faqs_json' => ['nullable', 'array', 'max:12'],
            'faqs_json.*.q' => ['required_with:faqs_json', 'string', 'max:200'],
            'faqs_json.*.a' => ['required_with:faqs_json', 'string', 'max:1000'],
            'body_html' => ['required', 'string', 'max:200000'],
            'published_at' => ['nullable', 'date'],
            'ai_quality_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ai_quality_breakdown' => ['nullable', 'array'],
            'ai_run_id' => ['nullable', 'integer', 'exists:blog_ai_runs,id'],
        ], [
            'slug.required' => 'Add an English SEO slug before publishing (e.g. fake-order-atkabo).',
            'slug.regex' => 'Slug must be lowercase Latin letters, numbers, and hyphens only.',
            'focus_keyword.required' => 'Set a focus keyword before publishing.',
            'body_html.max' => 'Body is too large (max ~200KB). Shorten the content or compress images.',
        ]);

        $slug = trim((string) ($validated['slug'] ?? ''));
        $status = $validated['status'];

        if ($slug === '') {
            if ($status === 'published') {
                throw ValidationException::withMessages([
                    'slug' => 'Add an English SEO slug before publishing (e.g. fake-order-atkabo).',
                ]);
            }

            $slug = BlogPost::makeSlug(
                (string) ($validated['focus_keyword'] ?: $validated['title']),
                $ignoreId,
            );
        }

        if ($status === 'published' && BlogPost::isPlaceholderSlug($slug)) {
            throw ValidationException::withMessages([
                'slug' => 'Replace the auto slug with a readable English slug before publishing.',
            ]);
        }

        $publishedAt = $validated['published_at'] ?? null;
        if ($publishedAt === '') {
            $publishedAt = null;
        }

        if ($status === 'published') {
            $publishedAt = $publishedAt ?: now();
        }

        if ($status === 'draft') {
            $publishedAt = $publishedAt ?: null;
        }

        $bodyHtml = BlogHtmlSanitizer::sanitize($validated['body_html']);
        $focusKeyword = $validated['focus_keyword'] ?? null;
        $locale = $validated['locale'];

        if ($status === 'published') {
            $seoErrors = $this->blogSeoQuality->publishValidationErrors(
                title: (string) $validated['title'],
                bodyHtml: $bodyHtml,
                focusKeyword: is_string($focusKeyword) ? $focusKeyword : null,
                metaDescription: (string) ($validated['meta_description'] ?? $validated['excerpt'] ?? ''),
                slug: $slug,
                locale: $locale,
                ignorePostId: $ignoreId,
                faqs: is_array($validated['faqs_json'] ?? null) ? $validated['faqs_json'] : [],
                ogImage: isset($validated['og_image']) ? (string) $validated['og_image'] : null,
            );

            if ($seoErrors !== []) {
                throw ValidationException::withMessages($seoErrors);
            }
        }

        $data = [
            'title' => $validated['title'],
            'slug' => $slug,
            'locale' => $locale,
            'cluster' => $validated['cluster'] ?? null,
            'status' => $status,
            'excerpt' => $validated['excerpt'] ?? null,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'focus_keyword' => $focusKeyword,
            'og_image' => $this->normalizeOgImage($validated['og_image'] ?? null),
            'robots' => $validated['robots'] ?? 'index,follow',
            'author_name' => $validated['author_name'] ?? null,
            'faqs_json' => $this->normalizeFaqs($validated['faqs_json'] ?? null),
            'body_html' => $bodyHtml,
            'published_at' => $publishedAt,
        ];

        if ($request->exists('ai_quality_score')) {
            $data['ai_quality_score'] = $validated['ai_quality_score'];
        }
        if ($request->exists('ai_quality_breakdown')) {
            $data['ai_quality_breakdown'] = $validated['ai_quality_breakdown'] ?? null;
        }
        if ($request->exists('ai_run_id')) {
            $data['ai_run_id'] = $validated['ai_run_id'] ?? null;
        }

        return $data;
    }

    /**
     * @return list<array{q: string, a: string}>|null
     */
    private function normalizeFaqs(mixed $faqs): ?array
    {
        if (! is_array($faqs) || $faqs === []) {
            return null;
        }

        $normalized = collect($faqs)
            ->filter(fn ($row) => is_array($row) && filled($row['q'] ?? null) && filled($row['a'] ?? null))
            ->map(fn (array $row) => [
                'q' => Str::limit(trim((string) $row['q']), 200, ''),
                'a' => Str::limit(trim((string) $row['a']), 1000, ''),
            ])
            ->take(12)
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return array{
     *     locales: list<string>,
     *     statuses: list<string>,
     *     robots: list<string>,
     *     markdown_slugs: list<string>
     * }
     */
    private function options(): array
    {
        return [
            'locales' => BlogPost::LOCALES,
            'statuses' => BlogPost::STATUSES,
            'robots' => [
                'index,follow',
                'noindex,follow',
                'index,nofollow',
                'noindex,nofollow',
            ],
            'markdown_slugs' => $this->blogService->markdownSlugs(),
            'clusters' => config('blog_ai.clusters', []),
            'seo' => [
                'min_body_words' => (int) config('blog_ai.min_body_words', 800),
                'min_faqs' => (int) config('blog_ai.seo_quality.min_faqs', 5),
                'min_internal_links' => (int) config('blog_ai.seo_quality.min_internal_links', 2),
            ],
        ];
    }

    private function normalizeOgImage(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $public = rtrim(asset('storage'), '/').'/';

        if (Str::startsWith($value, $public)) {
            return Str::after($value, $public);
        }

        return $value;
    }

    private function publicImageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset('storage/'.$path);
    }
}
