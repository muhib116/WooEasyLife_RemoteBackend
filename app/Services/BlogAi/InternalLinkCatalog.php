<?php

namespace App\Services\BlogAi;

use App\Models\BlogPost;

/**
 * Live internal link targets for BD SEO drafts.
 */
class InternalLinkCatalog
{
    /**
     * @return list<array{path: string, title: string, type: string, focus_keyword?: string|null, anchor_hints?: list<string>}>
     */
    public function all(): array
    {
        $static = collect(config('blog_ai.static_internal_links', []))
            ->map(fn (array $row) => [
                'path' => $row['path'],
                'title' => $row['title'],
                'type' => 'static',
                'anchor_hints' => $row['anchor_hints'] ?? [],
            ])
            ->all();

        $inventory = [];
        try {
            $inventory = app(\App\Services\Seo\SeoKeywordInventory::class)->liveLinkTargets();
        } catch (\Throwable) {
            $inventory = [];
        }

        // Prefer static titles when path already listed; still add inventory-only paths.
        $staticPaths = collect($static)->pluck('path')->all();
        $inventoryExtra = collect($inventory)
            ->reject(fn (array $row) => in_array($row['path'], $staticPaths, true))
            ->values()
            ->all();

        $posts = BlogPost::query()
            ->where('status', 'published')
            ->whereNotNull('slug')
            ->orderByDesc('published_at')
            ->limit(40)
            ->get(['title', 'slug', 'focus_keyword', 'locale'])
            ->map(fn (BlogPost $post) => [
                'path' => '/blog/'.$post->slug,
                'title' => $post->title,
                'type' => 'blog',
                'focus_keyword' => $post->focus_keyword,
                'locale' => $post->locale,
                'anchor_hints' => array_values(array_filter([
                    $post->focus_keyword,
                    $post->title,
                ])),
            ])
            ->all();

        return array_values([...$static, ...$inventoryExtra, ...$posts]);
    }

    public function toPromptBlock(): string
    {
        return json_encode($this->all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]';
    }
}
