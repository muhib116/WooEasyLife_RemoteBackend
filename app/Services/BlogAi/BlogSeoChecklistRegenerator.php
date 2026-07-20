<?php

namespace App\Services\BlogAi;

use App\Services\BlogSeoQuality;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Surgically regenerate blog fields to satisfy failing SEO checklist gates.
 */
class BlogSeoChecklistRegenerator
{
    public function __construct(
        private OpenAiBlogClient $openAi,
        private BlogSeoQuality $seoQuality,
        private BlogReadinessScorer $readinessScorer,
        private InternalLinkCatalog $linkCatalog,
        private BlogLearningService $learning,
    ) {}

    /**
     * @param  array{
     *     title?: string,
     *     slug?: string|null,
     *     focus_keyword?: string|null,
     *     secondary_keywords?: list<string>|null,
     *     meta_title?: string|null,
     *     meta_description?: string|null,
     *     excerpt?: string|null,
     *     body_html?: string,
     *     faqs_json?: list<array{q?: string, a?: string}>|null,
     *     og_image?: string|null,
     *     locale?: string,
     *     cluster?: string|null,
     *     ignore_post_id?: int|null
     * }  $input
     * @return array{
     *     title: string,
     *     meta_title: string,
     *     meta_description: string,
     *     excerpt: string,
     *     body_html: string,
     *     faqs_json: list<array{q: string, a: string}>,
     *     focus_keyword: string,
     *     ai_quality_score: int,
     *     ai_quality_breakdown: array<string, mixed>,
     *     quality: array<string, mixed>,
     *     fixed_checks: list<string>,
     *     remaining_failures: list<string>,
     *     notes: list<string>,
     *     usage: array<string, int>
     * }
     */
    public function regenerate(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $focus = trim((string) ($input['focus_keyword'] ?? ''));
        $body = (string) ($input['body_html'] ?? '');
        $meta = trim((string) ($input['meta_description'] ?? $input['excerpt'] ?? ''));
        $metaTitle = trim((string) ($input['meta_title'] ?? $title));
        $excerpt = trim((string) ($input['excerpt'] ?? ''));
        $faqs = is_array($input['faqs_json'] ?? null) ? $input['faqs_json'] : [];
        $secondary = is_array($input['secondary_keywords'] ?? null)
            ? array_values(array_filter(array_map('strval', $input['secondary_keywords'])))
            : [];
        $slug = trim((string) ($input['slug'] ?? ''));
        $locale = trim((string) ($input['locale'] ?? 'bn')) ?: 'bn';
        $cluster = trim((string) ($input['cluster'] ?? ''));
        $ignoreId = isset($input['ignore_post_id']) ? (int) $input['ignore_post_id'] : null;
        $ogImage = trim((string) ($input['og_image'] ?? ''));

        if ($title === '' || $focus === '' || trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages([
                'ai' => 'Title, focus keyword, and body are required before regenerating SEO.',
            ]);
        }

        $before = $this->seoQuality->analyze(
            $title,
            $focus,
            $body,
            $meta,
            $faqs,
            $secondary,
            $slug !== '' ? $slug : null,
            $ignoreId,
            $locale,
        );

        $targets = $this->failingTargets($before, $ogImage !== '');
        $notes = [];

        if ($targets === []) {
            $score = $this->readinessScorer->compute([
                'seo' => $this->readinessScorer->scoreFromSeoQuality($before),
                'content' => ! empty($before['ai_ready']) ? 92 : 70,
            ]);

            return [
                'title' => $title,
                'meta_title' => $metaTitle !== '' ? $metaTitle : $title,
                'meta_description' => $meta,
                'excerpt' => $excerpt !== '' ? $excerpt : $meta,
                'body_html' => $body,
                'faqs_json' => $this->normalizeFaqs($faqs),
                'focus_keyword' => $focus,
                'ai_quality_score' => $score['score'],
                'ai_quality_breakdown' => $score['breakdown'],
                'quality' => $before,
                'fixed_checks' => [],
                'remaining_failures' => [],
                'notes' => ['Checklist already complete — nothing to regenerate.'],
                'usage' => [],
            ];
        }

        $usage = [];
        $linkPlan = collect($this->linkCatalog->all())
            ->take(8)
            ->map(fn (array $row) => [
                'path' => $row['path'],
                'anchor' => ($row['anchor_hints'][0] ?? null) ?: ($row['title'] ?? $row['path']),
            ])
            ->values()
            ->all();

        // Deterministic fixes first (fast, free).
        $body = $this->seoQuality->ensureInternalLinks(
            $body,
            $linkPlan,
            (int) config('blog_ai.seo_quality.min_internal_links', 2),
        );

        if ($ogImage !== '' && ! preg_match('/<img[\s>]/i', $body)) {
            $body = $this->seoQuality->injectContentImage($body, $ogImage, $focus ?: $title);
            $notes[] = 'Injected cover image into body with keyword alt text.';
        }

        $mid = $this->seoQuality->analyze(
            $title,
            $focus,
            $body,
            $meta,
            $faqs,
            $secondary,
            $slug !== '' ? $slug : null,
            $ignoreId,
            $locale,
        );
        $targets = $this->failingTargets($mid, $ogImage !== '');
        // AI cannot invent media files — never spend tokens on image-only gaps.
        $mediaOnlyKeys = ['og_or_content_image', 'has_content_image', 'content_image_alt_ok'];
        $aiTargets = array_values(array_filter(
            $targets,
            fn (string $key) => ! in_array($key, $mediaOnlyKeys, true),
        ));

        if ($aiTargets !== []) {
            $originalBody = $body;
            $ai = $this->callModel(
                title: $title,
                metaTitle: $metaTitle,
                metaDescription: $meta,
                excerpt: $excerpt,
                focus: $focus,
                secondary: $secondary,
                bodyHtml: $body,
                faqs: $faqs,
                targets: $aiTargets,
                cluster: $cluster,
                linkPlan: $linkPlan,
                extraContext: [
                    'refresh_instructions' => $input['refresh_instructions'] ?? null,
                    'competitor_intelligence' => $input['competitor_intelligence'] ?? null,
                    'competitor_diff_checklist' => $input['competitor_diff_checklist'] ?? null,
                ],
            );
            $usage = $ai['usage'];

            $title = trim((string) ($ai['title'] ?? $title)) ?: $title;
            $metaTitle = trim((string) ($ai['meta_title'] ?? $metaTitle)) ?: $title;
            $meta = trim((string) ($ai['meta_description'] ?? $meta));
            $excerpt = trim((string) ($ai['excerpt'] ?? $excerpt)) ?: $meta;
            $faqs = is_array($ai['faqs'] ?? null) ? $ai['faqs'] : $faqs;

            $aiBody = trim((string) ($ai['body_html'] ?? ''));
            $bodyTruncatedForPrompt = ! empty($ai['body_was_truncated']);
            $keepOriginalBody = $bodyTruncatedForPrompt
                || $aiBody === ''
                || (
                    mb_strlen($originalBody) > 2000
                    && mb_strlen($aiBody) < (int) (mb_strlen($originalBody) * 0.7)
                );

            if ($keepOriginalBody) {
                $body = $originalBody;
                $notes[] = $bodyTruncatedForPrompt
                    ? 'Long body preserved (prompt truncated) — applied meta/FAQs/SEO blocks only.'
                    : 'AI body rewrite looked incomplete — kept original body and applied SEO blocks only.';
            } else {
                $body = $aiBody;
            }

            $body = $this->seoQuality->ensureSeoContentBlocks(
                $body,
                is_string($ai['quick_answer'] ?? null) ? $ai['quick_answer'] : null,
                is_string($ai['ai_search_summary'] ?? null) ? $ai['ai_search_summary'] : null,
            );
            $body = $this->seoQuality->ensureInternalLinks(
                $body,
                $linkPlan,
                (int) config('blog_ai.seo_quality.min_internal_links', 2),
            );
            $body = BlogHtmlSanitizer::sanitize($body);
            $notes[] = 'AI applied targeted SEO edits for failing checklist items.';
        } elseif ($targets !== []) {
            $notes[] = 'Fixed with deterministic SEO helpers (links/image inject). Remaining items need manual upload.';
        } else {
            $notes[] = 'Fixed with deterministic SEO helpers (links/blocks).';
        }

        // Finish with targeted deterministic fixes only when those gates still fail.
        $probe = $this->seoQuality->analyze(
            $title,
            $focus,
            $body,
            $meta,
            $faqs,
            $secondary,
            $slug !== '' ? $slug : null,
            $ignoreId,
            $locale,
        );
        $beforeDepth = $body;
        if (empty($probe['keyword_in_first_paragraph'])) {
            $body = $this->seoQuality->ensureKeywordInFirstParagraph($body, $focus);
        }
        if (empty($probe['word_count_ok'])) {
            $body = $this->seoQuality->ensureMinBodyWords($body, $focus);
        }
        if ($body !== $beforeDepth) {
            $notes[] = 'Applied deterministic first-paragraph keyword and/or minimum word-count expansion.';
        }
        $body = BlogHtmlSanitizer::sanitize($body);

        $faqs = $this->normalizeFaqs($faqs);
        $meta = $this->clampMetaDescription($meta);
        $excerpt = $this->clampExcerpt($excerpt !== '' ? $excerpt : $meta);
        $metaTitle = Str::limit($metaTitle !== '' ? $metaTitle : $title, 70, '');

        $after = $this->seoQuality->analyze(
            $title,
            $focus,
            $body,
            $meta,
            $faqs,
            $secondary,
            $slug !== '' ? $slug : null,
            $ignoreId,
            $locale,
        );

        $fixed = [];
        foreach ($this->trackableKeys() as $key) {
            $wasBad = empty($before[$key]);
            $nowOk = ! empty($after[$key]);
            if ($wasBad && $nowOk) {
                $fixed[] = $key;
            }
        }
        if (($before['faq_count'] ?? 0) < 5 && ($after['faq_count'] ?? 0) >= 5) {
            $fixed[] = 'faq_count_ok';
        }

        $score = $this->readinessScorer->compute([
            'seo' => $this->readinessScorer->scoreFromSeoQuality($after),
            'content' => ! empty($after['ai_ready']) ? 94 : max(65, $this->readinessScorer->scoreFromSeoQuality($after) - 5),
            'image' => (! empty($after['has_content_image']) || $ogImage !== '') ? 80 : 40,
        ]);

        if ($ogImage === '' && empty($after['has_content_image'])) {
            $notes[] = 'OG/cover image still missing — upload one in the form (AI regenerate cannot invent media files).';
        }

        $remaining = array_values($after['failures'] ?? []);
        if ($remaining !== []) {
            $notes[] = 'Still open after regenerate: '.implode(', ', array_slice($remaining, 0, 8))
                .(count($remaining) > 8 ? '…' : '').'.';
        }

        return [
            'title' => $title,
            'meta_title' => $metaTitle !== '' ? $metaTitle : $title,
            'meta_description' => $meta,
            'excerpt' => $excerpt !== '' ? $excerpt : $meta,
            'body_html' => $body,
            'faqs_json' => $faqs,
            'focus_keyword' => $focus,
            'ai_quality_score' => $score['score'],
            'ai_quality_breakdown' => $score['breakdown'],
            'quality' => $after,
            'fixed_checks' => array_values(array_unique($fixed)),
            'remaining_failures' => $remaining,
            'notes' => $notes,
            'usage' => $usage,
        ];
    }

