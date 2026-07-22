<?php

namespace App\Services\BlogAi;

use Illuminate\Support\Facades\File;

/**
 * Loads modular Blog AI prompt templates from resources/blog-ai.
 */
class BlogPromptLibrary
{
    public function get(string $name, array $vars = []): string
    {
        $path = resource_path('blog-ai/prompts/'.$name.'.md');
        if (! File::exists($path)) {
            return '';
        }

        $body = trim((string) File::get($path));
        foreach ($vars as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }

        return $body;
    }

    public function system(): string
    {
        $fromFile = $this->get('system');

        return $fromFile !== ''
            ? $fromFile
            : 'You are an expert Bangladesh SEO content strategist for WooEasyLife.';
    }

    /**
     * Structure + sourcing contract (source of truth for post flow).
     */
    public function playbook(): string
    {
        $path = resource_path('blog-ai/editorial-playbook.md');
        if (! File::exists($path)) {
            return '';
        }

        return trim((string) File::get($path));
    }

    /**
     * Fixed section skeleton for an article type.
     */
    public function skeleton(string $articleType): string
    {
        $type = strtolower(trim($articleType));
        $allowed = ['howto', 'comparison', 'glossary', 'case_study'];
        if (! in_array($type, $allowed, true)) {
            $type = 'howto';
        }

        $path = resource_path('blog-ai/skeletons/'.$type.'.md');
        if (! File::exists($path)) {
            return '';
        }

        return trim((string) File::get($path));
    }

    public function outline(): string
    {
        return $this->get('content-outline');
    }

    public function articleWriter(string $authorName, int $minWords): string
    {
        return $this->get('article-writer', [
            'author_name' => $authorName,
            'min_words' => (string) $minWords,
        ]);
    }

    /**
     * System prompt block that locks structure for outline/draft.
     */
    public function structureContract(string $articleType): string
    {
        $parts = array_filter([
            $this->playbook(),
            $this->skeleton($articleType),
        ]);

        return implode("\n\n---\n\n", $parts);
    }
}
