<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiSession;
use App\Models\BlogPost;
use App\Services\BlogSeoQuality;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogContentAgent
{
    public function __construct(
        private OpenAiBlogClient $openAi,
        private BlogProductBriefBuilder $briefBuilder,
        private InternalLinkCatalog $linkCatalog,
        private BdKeywordSuggestService $keywordSuggest,
        private BlogSeoQuality $seoQuality,
    ) {}

    /**
     * Generate BD keyword candidates from cluster + optional seed (Google Suggest + OpenAI).
     * Used to prefill the wizard keyword box before research.
     *
     * @return array{keywords: list<string>, live_suggestions: list<string>, usage: array<string, int>}
     */
    public function suggestSeedKeywords(string $seedTopic, string $cluster): array
    {
        $clusterLabel = (string) config('blog_ai.clusters.'.$cluster, $cluster);
        $seedQueries = config('blog_ai.cluster_seed_queries.'.$cluster, []);
        if (! is_array($seedQueries)) {
            $seedQueries = [];
        }

        $suggestSeeds = array_values(array_filter(array_merge(
            $seedTopic !== '' ? [$seedTopic] : [],
            $seedQueries,
            [$clusterLabel],
        )));

        $liveSuggestions = $this->keywordSuggest->suggestMany($suggestSeeds, 6, 20);

        $system = $this->systemPrompt().<<<'TXT'


Generate Bangladesh Google SEO keyword candidates for a WooEasyLife blog about the given cluster.
Return JSON only:
{
  "keywords": ["kw1", "kw2", "... up to 12"]
}
Rules:
- Prefer Bangla or BD-English hybrid phrases BD COD / WooCommerce sellers actually search
- Include 1–2 short head terms and several long-tail phrases
- Ground keywords in live Google Suggest (gl=bd) when provided; do not invent US-centric terms
- Stay inside WooEasyLife product truth (fraud, courier, missing orders, pixel, AI order, packing, multistore, team)
- No brand spam lists; each keyword must be useful for an article
TXT;

        $user = json_encode([
            'seed_topic' => $seedTopic,
            'cluster' => $cluster,
            'cluster_label' => $clusterLabel,
            'live_google_suggest_bd' => $liveSuggestions,
            'product_brief' => $this->briefBuilder->build(),
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.45);

        $data = $this->openAi->decodeJsonObject($result['content']);
        $keywords = collect($data['keywords'] ?? [])
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();

        // Prefer live suggestions if the model returned nothing useful.
        if ($keywords === [] && $liveSuggestions !== []) {
            $keywords = array_slice($liveSuggestions, 0, 8);
        }

        if ($keywords === []) {
            $fallback = array_values(array_filter(array_merge(
                $seedTopic !== '' ? [$seedTopic] : [],
                array_slice($seedQueries, 0, 5),
            )));
            $keywords = $fallback !== [] ? $fallback : ['WooCommerce বাংলাদেশ'];
        }

        // Merge a few live suggestions the model missed.
        foreach ($liveSuggestions as $live) {
            if (count($keywords) >= 12) {
                break;
            }
            if (! in_array($live, $keywords, true)) {
                $keywords[] = $live;
            }
        }

        return [
            'keywords' => array_values(array_slice($keywords, 0, 12)),
            'live_suggestions' => $liveSuggestions,
            'usage' => $result['usage'],
        ];
    }

    /**
     * Normalize pasted keywords + live BD Google Suggest + AI enrichment + cannibalization checks.
     *
     * @param  list<string>  $pasted
     * @return array{primary: string|null, secondary: list<string>, suggestions: list<array<string, mixed>>, live_suggestions: list<string>, cannibalization: list<array<string, mixed>>, usage: array<string, int>}
     */
    public function researchKeywords(string $seedTopic, string $cluster, array $pasted): array
    {
        $pasted = collect($pasted)
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $liveSuggestions = $this->keywordSuggest->suggestMany(
            array_values(array_filter(array_merge(
                $pasted,
                $seedTopic !== '' ? [$seedTopic] : [],
            ))),
        );

        $system = $this->systemPrompt().<<<'TXT'


You help with Bangladesh Google SEO keyword planning for WooEasyLife blog posts.
Return JSON only:
{
  "primary": "best primary keyword (Bangla preferred)",
  "secondary": ["kw2", "kw3"],
  "suggestions": [
    {"keyword": "...", "intent": "informational|commercial|transactional", "notes": "why relevant for BD sellers"}
  ]
}
Prefer Bangla or BD-English hybrid phrases sellers actually search.
Do not invent US-centric keywords.
Prioritize pasted keywords and live Google Suggest results from Bangladesh (gl=bd).
TXT;

        $user = json_encode([
            'seed_topic' => $seedTopic,
            'cluster' => $cluster,
            'cluster_label' => config('blog_ai.clusters.'.$cluster),
            'pasted_keywords' => $pasted,
            'live_google_suggest_bd' => $liveSuggestions,
            'product_brief' => $this->briefBuilder->build(),
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.4);

        $data = $this->openAi->decodeJsonObject($result['content']);
        $primary = trim((string) ($data['primary'] ?? ($pasted[0] ?? ''))) ?: null;
        $secondary = collect($data['secondary'] ?? [])
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->reject(fn ($k) => $k === $primary)
            ->take(12)
            ->values()
            ->all();

        $suggestions = collect($data['suggestions'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['keyword'] ?? null))
            ->take(15)
            ->values()
            ->all();

        // Merge live suggestions into secondary if room.
        foreach ($liveSuggestions as $live) {
            if (count($secondary) >= 12) {
                break;
            }
            if ($live === $primary || in_array($live, $secondary, true)) {
                continue;
            }
            $secondary[] = $live;
        }

        $checkTerms = array_values(array_filter(array_merge(
            $primary ? [$primary] : [],
            $secondary,
            $pasted,
            $liveSuggestions,
        )));

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'suggestions' => $suggestions,
            'live_suggestions' => $liveSuggestions,
            'cannibalization' => $this->findCannibalization($checkTerms),
            'usage' => $result['usage'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function generateHooks(BlogAiSession $session): array
    {
        $count = (int) config('blog_ai.hooks_count', 10);
        $keywords = $session->keywords_json ?? [];

        $system = $this->systemPrompt().<<<TXT


Generate {$count} powerful Bangla blog hook titles for Bangladesh SEO.
Return JSON:
{
  "hooks": [
    {
      "id": "h1",
      "title": "Bangla hook title",
      "focus_keyword": "...",
      "angle": "pain|howto|comparison|myth|checklist|story",
      "why_it_ranks": "one sentence",
      "risk": "optional risk note"
    }
  ]
}
Hooks must target BD COD / WooCommerce sellers. Mix angles. No clickbait lies.
TXT;

        $user = json_encode([
            'seed_topic' => $session->seed_topic,
            'cluster' => $session->cluster,
            'keywords' => $keywords,
            'product_brief' => $this->briefBuilder->build(),
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.8);

        $data = $this->openAi->decodeJsonObject($result['content']);
        $hooks = collect($data['hooks'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
            ->values()
            ->map(function (array $row, int $i) {
                return [
                    'id' => (string) ($row['id'] ?? 'h'.($i + 1)),
                    'title' => trim((string) $row['title']),
                    'focus_keyword' => trim((string) ($row['focus_keyword'] ?? '')),
                    'angle' => trim((string) ($row['angle'] ?? 'howto')),
                    'why_it_ranks' => trim((string) ($row['why_it_ranks'] ?? '')),
                    'risk' => trim((string) ($row['risk'] ?? '')),
                ];
            })
            ->take($count)
            ->all();

        if ($hooks === []) {
            throw ValidationException::withMessages([
                'ai' => 'AI did not return any hooks. Try again.',
            ]);
        }

        $session->hooks_json = $hooks;
        $session->addUsage($result['usage']);
        $session->status = 'hooks_ready';
        $session->saveIfJobCurrent();

        return $hooks;
    }

    /**
     * @param  list<string>  $selectedIds
     * @return array<string, mixed>
     */
    public function generateOutline(BlogAiSession $session, array $selectedIds, ?string $fixInstructions = null): array
    {
        $hooks = collect($session->hooks_json ?? []);
        $selected = $hooks->whereIn('id', $selectedIds)->values();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'selected_hook_ids' => 'Select at least one hook.',
            ]);
        }

        $system = $this->systemPrompt().<<<'TXT'


Create one SEO outline for a Bangla blog post using the selected hook(s).
Prefer the first selected hook as H1; others may become H2 angles if complementary.
Return JSON:
{
  "h1": "...",
  "focus_keyword": "...",
  "slug_suggestion": "latin-kebab-slug",
  "sections": [{"heading": "H2...", "bullets": ["..."]}],
  "faqs": [{"q": "...", "a_points": ["..."]}],
  "internal_links": [{"path": "/...", "anchor": "...", "reason": "..."}],
  "cta": "soft CTA sentence"
}
Use ONLY paths from the provided internal link catalog (2–4 links).
Include 3–6 FAQ items under faqs (q + a_points).
Include a differentiation section that beats generic competitor blogs (practical BD COD steps + WooEasyLife truth).
TXT;

        $user = json_encode([
            'selected_hooks' => $selected->all(),
            'keywords' => $session->keywords_json,
            'product_brief' => $this->briefBuilder->build(),
            'internal_link_catalog' => $this->linkCatalog->all(),
            'fix_instructions' => $fixInstructions,
            'previous_outline' => $fixInstructions ? ($session->outline_json ?? null) : null,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.5);

        $outline = $this->openAi->decodeJsonObject($result['content']);
        $outline['internal_links'] = $this->filterValidLinks($outline['internal_links'] ?? []);

        $session->selected_hook_ids = $selectedIds;
        $session->outline_json = $outline;
        $session->link_plan_json = $outline['internal_links'];
        $session->addUsage($result['usage']);
        $session->status = 'outline_ready';
        $session->saveIfJobCurrent();

        return $outline;
    }

    /**
     * @return array<string, mixed>
     */
    public function generateDraft(BlogAiSession $session, ?string $fixInstructions = null): array
    {
        if (! is_array($session->outline_json) || $session->outline_json === []) {
            throw ValidationException::withMessages([
                'outline' => 'Generate an outline first.',
            ]);
        }

        $minWords = (int) config('blog_ai.min_body_words', 800);
        $author = config('blog_ai.author_name', 'Muhibbullah Ansary');

        $system = $this->systemPrompt().<<<TXT


Write a complete Bangladesh SEO blog post in Bangla based on the outline.
Return JSON:
{
  "title": "...",
  "slug": "latin-kebab-only",
  "locale": "bn",
  "focus_keyword": "...",
  "meta_title": "<=70 chars",
  "meta_description": "50-160 chars",
  "excerpt": "...",
  "author_name": "{$author}",
  "robots": "index,follow",
  "body_html": "<h2>...</h2><p>...</p> valid HTML only (h2,h3,p,ul,ol,li,a,strong,em,blockquote)",
  "faqs": [{"q": "...", "a": "..."}],
  "seo_notes": ["..."]
}
Requirements:
- body_html roughly {$minWords}+ words of Bangla content
- Include focus keyword in title, FIRST <p> paragraph, meta_description, and one H2 naturally
- Include at least 2 secondary keywords from keywords.secondary naturally in body (not stuffed)
- Include at least 2 internal links using exact paths from link_plan (href="/path")
- Include 3–6 FAQs matching the outline (q/a plain text)
- One soft WooEasyLife CTA near the end — not spammy
- No script tags, no invented product claims
- slug must match ^[a-z0-9]+(?:-[a-z0-9]+)*$ and must be unique (avoid colliding with existing posts)
- If fix_instructions are provided, obey them strictly while keeping product truth
TXT;

        $user = json_encode([
            'outline' => $session->outline_json,
            'link_plan' => $session->link_plan_json,
            'keywords' => $session->keywords_json,
            'product_brief' => $this->briefBuilder->build(),
            'fix_instructions' => $fixInstructions,
            'previous_draft_quality' => $fixInstructions
                ? ($session->draft_json['quality'] ?? null)
                : null,
            'existing_slug_collisions' => $this->seoQuality->findCollisions(
                slug: is_string($session->outline_json['slug_suggestion'] ?? null)
                    ? (string) $session->outline_json['slug_suggestion']
                    : null,
                focusKeyword: is_string($session->outline_json['focus_keyword'] ?? null)
                    ? (string) $session->outline_json['focus_keyword']
                    : null,
                locale: 'bn',
            ),
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.55);

        $draft = $this->openAi->decodeJsonObject($result['content']);
        $draft = $this->normalizeDraft(
            $draft,
            $author,
            $session->outline_json ?? [],
            $session->link_plan_json ?? [],
            $session->keywords_json ?? [],
            $session->cluster,
        );

        $session->draft_json = $draft;
        $session->addUsage($result['usage']);
        $session->status = 'draft_ready';
        $session->saveIfJobCurrent();

        return $draft;
    }

    private function systemPrompt(): string
    {
        return 'You are an expert Bangladesh SEO content strategist for WooEasyLife, a WooCommerce operations platform for BD sellers (fraud checker, checkout OTP/block, auto courier, missing orders, Facebook pixel protection, AI message-to-order, packing/print, multistore app, team call tracking). Always obey the product brief. Never invent features or numbers. When performance_learning is present, prefer recommended clusters, winning title angles, and coverage gaps; avoid cloning underperforming topics.';
    }

    /**
     * @param  list<string>  $terms
     * @return list<array<string, mixed>>
     */
    private function findCannibalization(array $terms): array
    {
        if ($terms === []) {
            return [];
        }

        $posts = BlogPost::query()
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $q->orWhere('focus_keyword', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('slug', 'like', '%'.Str::slug($term).'%');
                }
            })
            ->limit(10)
            ->get(['id', 'title', 'slug', 'focus_keyword', 'status']);

        return $posts->map(fn (BlogPost $post) => [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'focus_keyword' => $post->focus_keyword,
            'status' => $post->status,
        ])->all();
    }

    /**
     * @return list<array{path: string, anchor: string, reason?: string}>
     */
    private function filterValidLinks(mixed $links): array
    {
        $allowed = collect($this->linkCatalog->all())->pluck('path')->all();
        $min = (int) config('blog_ai.internal_links_min', 2);

        $filtered = collect(is_array($links) ? $links : [])
            ->filter(fn ($row) => is_array($row) && in_array($row['path'] ?? null, $allowed, true))
            ->map(fn (array $row) => [
                'path' => (string) $row['path'],
                'anchor' => trim((string) ($row['anchor'] ?? $row['path'])),
                'reason' => trim((string) ($row['reason'] ?? '')),
            ])
            ->unique('path')
            ->take(4)
            ->values()
            ->all();

        if (count($filtered) < $min) {
            foreach ($this->linkCatalog->all() as $item) {
                if (count($filtered) >= $min) {
                    break;
                }
                if (collect($filtered)->contains(fn ($l) => $l['path'] === $item['path'])) {
                    continue;
                }
                $filtered[] = [
                    'path' => $item['path'],
                    'anchor' => $item['anchor_hints'][0] ?? $item['title'],
                    'reason' => 'fallback catalog link',
                ];
            }
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $outline
     * @param  list<array{path?: string, anchor?: string}>  $linkPlan
     * @param  array<string, mixed>  $keywords
     * @return array<string, mixed>
     */
    private function normalizeDraft(
        array $draft,
        string $author,
        array $outline = [],
        array $linkPlan = [],
        array $keywords = [],
        ?string $cluster = null,
    ): array {
        $slug = Str::slug((string) ($draft['slug'] ?? ''));
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $slug = Str::slug((string) ($draft['focus_keyword'] ?? 'woo-easylife-guide'));
        }

        // Resolve slug collisions without failing the draft.
        $slug = BlogPost::makeSlug($slug !== '' ? $slug : 'woo-easylife-guide');

        $body = BlogHtmlSanitizer::sanitize((string) ($draft['body_html'] ?? ''));
        if ($body === '') {
            throw ValidationException::withMessages([
                'ai' => 'AI draft body was empty after sanitization.',
            ]);
        }

        $minLinks = (int) config('blog_ai.seo_quality.min_internal_links', config('blog_ai.internal_links_min', 2));
        $body = BlogHtmlSanitizer::sanitize(
            $this->seoQuality->ensureInternalLinks($body, $linkPlan, $minLinks)
        );

        $metaTitle = Str::limit(trim((string) ($draft['meta_title'] ?? $draft['title'] ?? '')), 70, '');
        $metaDescription = Str::limit(trim((string) ($draft['meta_description'] ?? $draft['excerpt'] ?? '')), 160, '');
        $faqs = $this->normalizeFaqs($draft['faqs'] ?? null, $outline['faqs'] ?? null);
        $secondary = collect($keywords['secondary'] ?? [])
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $title = trim((string) ($draft['title'] ?? ''));
        $focusKeyword = trim((string) ($draft['focus_keyword'] ?? ''));

        $quality = $this->seoQuality->analyze(
            title: $title,
            focusKeyword: $focusKeyword,
            bodyHtml: $body,
            metaDescription: $metaDescription,
            faqs: $faqs,
            secondaryKeywords: $secondary,
            slug: $slug,
            locale: 'bn',
        );

        return [
            'title' => $title,
            'slug' => $slug,
            'locale' => 'bn',
            'status' => 'draft',
            'focus_keyword' => $focusKeyword,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'excerpt' => Str::limit(trim((string) ($draft['excerpt'] ?? '')), 500, ''),
            'author_name' => trim((string) ($draft['author_name'] ?? $author)) ?: $author,
            'robots' => 'index,follow',
            'body_html' => $body,
            'faqs' => $faqs,
            'seo_notes' => is_array($draft['seo_notes'] ?? null) ? $draft['seo_notes'] : [],
            'quality' => $quality,
            'cluster' => $cluster,
        ];
    }

    /**
     * @return list<array{q: string, a: string}>
     */
    private function normalizeFaqs(mixed $draftFaqs, mixed $outlineFaqs): array
    {
        $source = is_array($draftFaqs) && $draftFaqs !== [] ? $draftFaqs : $outlineFaqs;
        if (! is_array($source)) {
            return [];
        }

        return collect($source)
            ->filter(fn ($row) => is_array($row) && filled($row['q'] ?? null))
            ->map(function (array $row) {
                $answer = trim((string) ($row['a'] ?? ''));
                if ($answer === '' && is_array($row['a_points'] ?? null)) {
                    $answer = collect($row['a_points'])
                        ->map(fn ($p) => trim((string) $p))
                        ->filter()
                        ->implode(' ');
                }

                return [
                    'q' => Str::limit(trim((string) $row['q']), 200, ''),
                    'a' => Str::limit($answer, 500, ''),
                ];
            })
            ->filter(fn (array $row) => $row['q'] !== '' && $row['a'] !== '')
            ->take(8)
            ->values()
            ->all();
    }
}
