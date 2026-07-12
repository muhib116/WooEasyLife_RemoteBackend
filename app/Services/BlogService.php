<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BlogService
{
    /**
     * @return list<array{title: string, description: string, date: string, slug: string, locale: string, body: string, path: string}>
     */
    public function all(?string $locale = null): array
    {
        $posts = $this->scanPosts();

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
     * @return array{title: string, description: string, date: string, slug: string, locale: string, body: string, path: string, html: string}|null
     */
    public function find(string $slug): ?array
    {
        $post = $this->scanPosts()->first(
            fn (array $post) => ($post['slug'] ?? '') === $slug
        );

        if ($post === null) {
            return null;
        }

        $post['html'] = $this->toHtml($post['body'] ?? '');

        return $post;
    }

    public function toHtml(string $body): string
    {
        return Str::markdown($body);
    }

    /**
     * @return Collection<int, array{title: string, description: string, date: string, slug: string, locale: string, body: string, path: string}>
     */
    private function scanPosts(): Collection
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
     * @return array{title: string, description: string, date: string, slug: string, locale: string, body: string, path: string}|null
     */
    private function parseFile(string $path): ?array
    {
        $raw = File::get($path);

        if (! preg_match('/\A---\s*\R(.*?)\R---\s*\R?(.*)\z/s', $raw, $matches)) {
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
            'date' => (string) ($meta['date'] ?? ''),
            'slug' => $slug,
            'locale' => $locale,
            'body' => $body,
            'path' => $path,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseFrontMatter(string $yamlLike): array
    {
        $meta = [];

        foreach (preg_split('/\R/', $yamlLike) ?: [] as $line) {
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