    /**
     * @param  array<string, mixed>  $quality
     * @return list<string>
     */
    private function failingTargets(array $quality, bool $hasOgImage): array
    {
        $targets = [];
        foreach ($this->trackableKeys() as $key) {
            if (empty($quality[$key])) {
                $targets[] = $key;
            }
        }

        if (! $hasOgImage && empty($quality['has_content_image'])) {
            $targets[] = 'og_or_content_image';
        }

        return array_values(array_unique($targets));
    }

    /**
     * @return list<string>
     */
    private function trackableKeys(): array
    {
        return [
            'keyword_in_title',
            'keyword_in_first_paragraph',
            'keyword_in_h2',
            'keyword_in_meta',
            'meta_description_ok',
            'has_h2',
            'has_h3',
            'has_lists',
            'has_quick_answer',
            'has_ai_search_summary',
            'has_internal_link',
            'internal_links_ok',
            'faq_count_ok',
            'has_content_image',
            'content_image_alt_ok',
            'word_count_ok',
            'secondary_keyword_in_body',
        ];
    }

    /**
     * @param  list<string>  $targets
     * @param  list<string>  $secondary
     * @param  list<array{q?: string, a?: string}>  $faqs
     * @param  list<array{path?: string, anchor?: string}>  $linkPlan
     * @return array<string, mixed>
     */
    private function callModel(
        string $title,
        string $metaTitle,
        string $metaDescription,
        string $excerpt,
        string $focus,
        array $secondary,
        string $bodyHtml,
        array $faqs,
        array $targets,
        string $cluster,
        array $linkPlan,
        array $extraContext = [],
    ): array {
        $minFaqs = (int) config('blog_ai.seo_quality.min_faqs', 5);
        $minWords = (int) config('blog_ai.min_body_words', 800);
        $promptLimit = 14000;
        $bodyWasTruncated = mb_strlen($bodyHtml) > $promptLimit;
        $bodyForPrompt = Str::limit($bodyHtml, $promptLimit, "\n<!-- truncated -->");

        $system = <<<'TXT'
You are a Bangladesh SEO editor for WooEasyLife blog posts.
Improve the draft ONLY to satisfy the listed failing SEO checklist items.
Return JSON only:
{
  "title": "...",
  "meta_title": "...",
  "meta_description": "...",
  "excerpt": "...",
  "body_html": "...",
  "faqs": [{"q":"...","a":"..."}],
  "quick_answer": "40-80 word featured snippet in Bangla",
  "ai_search_summary": "2-4 sentence AI overview in Bangla",
  "notes": ["what you changed"]
}
Rules:
- Keep Bangla seller tone; preserve product truth (fraud checker, courier, fake order, WooCommerce BD).
- Prefer surgical edits over full rewrites. Do not invent US-centric claims.
- Include focus_keyword naturally in title, first content <p> AFTER Quick Answer and AI Summary sections (not inside those sections), one <h2>, and meta_description.
- If keyword_in_first_paragraph fails: rewrite/insert the first content <p> so it starts with or clearly includes focus_keyword.
- If word_count_ok fails: expand body_html with useful Bangla seller paragraphs until min_body_words is reached (do not pad with gibberish). Keep all existing good sections.
- Ensure <h2>, <h3>, and at least one <ul>/<ol> when those checks fail.
- FAQs must be ≥ min_faqs with useful BD seller Q&A.
- Internal links must use paths from allowed_internal_links only (href="/...").
- Keep valid HTML fragments (p, h2, h3, ul, ol, li, a, section, strong, em, figure, img).
- Do not remove existing good sections unless required for SEO.
- If body_html_truncated is true: leave body_html empty (or omit it). Only return title/meta/excerpt/faqs/quick_answer/ai_search_summary. The server will keep the full original body and apply deterministic keyword/word-count fixes.
- When refresh_instructions or competitor_diff_checklist is present, also improve CTR/title/meta and cover competitor gaps without changing the URL slug intent.
TXT;

        $user = json_encode([
            'failing_checks' => $targets,
            'focus_keyword' => $focus,
            'secondary_keywords' => $secondary,
            'cluster' => $cluster,
            'min_faqs' => $minFaqs,
            'min_body_words' => $minWords,
            'body_html_truncated' => $bodyWasTruncated,
            'allowed_internal_links' => $linkPlan,
            'gsc_keyword_seeds' => $this->learning->gscKeywordSeeds(6),
            'current' => [
                'title' => $title,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'excerpt' => $excerpt,
                'faqs' => $faqs,
                'body_html' => $bodyForPrompt,
            ],
            'refresh_instructions' => $extraContext['refresh_instructions'] ?? null,
            'competitor_intelligence' => $extraContext['competitor_intelligence'] ?? null,
            'competitor_diff_checklist' => $extraContext['competitor_diff_checklist'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.35);

        $data = $this->openAi->decodeJsonObject($result['content']);
        $data['usage'] = $result['usage'];
        $data['body_was_truncated'] = $bodyWasTruncated;

        return $data;
    }

    /**
     * @param  list<mixed>  $faqs
     * @return list<array{q: string, a: string}>
     */
    private function normalizeFaqs(array $faqs): array
    {
        return collect($faqs)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'q' => Str::limit(trim((string) ($row['q'] ?? '')), 200, ''),
                'a' => Str::limit(trim((string) ($row['a'] ?? '')), 1000, ''),
            ])
            ->filter(fn (array $row) => $row['q'] !== '' && $row['a'] !== '')
            ->take(12)
            ->values()
            ->all();
    }

    private function clampMetaDescription(string $meta): string
    {
        $meta = trim($meta);
        if ($meta === '') {
            return $meta;
        }

        return Str::limit($meta, 160, '');
    }

    private function clampExcerpt(string $excerpt): string
    {
        $excerpt = trim($excerpt);
        if ($excerpt === '') {
            return $excerpt;
        }

        return Str::limit($excerpt, 500, '');
    }
}
