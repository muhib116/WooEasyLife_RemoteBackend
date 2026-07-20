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
     *     has_h3: bool,
     *     has_lists: bool,
     *     has_internal_link: bool,
     *     internal_link_count: int,
     *     internal_links_ok: bool,
     *     keyword_in_title: bool,
     *     keyword_in_meta: bool,
     *     keyword_in_first_paragraph: bool,
     *     keyword_in_h2: bool,
     *     meta_description_ok: bool,
     *     faq_count: int,
     *     faq_count_ok: bool,
     *     has_quick_answer: bool,
     *     has_ai_search_summary: bool,
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
        $minFaqs = (int) config('blog_ai.seo_quality.min_faqs', 5);

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
            'has_h3' => (bool) preg_match('/<h3[\s>]/i', $bodyHtml),
            'has_lists' => (bool) preg_match('/<(ul|ol)[\s>]/i', $bodyHtml),
            'has_internal_link' => $linkCount >= 1,
            'internal_links_ok' => $linkCount >= $minLinks,
            'keyword_in_title' => $kw !== '' && $this->textContainsKeyword($title, $focusKeyword),
            'keyword_in_meta' => $kw !== '' && $this->textContainsKeyword($metaDescription, $focusKeyword),
            'keyword_in_first_paragraph' => $kw !== '' && $this->textContainsKeyword($firstParagraph, $focusKeyword),
            'keyword_in_h2' => $kw !== '' && $this->keywordInHeading($bodyHtml, 'h2', $focusKeyword),
            'meta_description_ok' => mb_strlen($metaDescription) >= 50 && mb_strlen($metaDescription) <= 160,
            'faq_count_ok' => $faqCount >= $minFaqs,
            'has_quick_answer' => $this->hasQuickAnswer($bodyHtml),
            'has_ai_search_summary' => $this->hasAiSearchSummary($bodyHtml),
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

        if (config('blog_ai.seo_quality.require_keyword_in_h2', true)) {
            $aiRequired[] = 'keyword_in_h2';
        }
        if (config('blog_ai.seo_quality.require_quick_answer', true)) {
            $aiRequired[] = 'has_quick_answer';
        }
        if (config('blog_ai.seo_quality.require_ai_search_summary', true)) {
            $aiRequired[] = 'has_ai_search_summary';
        }
        if (config('blog_ai.seo_quality.require_h3', true)) {
            $aiRequired[] = 'has_h3';
        }
        if (config('blog_ai.seo_quality.require_lists', true)) {
            $aiRequired[] = 'has_lists';
        }

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

        $publishRequiredKeys = config('blog_ai.seo_quality.enforce_on_publish', []);
        $publishReady = $aiReady;
        if (! empty($publishRequiredKeys['ai_ready']) && ! $aiReady) {
            $publishReady = false;
        }
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
            'has_h3' => $checks['has_h3'],
            'has_lists' => $checks['has_lists'],
            'has_internal_link' => $checks['has_internal_link'],
            'internal_link_count' => $linkCount,
            'internal_links_ok' => $checks['internal_links_ok'],
            'keyword_in_title' => $checks['keyword_in_title'],
            'keyword_in_meta' => $checks['keyword_in_meta'],
            'keyword_in_first_paragraph' => $checks['keyword_in_first_paragraph'],
            'keyword_in_h2' => $checks['keyword_in_h2'],
            'meta_description_ok' => $checks['meta_description_ok'],
            'faq_count' => $faqCount,
            'faq_count_ok' => $checks['faq_count_ok'],
            'has_quick_answer' => $checks['has_quick_answer'],
            'has_ai_search_summary' => $checks['has_ai_search_summary'],
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
     * Soft + hard publish gates for CMS.
     * By default only duplicate focus keyword hard-blocks; checklist gaps are soft-warned in the UI.
     *
     * @param  list<array{q?: string, a?: string}>  $faqs
     * @return array<string, string> field => message
     */
    public function publishValidationErrors(
        string $title,
        string $bodyHtml,
        ?string $focusKeyword,
        ?string $metaDescription,
        ?string $slug,
        string $locale,
        ?int $ignorePostId = null,
        array $faqs = [],
        ?string $ogImage = null,
    ): array {
        $gates = config('blog_ai.seo_quality.enforce_on_publish', []);
        $errors = [];
        $kw = trim((string) ($focusKeyword ?? ''));
        $meta = trim((string) ($metaDescription ?? ''));

        if (! empty($gates['focus_keyword_required']) && $kw === '') {
            $errors['focus_keyword'] = 'Set a focus keyword before publishing.';
        }

        $quality = $this->analyze(
            title: $title,
            focusKeyword: $kw,
            bodyHtml: $bodyHtml,
            metaDescription: $meta,
            faqs: $faqs,
            secondaryKeywords: [],
            slug: $slug,
            ignorePostId: $ignorePostId,
            locale: $locale,
        );

        if (! empty($gates['ai_ready']) && empty($quality['ai_ready'])) {
            foreach ($quality['failures'] as $failure) {
                [$field, $message] = $this->publishFailureMessage($failure);
                $errors[$field] = $errors[$field] ?? $message;
            }
        }

        // Legacy single-gate keys (still honored if ai_ready is off).
        if (empty($gates['ai_ready'])) {
            if (! empty($gates['has_internal_link']) && ! $quality['has_internal_link']) {
                $errors['body_html'] = 'Add at least one internal link (e.g. /bd-fraud-checker) before publishing.';
            }
        }

        if (! empty($gates['has_og_or_content_image'])) {
            $hasOg = filled(trim((string) $ogImage));
            if (! $hasOg && empty($quality['has_content_image'])) {
                $errors['og_image'] = 'Add an OG/cover image or a content image in the body before publishing.';
            } elseif (! empty($quality['has_content_image']) && empty($quality['content_image_alt_ok'])) {
                $errors['body_html'] = ($errors['body_html'] ?? null)
                    ?: 'Content images need non-empty alt text before publishing.';
            }
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
     * @return array{0: string, 1: string}
     */
    private function publishFailureMessage(string $failure): array
    {
        $minWords = (int) config('blog_ai.min_body_words', 800);
        $minFaqs = (int) config('blog_ai.seo_quality.min_faqs', 5);
        $minLinks = (int) config('blog_ai.seo_quality.min_internal_links', 2);

        return match ($failure) {
            'word_count_ok' => ['body_html', "Body needs at least {$minWords} words before publishing."],
            'has_h2' => ['body_html', 'Add at least one H2 heading before publishing.'],
            'has_h3' => ['body_html', 'Add at least one H3 heading before publishing.'],
            'has_lists' => ['body_html', 'Add a bullet or numbered list before publishing.'],
            'has_internal_link', 'internal_links_ok' => ['body_html', "Add at least {$minLinks} internal links (href=\"/...\") before publishing."],
            'keyword_in_title' => ['title', 'Include the focus keyword in the title before publishing.'],
            'keyword_in_meta' => ['meta_description', 'Include the focus keyword in the meta description before publishing.'],
            'keyword_in_first_paragraph' => ['body_html', 'Include the focus keyword in the first body paragraph (after Quick Answer) before publishing.'],
            'keyword_in_h2' => ['body_html', 'Include the focus keyword in at least one H2 before publishing.'],
            'meta_description_ok' => ['meta_description', 'Meta description must be 50–160 characters before publishing.'],
            'faq_count_ok' => ['faqs_json', "Add at least {$minFaqs} FAQs (question + answer) before publishing."],
            'has_quick_answer' => ['body_html', 'Add a Quick Answer / দ্রুত উত্তর section before publishing.'],
            'has_ai_search_summary' => ['body_html', 'Add an AI Search Summary / এআই সারাংশ section before publishing.'],
            'secondary_keyword_in_body' => ['body_html', 'Include at least one secondary keyword in the body before publishing.'],
            'slug_collision' => ['slug', 'Slug collides with another post. Choose a unique English SEO slug.'],
            'focus_keyword_collision' => ['focus_keyword', 'Focus keyword is already used by another published post.'],
            default => ['body_html', 'SEO checklist incomplete: '.$failure.'. Use Regenerate or fix manually before publishing.'],
        };
    }

    /**
     * Non-blocking SEO tips for the CMS publish flow.
     *
     * @param  list<array{q?: string, a?: string}>  $faqs
     * @return list<string>
     */
    public function softWarningsForPublish(
        string $title,
        string $bodyHtml,
        ?string $focusKeyword,
        ?string $ogImage,
        string $metaDescription = '',
        array $faqs = [],
    ): array {
        $flags = config('blog_ai.seo_quality.soft_warn_on_publish', []);
        $warnings = [];
        $kw = trim((string) $focusKeyword);
        $hasContentImage = (bool) preg_match('/<img[\s>]/i', $bodyHtml);
        $hasOg = filled(trim((string) $ogImage));

        $quality = $this->analyze(
            title: $title,
            focusKeyword: $kw,
            bodyHtml: $bodyHtml,
            metaDescription: trim($metaDescription),
            faqs: $faqs,
            secondaryKeywords: [],
        );

        if (! empty($flags['keyword_in_title']) && $kw !== '' && empty($quality['keyword_in_title'])) {
            $warnings[] = 'Focus keyword is missing from the title.';
        }

        if (! empty($flags['keyword_in_first_paragraph']) && $kw !== '' && empty($quality['keyword_in_first_paragraph'])) {
            $warnings[] = 'Focus keyword is missing from the first body paragraph (after Quick Answer / AI Summary).';
        }

        if (! empty($flags['word_count_ok']) && empty($quality['word_count_ok'])) {
            $minWords = (int) config('blog_ai.min_body_words', 800);
            $warnings[] = "Body is under {$minWords} words (currently {$quality['word_count']}).";
        }

        if (! empty($flags['missing_og_image']) && ! $hasOg) {
            $warnings[] = 'No OG / cover image set. Social shares will look weaker.';
        }

        if (! empty($flags['missing_content_image']) && ! $hasContentImage) {
            $warnings[] = 'No image in the body. Add a content image with alt text.';
        }

        if (! empty($flags['ai_ready']) && empty($quality['ai_ready'])) {
            $failures = array_values($quality['failures'] ?? []);
            if ($failures !== []) {
                $warnings[] = 'SEO checklist still incomplete: '.implode(', ', array_slice($failures, 0, 6))
                    .(count($failures) > 6 ? '…' : '').'.';
            }
        }

        return array_values(array_unique($warnings));
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
        // Skip Quick Answer / AI Summary blocks — keyword check targets the first content <p>.
        $content = preg_replace(
            '/<section\b[^>]*class=["\'][^"\']*(seo-quick-answer|seo-ai-summary)[^"\']*["\'][\s\S]*?<\/section>/iu',
            '',
            $html,
        ) ?? $html;

        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content, $m)) {
            return trim(html_entity_decode(strip_tags($m[1])));
        }

        $plain = $this->plainText($content);

        return Str::limit($plain, 400, '');
    }

    /**
     * Ensure the title contains the focus keyword (prepend when missing).
     */
    public function ensureKeywordInTitle(string $title, string $focusKeyword): string
    {
        $kw = trim($focusKeyword);
        $title = trim($title);
        if ($kw === '' || $title === '') {
            return $title;
        }
        if ($this->textContainsKeyword($title, $kw)) {
            return $title;
        }

        return Str::limit($kw.' — '.$title, 120, '');
    }

    /**
     * Ensure meta description contains the focus keyword and stays within 50–160 chars.
     */
    public function ensureKeywordInMeta(string $metaDescription, string $focusKeyword): string
    {
        $kw = trim($focusKeyword);
        $meta = trim($metaDescription);
        if ($kw === '') {
            return $meta;
        }

        if ($meta === '') {
            $meta = $kw.' নিয়ে বাংলাদেশি সেলারদের জন্য ব্যবহারিক গাইড — ধাপ, টিপস ও সতর্কতা।';
        } elseif (! $this->textContainsKeyword($meta, $kw)) {
            $meta = $kw.' — '.$meta;
        }

        if (mb_strlen($meta) > 160) {
            $meta = Str::limit($meta, 157, '…');
            // Keep keyword if truncate removed it (rare with prepend).
            if (! $this->textContainsKeyword($meta, $kw)) {
                $meta = Str::limit($kw.' — '.preg_replace('/^'.preg_quote($kw, '/').'\s*[—\-]\s*/u', '', $meta) ?? $meta, 157, '…');
            }
        }

        if (mb_strlen($meta) < 50) {
            $pad = ' বাংলাদেশে COD ও ইকমার্স সেলারদের জন্য স্পষ্ট ধাপ।';
            $meta = Str::limit($meta.$pad, 160, '');
        }

        return $meta;
    }

    /**
     * Ensure at least one content H2 includes the focus keyword.
     */
    public function ensureKeywordInH2(string $bodyHtml, string $focusKeyword): string
    {
        $kw = trim($focusKeyword);
        if ($kw === '' || $this->keywordInHeading($bodyHtml, 'h2', $kw)) {
            return $bodyHtml;
        }

        $heading = e($kw).' কীভাবে কাজ করে';
        $done = false;

        $updated = preg_replace_callback(
            '/<section\b[^>]*class=["\'][^"\']*(?:seo-quick-answer|seo-ai-summary)[^"\']*["\'][\s\S]*?<\/section>|<h2\b[^>]*>.*?<\/h2>/iu',
            function (array $m) use (&$done, $heading): string {
                if ($done) {
                    return $m[0];
                }
                if (preg_match('/seo-(?:quick-answer|ai-summary)/i', $m[0])) {
                    return $m[0];
                }
                // Skip SEO block titles if matched as bare h2.
                $text = trim(html_entity_decode(strip_tags($m[0])));
                if (preg_match('/^(Quick Answer|দ্রুত উত্তর|AI Search Summary|AI Summary|এআই সারাংশ)$/iu', $text)) {
                    return $m[0];
                }
                $done = true;

                return '<h2>'.$heading.'</h2>';
            },
            $bodyHtml,
        );

        if (is_string($updated) && $done) {
            return $updated;
        }

        $block = '<h2>'.$heading.'</h2>';
        if (preg_match(
            '/((?:<section\b[^>]*class=["\'][^"\']*(?:seo-quick-answer|seo-ai-summary)[^"\']*["\'][\s\S]*?<\/section>\s*)+)/iu',
            $bodyHtml,
            $m,
            PREG_OFFSET_CAPTURE,
        )) {
            $end = $m[0][1] + strlen($m[0][0]);

            return substr($bodyHtml, 0, $end).$block."\n".substr($bodyHtml, $end);
        }

        return $block."\n".$bodyHtml;
    }

    /**
     * Ensure at least one secondary keyword appears in the body plain text.
     *
     * @param  list<string>  $secondaryKeywords
     */
    public function ensureSecondaryKeywordsInBody(string $bodyHtml, array $secondaryKeywords): string
    {
        $plain = $this->plainText($bodyHtml);
        foreach ($secondaryKeywords as $secondary) {
            $s = trim((string) $secondary);
            if ($s !== '' && $this->textContainsKeyword($plain, $s)) {
                return $bodyHtml;
            }
        }

        $first = '';
        foreach ($secondaryKeywords as $secondary) {
            $s = trim((string) $secondary);
            if ($s !== '') {
                $first = $s;
                break;
            }
        }
        if ($first === '') {
            return $bodyHtml;
        }

        $sentence = e($first).' সম্পর্কিত টিপসও এই গাইডে আলোচনা করা হয়েছে।';

        return rtrim($bodyHtml)."\n<p>".$sentence.'</p>';
    }

    /**
     * Ensure the first content paragraph (after SEO blocks) contains the focus keyword.
     */
    public function ensureKeywordInFirstParagraph(string $bodyHtml, string $focusKeyword): string
    {
        $kw = trim($focusKeyword);
        if ($kw === '') {
            return $bodyHtml;
        }

        if ($this->textContainsKeyword($this->firstParagraphText($bodyHtml), $kw)) {
            return $bodyHtml;
        }

        $sentence = e($kw).' নিয়ে এই গাইডে বাংলাদেশি সেলারদের ব্যবহারিক ধাপ আলোচনা করা হয়েছে। ';
        $done = false;

        // Rewrite the first <p> that is not inside Quick Answer / AI Summary.
        $updated = preg_replace_callback(
            '/<section\b[^>]*class=["\'][^"\']*(?:seo-quick-answer|seo-ai-summary)[^"\']*["\'][\s\S]*?<\/section>|<p\b[^>]*>.*?<\/p>/iu',
            function (array $m) use (&$done, $sentence): string {
                if ($done) {
                    return $m[0];
                }
                if (preg_match('/seo-(?:quick-answer|ai-summary)/i', $m[0])) {
                    return $m[0];
                }
                $done = true;
                $inner = trim(html_entity_decode(strip_tags($m[0])));

                return '<p>'.$sentence.($inner !== '' ? e($inner) : '').'</p>';
            },
            $bodyHtml,
        );

        if (is_string($updated) && $done) {
            return $updated;
        }

        // No content <p> found — insert after SEO blocks, else prepend.
        if (preg_match(
            '/((?:<section\b[^>]*class=["\'][^"\']*(?:seo-quick-answer|seo-ai-summary)[^"\']*["\'][\s\S]*?<\/section>\s*)+)/iu',
            $bodyHtml,
            $m,
            PREG_OFFSET_CAPTURE,
        )) {
            $end = $m[0][1] + strlen($m[0][0]);

            return substr($bodyHtml, 0, $end).'<p>'.$sentence.'</p>'."\n".substr($bodyHtml, $end);
        }

        return '<p>'.$sentence.'</p>'."\n".$bodyHtml;
    }

    /**
     * Expand body until min word count with varied on-topic Bangla paragraphs.
     */
    public function ensureMinBodyWords(string $bodyHtml, string $focusKeyword, ?int $minWords = null): string
    {
        $min = $minWords ?? (int) config('blog_ai.min_body_words', 800);
        $kw = trim($focusKeyword) !== '' ? trim($focusKeyword) : 'WooEasyLife';
        $body = $bodyHtml;
        $current = $this->bodyWordCount($body);
        if ($current >= $min) {
            return $body;
        }

        $templates = [
            "{$kw} ব্যবহার করে অর্ডার কনফার্মের আগে কাস্টমার হিস্টোরি যাচাই করলে রিটার্ন লস কমে এবং ক্যাশফ্লো স্থিতিশীল থাকে।",
            "বাংলাদেশের COD সেলারদের জন্য {$kw} একটি প্র্যাকটিক্যাল ধাপ — নম্বর দিয়ে রেটিং দেখে ঝুঁকি বোঝা যায়।",
            "প্রতিদিনের অর্ডারে {$kw} চালু রাখলে ফেক অর্ডার আটকানো সহজ হয় এবং কুরিয়ার খরচ বাঁচে।",
            "টিমকে {$kw} ওয়ার্কফ্লো শেখালে কনফার্মেশন কোয়ালিটি বাড়ে এবং সাপোর্ট টিকেট কমে।",
            "Pathao, Steadfast বা RedX অর্ডারেও {$kw} দিয়ে আগে চেক করলে ডেলিভারি সাকসেস রেট উন্নত হয়।",
            "চেকআউটের আগে {$kw} রেজাল্ট সেভ করে রাখলে পরে ডিসপিউট হ্যান্ডেল করা সহজ হয়।",
            "নতুন স্টাফ অনবোর্ডে {$kw} স্ট্যান্ডার্ড অপারেটিং প্রসিডিউর দিলে মিস-কনফার্ম কমে।",
            "হাই-রিস্ক নম্বারে {$kw} রেটিং খারাপ হলে অগ্রিম পেমেন্ট বা বাতিল নীতি প্রয়োগ করা যায়।",
        ];

        $guard = 0;
        $n = count($templates);
        // ~12–18 words per template line; size the loop so thin drafts can still hit min_body_words.
        $approxWordsPerPad = 14;
        $maxPads = max(24, (int) ceil(($min - $current) / $approxWordsPerPad) + 8);
        while ($this->bodyWordCount($body) < $min && $guard < $maxPads) {
            $line = $templates[$guard % $n];
            $suffix = $guard >= $n ? ' (ধাপ '.($guard + 1).')' : '';
            $body = rtrim($body)."\n<p>".e($line.$suffix).'</p>';
            $guard++;
        }

        return $body;
    }

    public function bodyWordCount(string $html): int
    {
        $plain = $this->plainText($html);

        return $plain === '' ? 0 : count(preg_split('/\s+/u', $plain) ?: []);
    }

    public function hasQuickAnswer(string $html): bool
    {
        if (preg_match('/class=["\'][^"\']*seo-quick-answer/i', $html)) {
            return true;
        }

        return (bool) preg_match('/<h2\b[^>]*>[^<]*(Quick Answer|দ্রুত উত্তর)[^<]*<\/h2>/iu', $html);
    }

    public function hasAiSearchSummary(string $html): bool
    {
        if (preg_match('/class=["\'][^"\']*seo-ai-summary/i', $html)) {
            return true;
        }

        return (bool) preg_match(
            '/<h2\b[^>]*>[^<]*(AI Search Summary|AI Summary|এআই সারাংশ)[^<]*<\/h2>/iu',
            $html,
        );
    }

    public function keywordInHeading(string $html, string $tag, string $keyword): bool
    {
        $tag = strtolower($tag);
        if (! in_array($tag, ['h1', 'h2', 'h3'], true)) {
            return false;
        }

        if (! preg_match_all('/<'.$tag.'\b[^>]*>(.*?)<\/'.$tag.'>/is', $html, $matches)) {
            return false;
        }

        foreach ($matches[1] as $headingHtml) {
            $text = trim(html_entity_decode(strip_tags((string) $headingHtml)));
            if ($this->textContainsKeyword($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure Featured Snippet + AI Search Summary blocks exist when the model returned text fields.
     */
    public function ensureSeoContentBlocks(
        string $bodyHtml,
        ?string $quickAnswer,
        ?string $aiSearchSummary,
    ): string {
        $body = $bodyHtml;
        $quick = trim((string) $quickAnswer);
        $summary = trim((string) $aiSearchSummary);

        if ($quick !== '' && ! $this->hasQuickAnswer($body)) {
            $block = '<section class="seo-quick-answer"><h2>দ্রুত উত্তর</h2><p>'
                .e(Str::limit($quick, 500, ''))
                .'</p></section>';
            $body = $block."\n".$body;
        }

        if ($summary !== '' && ! $this->hasAiSearchSummary($body)) {
            $block = '<section class="seo-ai-summary"><h2>এআই সারাংশ</h2><p>'
                .e(Str::limit($summary, 1200, ''))
                .'</p></section>';
            // Place before a trailing FAQ-looking h2 if present; else append.
            if (preg_match('/<h2\b[^>]*>[^<]*(FAQ|প্রশ্ন|সচরাচর)[^<]*<\/h2>/iu', $body, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                $body = substr($body, 0, $pos).$block."\n".substr($body, $pos);
            } else {
                $body = rtrim($body)."\n".$block;
            }
        }

        return $body;
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
