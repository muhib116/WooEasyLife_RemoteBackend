<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BlogService
{
    /**
     * @return list<array{
     *     title: string,
     *     description: string,
     *     meta_title: string|null,
     *     date: string,
     *     slug: string,
     *     locale: string,
     *     body: string,
     *     path: string|null,
     *     source: string,
     *     format: string,
     *     og_image: string|null,
     *     robots: string|null
     * }>
     */
    public function all(?string $locale = null): array
    {
        $posts = $this->mergedPosts();

        if ($locale !== null && $locale !== '') {
            $posts = $posts->filter(
                fn (array $post) => ($post['locale'] ?? 'bn') === $locale
            );
        }

        return $posts
            ->sortByDesc(fn (array $post) => $post['date'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     meta_title: string|null,
     *     date: string,
     *     slug: string,
     *     locale: string,
     *     body: string,
     *     path: string|null,
     *     source: string,
     *     format: string,
     *     og_image: string|null,
     *     robots: string|null,
     *     html: string
     * }|null
     */
    public function find(string $slug): ?array
    {
        $post = $this->mergedPosts()->first(
            fn (array $post) => ($post['slug'] ?? '') === $slug
        );

        if ($post === null) {
            return null;
        }

        $post['html'] = $this->renderHtml($post);

        return $post;
    }

    public function toHtml(string $body): string
    {
        // Blog show page already renders the post title as H1; drop a leading
        // markdown # heading so body content cannot emit a second H1.
        $body = (string) preg_replace('/\A\s*#\s+[^\n]+\n+/u', '', $body, 1);

        return Str::markdown($body);
    }

    /**
     * @param  array<string, mixed>  $post
     */
    public function renderHtml(array $post): string
    {
        if (($post['format'] ?? 'markdown') === 'html') {
            return BlogHtmlSanitizer::sanitize((string) ($post['body'] ?? ''));
        }

        return $this->toHtml((string) ($post['body'] ?? ''));
    }

    /**
     * Slugs used by filesystem markdown posts (for CMS collision warnings).
     *
     * @return list<string>
     */
    public function markdownSlugs(): array
    {
        return $this->scanMarkdownPosts()
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * DB published posts win over markdown files when slugs collide.
     *
     * @return Collection<int, array{
     *     title: string,
     *     description: string,
     *     meta_title: string|null,
     *     date: string,
     *     slug: string,
     *     locale: string,
     *     body: string,
     *     path: string|null,
     *     source: string,
     *     format: string,
     *     og_image: string|null,
     *     robots: string|null
     * }>
     */
    private function mergedPosts(): Collection
    {
        $database = $this->scanDatabasePosts()->keyBy('slug');
        $markdown = $this->scanMarkdownPosts()
            ->reject(fn (array $post) => $database->has($post['slug']))
            ->keyBy('slug');

        return $database->union($markdown)->values();
    }

    /**
     * @return Collection<int, array{
     *     title: string,
     *     description: string,
     *     meta_title: string|null,
     *     date: string,
     *     slug: string,
     *     locale: string,
     *     body: string,
     *     path: string|null,
     *     source: string,
     *     format: string,
     *     og_image: string|null,
     *     robots: string|null
     * }>
     */
    private function scanDatabasePosts(): Collection
    {
        try {
            return BlogPost::query()
                ->published()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BlogPost $post) => $this->normalizeDatabasePost($post))
                ->values();
        } catch (Throwable $e) {
            // Missing table / migration lag must not 500 the public blog.
            Log::warning('BlogService: CMS posts unavailable', [
                'message' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     meta_title: string|null,
     *     date: string,
     *     slug: string,
     *     locale: string,
     *     body: string,
     *     path: string|null,
     *     source: string,
     *     format: string,
     *     og_image: string|null,
     *     robots: string|null
     * }
     */
    private function normalizeDatabasePost(BlogPost $post): array
    {
        $ogImage = $post->og_image;
        if (is_string($ogImage) && $ogImage !== '' && ! Str::startsWith($ogImage, ['http://', 'https://', '/'])) {
            $ogImage = asset('storage/'.$ogImage);
        }

        return [
            'title' => $post->title,
            'description' => $post->seoDescription(),
            'meta_title' => $post->meta_title,
            'date' => optional($post->published_at)->toDateString()
                ?? optional($post->updated_at)->toDateString()
                ?? optional($post->created_at)->toDateString()
                ?? '',
            'date_published' => optional($post->published_at)?->toAtomString()
                ?? optional($post->created_at)?->toAtomString(),
            'date_modified' => optional($post->updated_at)?->toAtomString(),
            'slug' => $post->slug,
            'locale' => $post->locale,
            'body' => $post->body_html,
            'html' => $post->body_html,
            'path' => null,
            'source' => 'database',
            'format' => 'html',
            'og_image' => $ogImage,
            'robots' => $post->robots,
            'author_name' => $post->author_name ?: config('blog_ai.author_name'),
            'focus_keyword' => $post->focus_keyword,
            'faqs' => is_array($post->faqs_json) ? $post->faqs_json : [],
        ];
    }

    /**
     * @return Collection<int, array{
     *     title: string,
     *     description: string,
     *     meta_title: string|null,
     *     date: string,
     *     slug: string,
     *     locale: string,
     *     body: string,
     *     path: string|null,
     *     source: string,
     *     format: string,
     *     og_image: string|null,
     *     robots: string|null
     * }>
     */
    private function scanMarkdownPosts(): Collection
    {
        $dir = resource_path('content/blog');

        if (! File::isDirectory($dir)) {
            return collect();
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => str_ends_with(strtolower($file->getFilename()), '.md'))
            ->map(fn ($file) => $this->parseFile($file->getPathname()))
            ->filter()
            ->values();
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     meta_title: string|null,
     *     date: string,
     *     slug: string,
     *     locale: string,
     *     body: string,
     *     path: string|null,
     *     source: string,
     *     format: string,
     *     og_image: string|null,
     *     robots: string|null
     * }|null
     */
    private function parseFile(string $path): ?array
    {
        $raw = File::get($path);

        // Use \n (not \R) and the /u flag. Without /u, PCRE's \R treats raw byte
        // 0x85 as NEL and splits inside Bengali UTF-8 sequences (e.g. অ = E0 A6 85),
        // which truncates titles and breaks Inertia's JSON page payload.
        if (! preg_match('/\A---\s*\n(.*?)\n---\s*\n?(.*)\z/su', $raw, $matches)) {
            return null;
        }

        $meta = $this->parseFrontMatter($matches[1]);
        $body = trim($matches[2]);
        $slug = (string) ($meta['slug'] ?? pathinfo($path, PATHINFO_FILENAME));

        if ($slug === '') {
            return null;
        }

        $locale = strtolower((string) ($meta['locale'] ?? 'bn'));
        if (! in_array($locale, ['bn', 'en'], true)) {
            $locale = 'bn';
        }

        return [
            'title' => (string) ($meta['title'] ?? $slug),
            'description' => (string) ($meta['description'] ?? ''),
            'meta_title' => null,
            'date' => (string) ($meta['date'] ?? ''),
            'slug' => $slug,
            'locale' => $locale,
            'body' => $body,
            'path' => $path,
            'source' => 'markdown',
            'format' => 'markdown',
            'og_image' => null,
            'robots' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseFrontMatter(string $yamlLike): array
    {
        $meta = [];

        foreach (preg_split('/\n/u', $yamlLike) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key !== '') {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }
}
