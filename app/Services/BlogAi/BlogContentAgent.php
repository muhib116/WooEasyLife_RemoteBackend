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
        private BlogLandingContextService $landingContext,
        private BlogPromptLibrary $prompts,
        private BlogLearningService $learning,
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

        $gscSeeds = $this->learning->gscKeywordSeeds(10);
        $gscQueries = collect($gscSeeds)->pluck('query')->filter()->values()->all();

        $suggestSeeds = array_values(array_filter(array_merge(
            $seedTopic !== '' ? [$seedTopic] : [],
            $gscQueries,
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
- Prefer gsc_rank_queries (real Search Console opportunities) when relevant to the cluster
- Stay inside WooEasyLife product truth (fraud, courier, missing orders, pixel, AI order, packing, multistore, team)
- Prefer phrases aligned with cluster_landing angle_hint and page H1/lead
- No brand spam lists; each keyword must be useful for an article
TXT;

        $user = json_encode([
            'seed_topic' => $seedTopic,
            'cluster' => $cluster,
            'cluster_label' => $clusterLabel,
            'live_google_suggest_bd' => $liveSuggestions,
            'gsc_rank_queries' => $gscSeeds,
            'product_brief' => $this->briefBuilder->build($cluster),
            'cluster_landing' => $this->landingContext->forCluster($cluster),
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
                array_slice($gscQueries, 0, 5),
                array_slice($seedQueries, 0, 5),
            )));
            $keywords = $fallback !== [] ? $fallback : ['WooCommerce বাংলাদেশ'];
        }

        // Merge GSC opportunity queries the model missed, then live suggestions.
        foreach (array_merge($gscQueries, $liveSuggestions) as $extra) {
            if (count($keywords) >= 12) {
                break;
            }
            if (! in_array($extra, $keywords, true)) {
                $keywords[] = $extra;
            }
        }

        return [
            'keywords' => array_values(array_slice($keywords, 0, 12)),
            'live_suggestions' => $liveSuggestions,
            'gsc_rank_queries' => $gscSeeds,
            'usage' => $result['usage'],
        ];
    }

    /**
     * Normalize pasted keywords + live BD Google Suggest + AI enrichment + cannibalization checks.
     *
     * @param  list<string>  $pasted
     * @param  list<string>  $avoidPrimaries  Primaries already tried / known collisions
     * @return array{primary: string|null, secondary: list<string>, suggestions: list<array<string, mixed>>, live_suggestions: list<string>, cannibalization: list<array<string, mixed>>, auto_pivot: array{from: string, to: string}|null, usage: array<string, int>}
     */
    public function researchKeywords(string $seedTopic, string $cluster, array $pasted, array $avoidPrimaries = []): array
    {
        $pasted = collect($pasted)
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $avoidPrimaries = collect($avoidPrimaries)
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(30)
            ->values()
            ->all();

        $liveSuggestions = $this->keywordSuggest->suggestMany(
            array_values(array_filter(array_merge(
                $pasted,
                $seedTopic !== '' ? [$seedTopic] : [],
            ))),
        );

        $gscSeeds = $this->learning->gscKeywordSeeds(10);

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
Prioritize pasted keywords, gsc_rank_queries (Search Console opportunities), then live Google Suggest (gl=bd).
Align primary intent with cluster_landing (same problem the landing page solves).
If avoid_primary_keywords is non-empty, the primary MUST be a different long-tail angle that is not in that list and not an exact match of existing post focus keywords.
TXT;

        $user = json_encode([
            'seed_topic' => $seedTopic,
            'cluster' => $cluster,
            'cluster_label' => config('blog_ai.clusters.'.$cluster),
            'pasted_keywords' => $pasted,
            'avoid_primary_keywords' => $avoidPrimaries,
            'live_google_suggest_bd' => $liveSuggestions,
            'gsc_rank_queries' => $gscSeeds,
            'product_brief' => $this->briefBuilder->build($cluster),
            'cluster_landing' => $this->landingContext->forCluster($cluster),
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
            collect($suggestions)->pluck('keyword')->all(),
        )));

        $research = [
            'primary' => $primary,
            'secondary' => $secondary,
            'suggestions' => $suggestions,
            'live_suggestions' => $liveSuggestions,
            'cannibalization' => $this->findCannibalization($checkTerms),
            'auto_pivot' => null,
            'usage' => $result['usage'],
        ];

        return $this->preferNonCollidingPrimary($research, $avoidPrimaries);
    }

    /**
     * If primary collides with an existing focus keyword, auto-pick the best safe alternative.
     *
     * @param  array<string, mixed>  $research
     * @param  list<string>  $extraAvoid
     * @return array<string, mixed>
     */
    public function preferNonCollidingPrimary(array $research, array $extraAvoid = []): array
    {
        $normalize = fn (?string $value): string => mb_strtolower(trim((string) $value));

        $blocked = collect($extraAvoid)
            ->map(fn ($k) => $normalize($k))
            ->filter()
            ->values();

        foreach ($research['cannibalization'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $focus = $normalize($row['focus_keyword'] ?? '');
            if ($focus !== '') {
                $blocked->push($focus);
            }
        }

        $blocked = $blocked->unique()->all();
        $primary = trim((string) ($research['primary'] ?? ''));
        $primaryNorm = $normalize($primary);

        $collides = $primary === '' || ($primaryNorm !== '' && in_array($primaryNorm, $blocked, true));
        if (! $collides) {
            $research['auto_pivot'] = null;

            return $research;
        }

        $candidates = collect($research['secondary'] ?? [])
            ->merge(collect($research['suggestions'] ?? [])->pluck('keyword'))
            ->merge($research['live_suggestions'] ?? [])
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->reject(fn ($k) => in_array($normalize($k), $blocked, true))
            ->values();

        $replacement = $candidates->first();
        if (! $replacement) {
            // Last resort: lengthen the colliding primary into a differentiation long-tail.
            if ($primary !== '') {
                $replacement = $primary.' কমানোর উপায় Bangladesh';
                if (in_array($normalize($replacement), $blocked, true)) {
                    $replacement = $primary.' WooEasyLife guide';
                }
            }
        }

        if (! $replacement || in_array($normalize($replacement), $blocked, true)) {
            $research['auto_pivot'] = null;

            return $research;
        }

        $from = $primary !== '' ? $primary : '(empty)';
        $research['primary'] = $replacement;
        $research['secondary'] = collect($research['secondary'] ?? [])
            ->prepend($primary)
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->reject(fn ($k) => $normalize($k) === $normalize($replacement))
            ->unique()
            ->take(12)
            ->values()
            ->all();
        $research['auto_pivot'] = [
            'from' => $from,
            'to' => $replacement,
        ];

        return $research;
    }

    /**
     * @param  list<string>  $avoidTitles  Prior hook titles to avoid repeating
     * @return list<array<string, mixed>>
     */
    public function generateHooks(BlogAiSession $session, ?string $fixInstructions = null, array $avoidTitles = []): array
    {
        $count = (int) config('blog_ai.hooks_count', 10);
        $keywords = $session->keywords_json ?? [];
        $existingPosts = collect($keywords['cannibalization'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'title' => $row['title'] ?? null,
                'focus_keyword' => $row['focus_keyword'] ?? null,
            ])
            ->take(8)
            ->values()
            ->all();

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
Each hook MUST use a different angle and a distinct title wording.
Ground hooks in cluster_landing angle_hint / page lead — do not invent unrelated product pillars.
If existing_posts or avoid_titles is present, differentiate: new angle, persona, tool angle, or long-tail — do not clone those titles.
TXT;

        $cluster = (string) ($session->cluster ?: 'general');
        $user = json_encode([
            'seed_topic' => $session->seed_topic,
            'cluster' => $cluster,
            'keywords' => $keywords,
            'existing_posts' => $existingPosts,
            'avoid_titles' => array_values(array_filter($avoidTitles)),
            'fix_instructions' => $fixInstructions,
            'product_brief' => $this->briefBuilder->build($cluster),
            'cluster_landing' => $this->landingContext->forCluster($cluster),
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

        $outlinePrompt = $this->prompts->outline();
        $system = $this->systemPrompt()."\n\n".($outlinePrompt !== '' ? $outlinePrompt : <<<'TXT'
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
MUST include cluster_landing.primary_path (or must_link_paths) as the first internal link(s).
Use keyword-rich anchors from seo_tools / catalog anchor_hints (e.g. “রিটার্ন লস ক্যালকুলেটর”, “ফ্রড চেকার”) — never “এখানে ক্লিক” / “এই লিংক”.
Prefer ranking free tools: /bd-fraud-checker, /return-loss-calculator, /courier-charge-calculator, /ads-roas-calculator when relevant.
Echo page FAQs/angle_hint truth — do not invent features beyond product_brief + cluster_landing.
Include at least 5 FAQ items under faqs (q + a_points).
Include a differentiation section that beats generic competitor blogs (practical BD COD steps + WooEasyLife truth).
TXT);

        $cluster = (string) ($session->cluster ?: 'general');
        $user = json_encode([
            'selected_hooks' => $selected->all(),
            'keywords' => $session->keywords_json,
            'product_brief' => $this->briefBuilder->build($cluster),
            'cluster_landing' => $this->landingContext->forCluster($cluster),
            'internal_link_catalog' => $this->linkCatalog->all(),
            'fix_instructions' => $fixInstructions,
            'previous_outline' => $fixInstructions ? ($session->outline_json ?? null) : null,
            'seo_targets' => [
                'min_faqs' => (int) config('blog_ai.seo_quality.min_faqs', 5),
                'min_sections' => 4,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.4);

        $outline = $this->openAi->decodeJsonObject($result['content']);
        $outline['internal_links'] = $this->filterValidLinks($outline['internal_links'] ?? [], $cluster);

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
        $minFaqs = (int) config('blog_ai.seo_quality.min_faqs', 5);

        $articlePrompt = $this->prompts->articleWriter((string) $author, $minWords);
        $system = $this->systemPrompt()."\n\n".($articlePrompt !== '' ? $articlePrompt : <<<TXT
Write a complete Bangladesh SEO blog post in Bangla based on the outline.
Return JSON with title, slug, focus_keyword, meta_title, meta_description, excerpt,
author_name "{$author}", quick_answer, ai_search_summary, body_html, faqs, seo_notes.
Requirements: {$minWords}+ words; keyword in title, first <p>, meta, one H2;
at least {$minFaqs} FAQs; Featured Snippet Quick Answer + AI Search Summary sections;
2+ internal links including ALL cluster_landing.must_link_paths with keyword-rich anchors;
link free SEO tools when relevant (/bd-fraud-checker, /return-loss-calculator, /courier-charge-calculator, /ads-roas-calculator);
H2+H3; lists; soft CTA to primary tool.
TXT);

        $cluster = (string) ($session->cluster ?: 'general');
        $user = json_encode([
            'outline' => $session->outline_json,
            'link_plan' => $session->link_plan_json,
            'keywords' => $session->keywords_json,
            'product_brief' => $this->briefBuilder->build($cluster),
            'cluster_landing' => $this->landingContext->forCluster($cluster),
            'fix_instructions' => $fixInstructions,
            'previous_draft_quality' => $fixInstructions
                ? ($session->draft_json['quality'] ?? null)
                : null,
            'seo_targets' => [
                'min_words' => $minWords,
                'min_faqs' => $minFaqs,
                'require_keyword_in_h2' => (bool) config('blog_ai.seo_quality.require_keyword_in_h2', true),
                'require_quick_answer' => (bool) config('blog_ai.seo_quality.require_quick_answer', true),
                'require_ai_search_summary' => (bool) config('blog_ai.seo_quality.require_ai_search_summary', true),
            ],
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
        ], 0.35);

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
        return $this->prompts->system();
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
    private function filterValidLinks(mixed $links, ?string $cluster = null): array
    {
        $allowed = collect($this->linkCatalog->all())->pluck('path')->all();
        $min = (int) config('blog_ai.internal_links_min', 2);
        $landing = filled($cluster) ? $this->landingContext->forCluster((string) $cluster) : null;
        $mustPaths = is_array($landing['must_link_paths'] ?? null)
            ? array_values(array_filter($landing['must_link_paths']))
            : [];

        $filtered = collect(is_array($links) ? $links : [])
            ->filter(fn ($row) => is_array($row) && in_array($row['path'] ?? null, $allowed, true))
            ->map(fn (array $row) => [
                'path' => (string) $row['path'],
                'anchor' => trim((string) ($row['anchor'] ?? $row['path'])),
                'reason' => trim((string) ($row['reason'] ?? '')),
            ])
            ->unique('path')
            ->values()
            ->all();

        // Ensure cluster landing primary/must links are present and ordered first.
        foreach (array_reverse($mustPaths) as $mustPath) {
            if (! in_array($mustPath, $allowed, true)) {
                continue;
            }
            $existing = collect($filtered)->first(fn ($l) => $l['path'] === $mustPath);
            $filtered = collect($filtered)->reject(fn ($l) => $l['path'] === $mustPath)->values()->all();
            $catalogItem = collect($this->linkCatalog->all())->firstWhere('path', $mustPath);
            $tool = collect(config('blog_ai.seo_tools', []))->firstWhere('path', $mustPath);
            $keywordAnchor = (is_array($tool['keywords'] ?? null) && filled($tool['keywords'][0] ?? null))
                ? (string) $tool['keywords'][0]
                : null;
            $fallbackAnchor = $keywordAnchor
                ?? ($catalogItem['anchor_hints'][0] ?? null)
                ?? ($catalogItem['title'] ?? $mustPath);
            $existingAnchor = is_array($existing) ? trim((string) ($existing['anchor'] ?? '')) : '';
            array_unshift($filtered, [
                'path' => $mustPath,
                'anchor' => $existingAnchor !== '' ? $existingAnchor : (string) $fallbackAnchor,
                'reason' => (is_array($existing) && ($existing['reason'] ?? '') !== '')
                    ? (string) $existing['reason']
                    : 'required cluster SEO tool / landing',
            ]);
        }

        // Prefer one extra related SEO tool when room remains.
        $toolPaths = collect(config('blog_ai.seo_tools', []))
            ->pluck('path')
            ->filter()
            ->all();
        $related = is_array($landing['related_paths'] ?? null) ? $landing['related_paths'] : [];
        foreach ($related as $relatedPath) {
            if (count($filtered) >= 4) {
                break;
            }
            if (! in_array($relatedPath, $allowed, true) || ! in_array($relatedPath, $toolPaths, true)) {
                continue;
            }
            if (collect($filtered)->contains(fn ($l) => $l['path'] === $relatedPath)) {
                continue;
            }
            $tool = collect(config('blog_ai.seo_tools', []))->firstWhere('path', $relatedPath);
            $catalogItem = collect($this->linkCatalog->all())->firstWhere('path', $relatedPath);
            $filtered[] = [
                'path' => $relatedPath,
                'anchor' => (string) (($tool['keywords'][0] ?? null) ?: ($catalogItem['anchor_hints'][0] ?? $relatedPath)),
                'reason' => 'related SEO tool for topical ranking',
            ];
        }

        $filtered = array_slice($filtered, 0, 4);

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

        $body = BlogHtmlSanitizer::sanitize(
            $this->seoQuality->ensureSeoContentBlocks(
                $body,
                is_string($draft['quick_answer'] ?? null) ? (string) $draft['quick_answer'] : null,
                is_string($draft['ai_search_summary'] ?? null) ? (string) $draft['ai_search_summary'] : (
                    is_array($outline['ai_search_summary_points'] ?? null)
                        ? collect($outline['ai_search_summary_points'])->filter()->implode(' ')
                        : null
                ),
            )
        );

        // Prefer outline quick_answer_points when model omitted quick_answer field.
        if (! $this->seoQuality->hasQuickAnswer($body) && is_array($outline['quick_answer_points'] ?? null)) {
            $fromOutline = collect($outline['quick_answer_points'])->map(fn ($p) => trim((string) $p))->filter()->implode(' ');
            if ($fromOutline !== '') {
                $body = BlogHtmlSanitizer::sanitize(
                    $this->seoQuality->ensureSeoContentBlocks($body, $fromOutline, null)
                );
            }
        }

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
        $autoKeywordPivot = null;

        // Never leave an exact published focus-keyword collision in the draft.
        $suffixes = ['WooEasyLife BD', 'কমানোর উপায়', 'COD sellers guide', 'Bangladesh ২০২৬'];
        for ($i = 0; $i < count($suffixes) + 1; $i++) {
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

            if (empty($quality['focus_keyword_collision'])) {
                break;
            }

            $from = $focusKeyword;
            $suffix = $suffixes[min($i, count($suffixes) - 1)];
            $focusKeyword = trim($from === '' ? $suffix : $from.' '.$suffix);
            $autoKeywordPivot = ['from' => $from, 'to' => $focusKeyword];
            $slug = BlogPost::makeSlug(Str::slug($focusKeyword) ?: $slug);
        }

        // Deterministic SEO closers — Auto Create must not stall forever on thin AI drafts.
        $wordsBefore = $this->seoQuality->bodyWordCount($body);
        $body = $this->seoQuality->ensureKeywordInFirstParagraph($body, $focusKeyword);
        $body = $this->seoQuality->ensureMinBodyWords($body, $focusKeyword);
        $body = BlogHtmlSanitizer::sanitize($body);

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

        $notes = is_array($draft['seo_notes'] ?? null) ? $draft['seo_notes'] : [];
        if ($autoKeywordPivot) {
            $notes[] = 'Auto-pivoted focus keyword to avoid published collision: '
                .($autoKeywordPivot['from'] ?: '(empty)')
                .' → '
                .$autoKeywordPivot['to'];
        }
        if ($this->seoQuality->bodyWordCount($body) > $wordsBefore) {
            $notes[] = 'Expanded body to meet minimum word count for SEO readiness.';
        }

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
            'quick_answer' => Str::limit(trim((string) ($draft['quick_answer'] ?? '')), 500, ''),
            'ai_search_summary' => Str::limit(trim((string) ($draft['ai_search_summary'] ?? '')), 1200, ''),
            'body_html' => $body,
            'faqs' => $faqs,
            'seo_notes' => $notes,
            'quality' => $quality,
            'cluster' => $cluster,
            'auto_keyword_pivot' => $autoKeywordPivot,
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
            ->take(10)
            ->values()
            ->all();
    }
}
