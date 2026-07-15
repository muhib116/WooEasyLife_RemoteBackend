<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Support\Str;

/**
 * Shared on-page SEO checks for AI drafts and CMS publish.
 */
class BlogSeoQuality
{
    /**
     * @param  list<string>  $secondaryKeywords
     * @param  list<array{q?: string, a?: string}>|null  $faqs
     * @return array{
     *     word_count: int,
     *     word_count_ok: bool,
     *     has_h2: bool,
     *     has_internal_link: bool,
     *     internal_link_count: int,
     *     internal_links_ok: bool,
     *     keyword_in_title: bool,
     *     keyword_in_meta: bool,
     *     keyword_in_first_paragraph: bool,
     *     meta_description_ok: bool,
     *     faq_count: int,
     *     faq_count_ok: bool,
     *     has_content_image: bool,
     *     content_image_alt_ok: bool,
     *     secondary_keyword_in_body: bool,
     *     slug_collision: bool,
     *     focus_keyword_collision: bool,
     *     collisions: list<array{id: int, title: string, slug: string, focus_keyword: string|null, status: string, type: string}>,
     *     publish_ready: bool,
     *     ai_ready: bool,
     *     failures: list<string>
     * }
     */
    public function analyze(
        string $title,
        string $focusKeyword,
        string $bodyHtml,
        string $metaDescription,
        array $faqs = [],
        array $secondaryKeywords = [],
        ?string $slug = null,
        ?int $ignorePostId = null,
        string $locale = 'bn',
    ): array {
        $minWords = (int) config('blog_ai.min_body_words', 800);
        $minLinks = (int) config('blog_ai.seo_quality.min_internal_links', 2);
        $minFaqs = (int) config('blog_ai.seo_quality.min_faqs', 3);

        $plain = $this->plainText($bodyHtml);
        $words = $plain === '' ? 0 : count(preg_split('/\s+/u', $plain) ?: []);
        $kw = mb_strtolower(trim($focusKeyword));
        $firstParagraph = $this->firstParagraphText($bodyHtml);

        $linkCount = preg_match_all('/<a\b[^>]*\bhref=["\'](\/[^"\']*)["\']/i', $bodyHtml) ?: 0;
        $faqCount = collect($faqs)
            ->filter(fn ($row) => is_array($row) && filled($row['q'] ?? null) && filled($row['a'] ?? null))
            ->count();

        $hasContentImage = (bool) preg_match('/<img[\s>]/i', $bodyHtml);
        $contentImageAltOk = ! $hasContentImage || (bool) preg_match('/<img\b[^>]*\balt=["\'][^"\']+["\']/i', $bodyHtml);

        $secondaryHit = false;
        foreach ($secondaryKeywords as $secondary) {
            $s = trim((string) $secondary);
            if ($s !== '' && $this->textContainsKeyword($plain, $s)) {
                $secondaryHit = true;
                break;
            }
        }
        if ($secondaryKeywords === []) {
            $secondaryHit = true; // nothing to cover
        }

        $collisions = $this->findCollisions($slug, $focusKeyword, $locale, $ignorePostId);
        $slugCollision = collect($collisions)->contains(fn (array $row) => $row['type'] === 'slug');
        $focusCollision = collect($collisions)->contains(fn (array $row) => $row['type'] === 'focus_keyword');

        $checks = [
            'word_count_ok' => $words >= $minWords,
            'has_h2' => (bool) preg_match('/<h2[\s>]/i', $bodyHtml),
            'has_internal_link' => $linkCount >= 1,
            'internal_links_ok' => $linkCount >= $minLinks,
            'keyword_in_title' => $kw !== '' && $this->textContainsKeyword($title, $focusKeyword),
            'keyword_in_meta' => $kw !== '' && $this->textContainsKeyword($metaDescription, $focusKeyword),
            'keyword_in_first_paragraph' => $kw !== '' && $this->textContainsKeyword($firstParagraph, $focusKeyword),
            'meta_description_ok' => mb_strlen($metaDescription) >= 50 && mb_strlen($metaDescription) <= 160,
            'faq_count_ok' => $faqCount >= $minFaqs,
            'has_content_image' => $hasContentImage,
            'content_image_alt_ok' => $contentImageAltOk,
            'secondary_keyword_in_body' => $secondaryHit,
            'slug_collision' => $slugCollision,
            'focus_keyword_collision' => $focusCollision,
        ];

        $aiRequired = [
            'word_count_ok',
            'has_h2',
            'internal_links_ok',
            'keyword_in_title',
            'keyword_in_meta',
            'keyword_in_first_paragraph',
            'meta_description_ok',
            'faq_count_ok',
            'secondary_keyword_in_body',
        ];

        $publishRequiredKeys = config('blog_ai.seo_quality.enforce_on_publish', [
            'has_internal_link' => true,
            'duplicate_focus_keyword' => true,
        ]);

        $failures = [];
        foreach ($aiRequired as $key) {
            if (! ($checks[$key] ?? false)) {
                $failures[] = $key;
            }
        }
        if ($slugCollision) {
            $failures[] = 'slug_collision';
        }
        if ($focusCollision) {
            $failures[] = 'focus_keyword_collision';
        }

        $aiReady = $failures === [];

        $publishReady = true;
        if (! empty($publishRequiredKeys['has_internal_link']) && ! $checks['has_internal_link']) {
            $publishReady = false;
        }
        if (! empty($publishRequiredKeys['duplicate_focus_keyword']) && $focusCollision) {
            $publishReady = false;
        }
        if (! empty($publishRequiredKeys['duplicate_slug']) && $slugCollision) {
            $publishReady = false;
        }

        return [
            'word_count' => $words,
            'word_count_ok' => $checks['word_count_ok'],
            'has_h2' => $checks['has_h2'],
            'has_internal_link' => $checks['has_internal_link'],
            'internal_link_count' => $linkCount,
            'internal_links_ok' => $checks['internal_links_ok'],
            'keyword_in_title' => $checks['keyword_in_title'],
            'keyword_in_meta' => $checks['keyword_in_meta'],
            'keyword_in_first_paragraph' => $checks['keyword_in_first_paragraph'],
            'meta_description_ok' => $checks['meta_description_ok'],
            'faq_count' => $faqCount,
            'faq_count_ok' => $checks['faq_count_ok'],
            'has_content_image' => $checks['has_content_image'],
            'content_image_alt_ok' => $checks['content_image_alt_ok'],
            'secondary_keyword_in_body' => $checks['secondary_keyword_in_body'],
            'slug_collision' => $slugCollision,
            'focus_keyword_collision' => $focusCollision,
            'collisions' => $collisions,
            'publish_ready' => $publishReady,
            'ai_ready' => $aiReady,
            'failures' => array_values(array_unique($failures)),
        ];
    }

