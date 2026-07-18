<?php

namespace App\Services\Facebook;

use App\Models\BlogPost;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FacebookPagePublisher
{
    public function configured(): bool
    {
        return filled(config('services.facebook.page_id'))
            && filled(config('services.facebook.page_access_token'));
    }

    /**
     * Build a short Page caption (message). Link is appended so readers can click through.
     */
    public function defaultCaption(BlogPost $post): string
    {
        $title = trim((string) $post->title);
        $excerpt = trim((string) ($post->excerpt ?: $post->seoDescription()));

        $lines = array_values(array_filter([
            $title !== '' ? $title : null,
            $excerpt !== '' ? Str::limit($excerpt, 180, '…') : null,
            '👉 বিস্তারিত পড়ুন 👇',
            $this->shareUrl($post),
        ]));

        return implode("\n\n", $lines);
    }

    /**
     * Prefer FACEBOOK_SHARE_BASE_URL (public production URL) over APP_URL for link previews.
     */
    public function shareUrl(BlogPost $post): string
    {
        return rtrim($this->shareBaseUrl(), '/').$post->publicPath();
    }

    public function shareBaseUrl(): string
    {
        $configured = trim((string) config('services.facebook.share_base_url', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public function isPublicShareUrl(?string $url = null): bool
    {
        $url ??= $this->shareBaseUrl().'/';
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.test') || str_ends_with($host, '.localhost')) {
            return false;
        }

        return true;
    }

    /**
     * @return array{post_id: string, permalink: string|null, mode: string}
     */
    public function shareBlogPost(BlogPost $post, ?string $message = null): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Facebook Page sharing is not configured. Set FACEBOOK_PAGE_ID and FACEBOOK_PAGE_ACCESS_TOKEN.');
        }

        if (! $post->isPublished() || ! filled($post->slug)) {
            throw new RuntimeException('Only published posts with a slug can be shared to Facebook.');
        }

        $caption = trim((string) ($message ?? $this->defaultCaption($post)));
        if ($caption === '') {
            $caption = (string) $post->title;
        }

        $link = $this->shareUrl($post);
        if ($link !== '' && ! str_contains($caption, $link)) {
            $caption .= "\n\n".$link;
        }

        $image = $this->resolveShareImage($post);

        if ($image !== null) {
            return $this->publishPhoto($caption, $image);
        }

        return $this->publishFeed($caption, $link);
    }

    /**
     * Local filesystem path for the image to upload, or null.
     *
     * @return array{path: string, filename: string}|null
     */
    public function resolveShareImage(BlogPost $post): ?array
    {
        foreach ($this->candidateImageRefs($post) as $ref) {
            $resolved = $this->resolveLocalImage($ref);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateImageRefs(BlogPost $post): array
    {
        $refs = [];

        if (filled($post->og_image)) {
            $refs[] = (string) $post->og_image;
        }

        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $post->body_html, $matches)) {
            foreach ($matches[1] as $src) {
                $refs[] = (string) $src;
            }
        }

        $defaultOg = (string) config('seo.default_og_image', '');
        if ($defaultOg !== '') {
            $refs[] = $defaultOg;
        }

        return array_values(array_unique(array_filter($refs)));
    }

    /**
     * @return array{path: string, filename: string}|null
     */
    private function resolveLocalImage(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        // Absolute public URL on this app → strip to path
        $appUrl = rtrim((string) config('app.url'), '/');
        if (Str::startsWith($ref, ['http://', 'https://'])) {
            if ($appUrl !== '' && Str::startsWith($ref, $appUrl.'/')) {
                $ref = '/'.ltrim(Str::after($ref, $appUrl.'/'), '/');
            } else {
                // Remote URL — skip (Facebook may fetch it, but localhost/admin may not).
                return null;
            }
        }

        if (Str::startsWith($ref, '/storage/')) {
            $storagePath = ltrim(Str::after($ref, '/storage/'), '/');
            $full = Storage::disk('public')->path($storagePath);
            if (is_file($full)) {
                return ['path' => $full, 'filename' => basename($full)];
            }
        }

        if (Str::startsWith($ref, 'storage/')) {
            $storagePath = ltrim(Str::after($ref, 'storage/'), '/');
            $full = Storage::disk('public')->path($storagePath);
            if (is_file($full)) {
                return ['path' => $full, 'filename' => basename($full)];
            }
        }

        // Relative public disk path (common for og_image)
        if (! Str::startsWith($ref, ['/', 'http'])) {
            $full = Storage::disk('public')->path($ref);
            if (is_file($full)) {
                return ['path' => $full, 'filename' => basename($full)];
            }
        }

        // public/ path (e.g. /images/seo/og-default.jpg)
        $publicRelative = ltrim($ref, '/');
        $publicFull = public_path($publicRelative);
        if (is_file($publicFull)) {
            return ['path' => $publicFull, 'filename' => basename($publicFull)];
        }

        return null;
    }

    /**
     * @param  array{path: string, filename: string}  $image
     * @return array{post_id: string, permalink: string|null, mode: string}
     */
    private function publishPhoto(string $caption, array $image): array
    {
        $pageId = (string) config('services.facebook.page_id');
        $token = (string) config('services.facebook.page_access_token');
        $version = (string) config('services.facebook.graph_version', 'v21.0');

        $contents = @file_get_contents($image['path']);
        if ($contents === false) {
            throw new RuntimeException('Facebook share failed: could not read image file.');
        }

        try {
            $response = Http::timeout(60)
                ->attach('source', $contents, $image['filename'])
                ->post("https://graph.facebook.com/{$version}/{$pageId}/photos", [
                    'caption' => $caption,
                    'access_token' => $token,
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('Facebook share failed: '.$this->apiErrorMessage($e), previous: $e);
        }

        $postId = (string) ($response->json('post_id') ?: $response->json('id') ?: '');

        if ($postId === '') {
            throw new RuntimeException('Facebook share failed: no post id returned.');
        }

        return [
            'post_id' => $postId,
            'permalink' => $this->permalinkFor($postId),
            'mode' => 'photo',
        ];
    }

    /**
     * @return array{post_id: string, permalink: string|null, mode: string}
     */
    private function publishFeed(string $caption, string $link): array
    {
        $pageId = (string) config('services.facebook.page_id');
        $token = (string) config('services.facebook.page_access_token');
        $version = (string) config('services.facebook.graph_version', 'v21.0');

        $payload = [
            'message' => $caption,
            'access_token' => $token,
        ];

        // Facebook rejects localhost / private URLs in `link`.
        if ($this->isPublicShareUrl($link)) {
            $payload['link'] = $link;
        }

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post("https://graph.facebook.com/{$version}/{$pageId}/feed", $payload)
                ->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('Facebook share failed: '.$this->apiErrorMessage($e), previous: $e);
        }

        $postId = (string) ($response->json('id') ?? $response->json('post_id') ?? '');

        if ($postId === '') {
            throw new RuntimeException('Facebook share failed: no post id returned.');
        }

        return [
            'post_id' => $postId,
            'permalink' => $this->permalinkFor($postId),
            'mode' => 'feed',
        ];
    }

    private function apiErrorMessage(RequestException $e): string
    {
        $body = $e->response?->json();

        return (string) (
            data_get($body, 'error.message')
            ?? data_get($body, 'error.error_user_msg')
            ?? $e->getMessage()
        );
    }

    public function permalinkFor(string $graphPostId): ?string
    {
        // Graph returns "{page_id}_{post_id}" for feed/photo posts.
        if (str_contains($graphPostId, '_')) {
            [, $storyId] = explode('_', $graphPostId, 2);
            $pageId = (string) config('services.facebook.page_id');

            if ($pageId !== '' && $storyId !== '') {
                return "https://www.facebook.com/{$pageId}/posts/{$storyId}";
            }
        }

        return "https://www.facebook.com/{$graphPostId}";
    }
}
