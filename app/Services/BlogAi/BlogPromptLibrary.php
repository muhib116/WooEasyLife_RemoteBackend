<?php

namespace App\Services\BlogAi;

use Illuminate\Support\Facades\File;

/**
 * Loads modular Blog AI prompt templates from resources/blog-ai/prompts.
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
}