    /**
     * Soft publish gates — keeps manual short posts working unless clearly broken.
     *
     * @return array<string, string> field => message
     */
    public function publishValidationErrors(
        string $bodyHtml,
        ?string $focusKeyword,
        ?string $slug,
        string $locale,
        ?int $ignorePostId = null,
    ): array {
        $gates = config('blog_ai.seo_quality.enforce_on_publish', []);
        $errors = [];

        $quality = $this->analyze(
            title: '',
            focusKeyword: (string) ($focusKeyword ?? ''),
            bodyHtml: $bodyHtml,
            metaDescription: '',
            faqs: [],
            secondaryKeywords: [],
            slug: $slug,
            ignorePostId: $ignorePostId,
            locale: $locale,
        );

        if (! empty($gates['has_internal_link']) && ! $quality['has_internal_link']) {
            $errors['body_html'] = 'Add at least one internal link (e.g. /bd-fraud-checker) before publishing.';
        }

        if (! empty($gates['duplicate_focus_keyword']) && $quality['focus_keyword_collision']) {
            $other = collect($quality['collisions'])->firstWhere('type', 'focus_keyword');
            $errors['focus_keyword'] = 'Focus keyword already used by published post'
                .($other ? ' “'.$other['title'].'” (/blog/'.$other['slug'].').' : '.');
        }

        if (! empty($gates['duplicate_slug']) && $quality['slug_collision']) {
            $errors['slug'] = 'Slug already exists. Choose a unique English SEO slug.';
        }

        return $errors;
    }

    /**
     * Non-blocking SEO tips for the CMS publish flow.
     *
     * @return list<string>
     */
    public function softWarningsForPublish(
        string $title,
        string $bodyHtml,
        ?string $focusKeyword,
        ?string $ogImage,
    ): array {
        $flags = config('blog_ai.seo_quality.soft_warn_on_publish', []);
        $warnings = [];
        $kw = trim((string) $focusKeyword);
        $hasContentImage = (bool) preg_match('/<img[\s>]/i', $bodyHtml);
        $hasOg = filled(trim((string) $ogImage));

        if (! empty($flags['keyword_in_title']) && $kw !== '' && ! $this->textContainsKeyword($title, $kw)) {
            $warnings[] = 'Focus keyword is missing from the title. Consider adding it before publishing.';
        }

        if (! empty($flags['missing_og_image']) && ! $hasOg) {
            $warnings[] = 'No OG / cover image set. Social shares will look weaker — add one or generate via AI.';
        }

        if (! empty($flags['missing_content_image']) && ! $hasContentImage) {
            $warnings[] = 'No image in the body. Add a content image with alt text for better engagement.';
        }

        return $warnings;
    }

    /**
     * @param  list<array{path?: string, anchor?: string}>  $linkPlan
     */
    public function ensureInternalLinks(string $bodyHtml, array $linkPlan, int $min = 2): string
    {
        $count = preg_match_all('/<a\b[^>]*\bhref=["\'](\/[^"\']*)["\']/i', $bodyHtml) ?: 0;
        if ($count >= $min) {
            return $bodyHtml;
        }

        $existing = [];
        if (preg_match_all('/href=["\'](\/[^"\']*)["\']/i', $bodyHtml, $m)) {
            $existing = $m[1];
        }

        $parts = [];
        foreach ($linkPlan as $link) {
            if ($count >= $min) {
                break;
            }
            $path = (string) ($link['path'] ?? '');
            if ($path === '' || ! str_starts_with($path, '/') || in_array($path, $existing, true)) {
                continue;
            }
            $anchor = trim((string) ($link['anchor'] ?? $path)) ?: $path;
            $parts[] = '<a href="'.e($path).'">'.e($anchor).'</a>';
            $existing[] = $path;
            $count++;
        }

        if ($parts === []) {
            return $bodyHtml;
        }

        return rtrim($bodyHtml)."\n<p>আরও দেখুন: ".implode(' · ', $parts).'</p>';
    }

    public function injectContentImage(string $bodyHtml, string $imageUrl, string $alt, ?string $caption = null): string
    {
        if ($imageUrl === '') {
            return $bodyHtml;
        }

        if (preg_match('/<img\b[^>]*\bsrc=["\']'.preg_quote($imageUrl, '/').'["\']/i', $bodyHtml)) {
            return $bodyHtml;
        }

        // Already has a real content image (not just empty).
        if (preg_match('/<img[\s>]/i', $bodyHtml)) {
            return $bodyHtml;
        }

        $altSafe = e(Str::limit(trim($alt) !== '' ? trim($alt) : 'WooEasyLife blog', 120, ''));
        $srcSafe = e($imageUrl);
        $captionHtml = $caption
            ? '<figcaption>'.e(Str::limit($caption, 160, '')).'</figcaption>'
            : '';

        $figure = '<figure><img src="'.$srcSafe.'" alt="'.$altSafe.'">'.$captionHtml.'</figure>';

        // Keep keyword-bearing first <p> as the first paragraph: insert figure after it.
        // If the body does not start with <p> (e.g. starts with <h2>), prepend figure
        // so the image is not buried far below headings.
        if (preg_match('/^\s*<p\b/i', $bodyHtml) && preg_match('/<\/p>/i', $bodyHtml, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);

            return substr($bodyHtml, 0, $pos)."\n".$figure.substr($bodyHtml, $pos);
        }

        return $figure."\n".$bodyHtml;
    }

    /**
     * Bangla / phrase: substring. Latin tokens: prefer word-ish boundaries.
     */
    public function textContainsKeyword(string $haystack, string $keyword): bool
    {
        $hay = mb_strtolower(trim($haystack));
        $kw = mb_strtolower(trim($keyword));
        if ($hay === '' || $kw === '') {
            return false;
        }

        if (str_contains($hay, $kw)) {
            // Reduce obvious Latin false positives: require non-letter neighbors when keyword is ASCII letters only.
            if (preg_match('/^[a-z0-9][a-z0-9\s-]{0,80}$/i', $kw)) {
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($kw, '/').'(?![\p{L}\p{N}])/iu';

                return (bool) preg_match($pattern, $hay);
            }

            return true;
        }

        return false;
    }

    public function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    }

    public function firstParagraphText(string $html): string
    {
        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1])));
        }

        $plain = $this->plainText($html);

        return Str::limit($plain, 400, '');
    }

    /**
     * @return list<array{id: int, title: string, slug: string, focus_keyword: string|null, status: string, type: string}>
     */
    public function findCollisions(
        ?string $slug,
        ?string $focusKeyword,
        string $locale = 'bn',
        ?int $ignorePostId = null,
    ): array {
        $out = [];
        $slug = trim((string) $slug);
        $focus = trim((string) $focusKeyword);

        if ($slug !== '') {
            $row = BlogPost::query()
                ->when($ignorePostId, fn ($q) => $q->where('id', '!=', $ignorePostId))
                ->where('slug', $slug)
                ->first(['id', 'title', 'slug', 'focus_keyword', 'status']);

            if ($row) {
                $out[] = [
                    'id' => $row->id,
                    'title' => $row->title,
                    'slug' => $row->slug,
                    'focus_keyword' => $row->focus_keyword,
                    'status' => $row->status,
                    'type' => 'slug',
                ];
            }
        }

        if ($focus !== '') {
            $row = BlogPost::query()
                ->when($ignorePostId, fn ($q) => $q->where('id', '!=', $ignorePostId))
                ->where('locale', $locale)
                ->where('status', 'published')
                ->whereRaw('LOWER(TRIM(focus_keyword)) = ?', [mb_strtolower($focus)])
                ->first(['id', 'title', 'slug', 'focus_keyword', 'status']);

            if ($row) {
                $out[] = [
                    'id' => $row->id,
                    'title' => $row->title,
                    'slug' => $row->slug,
                    'focus_keyword' => $row->focus_keyword,
                    'status' => $row->status,
                    'type' => 'focus_keyword',
                ];
            }
        }

        return $out;
    }
}
