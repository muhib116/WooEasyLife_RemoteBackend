<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiSession;
use App\Services\BlogSeoQuality;
use Illuminate\Support\Str;
use Throwable;

/**
 * Per-step reviewer for the auto pipeline. Returns a strict decision contract.
 */
class BlogStepReviewAgent
{
    public function __construct(
        private OpenAiBlogClient $openAi,
        private BlogProductBriefBuilder $briefBuilder,
        private BlogSeoQuality $seoQuality,
        private BlogReadinessScorer $scorer,
        private BlogLandingContextService $landingContext,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     pass: bool,
     *     score: int,
     *     decision: string,
     *     failures: list<string>,
     *     fix_instructions: string|null,
     *     notes: string|null,
     *     usage: array<string, int>
     * }
     */
    public function review(string $step, BlogAiSession $session, array $context = []): array
    {
        $rule = $this->ruleBasedReview($step, $session, $context);

        if (! config('blog_ai.auto.use_llm_review', true)) {
            return $rule;
        }

        // Hard abort never calls the LLM.
        if ($rule['decision'] === 'abort') {
            return $rule;
        }

        try {
            $llm = $this->llmReview($step, $session, $context, $rule);

            return $this->mergeRuleAndLlm($step, $rule, $llm);
        } catch (Throwable) {
            return $rule;
        }
    }

    /**
     * Rule always wins on fail. LLM may only tighten (fail a rule-pass) or enrich notes/fixes.
     * Research is rule-authoritative once primary is non-colliding (Auto already pivots keywords).
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $llm
     * @return array<string, mixed>
     */
    private function mergeRuleAndLlm(string $step, array $rule, array $llm): array
    {
        // Soft LLM "abort" must not kill Auto when hard rules did not hard-abort.
        if (($llm['decision'] ?? '') === 'abort' && ($rule['decision'] ?? '') !== 'abort') {
            $llm['decision'] = 'revise';
            $llm['pass'] = false;
        }

        if (! $rule['pass']) {
            return [
                'pass' => false,
                'score' => min((int) $rule['score'], (int) $llm['score']),
                'decision' => $rule['decision'] === 'abort' ? 'abort' : (
                    in_array($llm['decision'], ['revise', 'abort'], true) ? $llm['decision'] : $rule['decision']
                ),
                'failures' => array_values(array_unique(array_merge($rule['failures'], $llm['failures']))),
                'fix_instructions' => filled($llm['fix_instructions'])
                    ? $llm['fix_instructions']
                    : $rule['fix_instructions'],
                'notes' => $llm['notes'] ?? $rule['notes'],
                'usage' => $llm['usage'],
            ];
        }

        // Rule passed: LLM may still fail/revise and block advance — except planning steps
        // where Auto already enforces hard rules and can differentiate in later writing.
        if (! $llm['pass'] || $llm['decision'] !== 'advance') {
            if (in_array($step, ['research', 'hooks', 'outline'], true)) {
                $noteBits = array_filter([
                    $rule['notes'] ?? null,
                    $llm['notes'] ?? null,
                    $llm['fix_instructions'] ?? null,
                ]);

                return [
                    'pass' => true,
                    'score' => (int) round(((int) $rule['score'] + min(100, max(0, (int) $llm['score']))) / 2),
                    'decision' => 'advance',
                    'failures' => [],
                    'fix_instructions' => null,
                    'notes' => $noteBits !== []
                        ? implode(' ', $noteBits)
                        : ucfirst($step).' rules passed; keep differentiation in later steps.',
                    'usage' => $llm['usage'],
                ];
            }

            return [
                'pass' => false,
                'score' => (int) $llm['score'],
                'decision' => $llm['decision'] === 'abort' ? 'abort' : 'revise',
                'failures' => $llm['failures'] !== [] ? $llm['failures'] : ['llm_review_failed'],
                'fix_instructions' => $llm['fix_instructions'] ?: $rule['fix_instructions'],
                'notes' => $llm['notes'] ?? null,
                'usage' => $llm['usage'],
            ];
        }

        return [
            'pass' => true,
            'score' => (int) round(((int) $rule['score'] + (int) $llm['score']) / 2),
            'decision' => 'advance',
            'failures' => [],
            'fix_instructions' => null,
            'notes' => $llm['notes'] ?? $rule['notes'],
            'usage' => $llm['usage'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     pass: bool,
     *     score: int,
     *     decision: string,
     *     failures: list<string>,
     *     fix_instructions: string|null,
     *     notes: string|null,
     *     usage: array<string, int>
     * }
     */
    private function ruleBasedReview(string $step, BlogAiSession $session, array $context): array
    {
        $passScore = (int) config('blog_ai.auto.pass_score', 70);
        $failures = [];
        $score = 70;
        $fix = null;
        $decision = 'advance';
        $notes = null;

        if ($step === 'research') {
            $keywords = $session->keywords_json ?? [];
            $primary = trim((string) ($keywords['primary'] ?? ''));
            if ($primary === '') {
                $failures[] = 'missing_primary_keyword';
                $score = 20;
                $decision = 'revise';
                $fix = 'Choose a clear BD search primary keyword from pasted or live suggestions.';
            } else {
                $score = 78;
            }

            $cannibal = collect($keywords['cannibalization'] ?? []);
            $exact = $cannibal->first(function ($row) use ($primary) {
                if (! is_array($row)) {
                    return false;
                }

                return mb_strtolower(trim((string) ($row['focus_keyword'] ?? ''))) === mb_strtolower($primary);
            });
            if ($exact) {
                $failures[] = 'focus_keyword_collision';
                $score = min($score, 40);
                $decision = 'revise';
                $fix = 'Primary keyword already used by an existing post. Pick a non-colliding long-tail primary.';
            }

            $secondary = $keywords['secondary'] ?? [];
            if ($primary !== '' && (! is_array($secondary) || $secondary === [])) {
                $notes = 'No secondary keywords; draft should still weave related phrases.';
                $score = min($score, 72);
                // Soft note only — not a hard failure.
            }
        } elseif ($step === 'hooks') {
            $hooks = $session->hooks_json ?? [];
            if (! is_array($hooks) || $hooks === []) {
                $failures[] = 'no_hooks';
                $score = 15;
                $decision = 'revise';
                $fix = 'Generate at least 3 distinct BD seller hook titles with mixed angles.';
            } elseif (count($hooks) < 3) {
                $failures[] = 'few_hooks';
                $score = 55;
                $decision = 'revise';
                $fix = 'Generate at least 3 distinct hook titles.';
            } else {
                $score = max($passScore, min(92, 72 + (count($hooks) - 3) * 3));
            }
        } elseif ($step === 'outline') {
            $outline = $session->outline_json ?? [];
            $sections = $outline['sections'] ?? [];
            if (! is_array($sections) || count($sections) < 3) {
                $failures[] = 'thin_outline';
                $score = 45;
                $decision = 'revise';
                $fix = 'Expand outline to at least 4 H2 sections with practical BD seller bullets, H3 children on 2+ sections, a comparison or checklist section, and 5+ FAQs.';
            } else {
                $score = 75 + min(15, count($sections) * 2);
            }
            $faqs = $outline['faqs'] ?? [];
            $minFaqs = (int) config('blog_ai.seo_quality.min_faqs', 5);
            if (! is_array($faqs) || count($faqs) < $minFaqs) {
                $failures[] = 'few_faqs';
                $score = min($score, 62);
                $decision = 'revise';
                $fix = trim(($fix ? $fix.' ' : '')."Add at least {$minFaqs} FAQs with a_points.");
            }
            $links = $outline['internal_links'] ?? $session->link_plan_json ?? [];
            if (! is_array($links) || count($links) < 2) {
                $failures[] = 'weak_link_plan';
                $score = min($score, 60);
                $decision = 'revise';
                $fix = trim(($fix ? $fix.' ' : '').'Plan at least 2 internal links from the catalog.');
            }
            $mustPath = $this->clusterMustLinkPath($session);
            if ($mustPath !== null && is_array($links)) {
                $hasMust = collect($links)->contains(
                    fn ($row) => is_array($row) && ($row['path'] ?? null) === $mustPath
                );
                if (! $hasMust) {
                    $failures[] = 'missing_cluster_landing_link';
                    $score = min($score, 55);
                    $decision = 'revise';
                    $fix = trim(($fix ? $fix.' ' : '')
                        .'Include the cluster landing path '.$mustPath.' in internal_links (first link).');
                }
            }
        } elseif ($step === 'draft') {
            $draft = $session->draft_json ?? [];
            $quality = is_array($draft['quality'] ?? null) ? $draft['quality'] : [];
            if ($quality === [] && is_array($draft) && $draft !== []) {
                $quality = $this->seoQuality->analyze(
                    title: (string) ($draft['title'] ?? ''),
                    focusKeyword: (string) ($draft['focus_keyword'] ?? ''),
                    bodyHtml: (string) ($draft['body_html'] ?? ''),
                    metaDescription: (string) ($draft['meta_description'] ?? ''),
                    faqs: is_array($draft['faqs'] ?? null) ? $draft['faqs'] : [],
                    secondaryKeywords: is_array($session->keywords_json['secondary'] ?? null)
                        ? $session->keywords_json['secondary']
                        : [],
                    slug: (string) ($draft['slug'] ?? ''),
                    locale: (string) ($draft['locale'] ?? 'bn'),
                );
            }

            $score = $this->scorer->scoreFromSeoQuality($quality);
            $failures = array_values(array_filter(array_map('strval', $quality['failures'] ?? [])));
            $mustPath = $this->clusterMustLinkPath($session);
            $bodyHtml = (string) ($draft['body_html'] ?? '');
            if ($mustPath !== null && ! $this->bodyHasInternalPath($bodyHtml, $mustPath)) {
                $failures[] = 'missing_cluster_landing_link';
                $score = min($score, 55);
                $decision = 'revise';
                $fix = 'Add an internal link href="'.$mustPath.'" to the matching landing page. Stay on cluster_landing claims.';
            } elseif (! empty($quality['focus_keyword_collision']) || ! empty($quality['slug_collision'])) {
                // Prefer revise so Auto can pivot keyword / slug; only hard-abort if still colliding after rewrite attempts.
                $decision = 'revise';
                $fix = 'Focus keyword or slug still collides with a published post. Use a distinct long-tail focus keyword and unique latin slug.';
            } elseif (empty($quality['ai_ready'])) {
                $decision = 'revise';
                $fix = 'Fix SEO failures: '.implode(', ', $failures ?: ['ai_ready']).'. Strengthen keyword placement, FAQs, internal links, and body depth. Stay on landing-page product truth.';
            } else {
                $decision = 'advance';
                $score = max($score, 80);
                $failures = [];
            }
        } elseif ($step === 'image') {
            $image = $session->image_json ?? [];
            $reviewScore = (int) ($image['review']['score'] ?? ($context['image_score'] ?? 0));
            $autoApproved = ! empty($context['image_auto_approved']);
            $score = $reviewScore > 0 ? $reviewScore : ($session->status === 'image_ready' ? 80 : 50);
            if ($autoApproved || $session->status === 'image_needs_fix') {
                $score = min($score, 55);
                $notes = 'Cover image auto-approved after failed vision QA — replace before publish if weak.';
            }
            $decision = 'advance';
        }

        $pass = $decision === 'advance'
            && $score >= $passScore
            && $failures === [];

        if ($step === 'draft') {
            $hardDraftFails = array_intersect($failures, [
                'missing_cluster_landing_link',
                'focus_keyword_collision',
                'slug_collision',
            ]);
            if ($hardDraftFails !== []) {
                $pass = false;
                if ($decision === 'advance') {
                    $decision = 'revise';
                }
            } elseif (! empty($session->draft_json['quality']['ai_ready'])) {
                $pass = true;
                $decision = 'advance';
                $failures = [];
                $score = max($score, $passScore);
            } else {
                $pass = false;
                if ($decision === 'advance') {
                    $decision = 'revise';
                }
            }
        }

        // Image step: always advance (with possible score penalty); never block pipeline.
        if ($step === 'image') {
            $pass = true;
            $decision = 'advance';
            $failures = [];
        }

        return [
            'pass' => $pass,
            'score' => max(0, min(100, $score)),
            'decision' => $decision,
            'failures' => $failures,
            'fix_instructions' => $fix,
            'notes' => $notes,
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $rule
     * @return array{
     *     pass: bool,
     *     score: int,
     *     decision: string,
     *     failures: list<string>,
     *     fix_instructions: string|null,
     *     notes: string|null,
     *     usage: array<string, int>
     * }
     */
    private function llmReview(string $step, BlogAiSession $session, array $context, array $rule): array
    {
        $passScore = (int) config('blog_ai.auto.pass_score', 70);

        $system = <<<'TXT'
You are a strict Bangladesh SEO step reviewer for WooEasyLife blog AI.
Return JSON only:
{
  "pass": true,
  "score": 0-100,
  "decision": "advance|revise|abort",
  "failures": ["code_snake"],
  "fix_instructions": "actionable fix for the writer agent or null",
  "notes": "one short sentence"
}
Rules:
- Compete for strong BD SEO process quality (intent clarity, differentiation, practical seller value, E-E-A-T)
- Never invent WooEasyLife features; never claim guaranteed rankings
- Content must stay on-brief with cluster_landing (matching landing page H1/lead/claims)
- Outline/draft must link the cluster primary_path
- revise when content is thin, generic, cannibalizing, off-landing, or missing differentiation
- abort only for hard collisions or off-brand claims you cannot fix by rewrite
- Prefer revise with concrete fix_instructions over abort
TXT;

        $cluster = (string) ($session->cluster ?: 'general');
        $payload = [
            'step' => $step,
            'rule_baseline' => [
                'pass' => $rule['pass'],
                'score' => $rule['score'],
                'decision' => $rule['decision'],
                'failures' => $rule['failures'],
            ],
            'session' => [
                'cluster' => $session->cluster,
                'seed_topic' => $session->seed_topic,
                'keywords' => $session->keywords_json,
                'hooks' => $session->hooks_json,
                'selected_hook_ids' => $session->selected_hook_ids,
                'outline' => $session->outline_json,
                'draft_meta' => [
                    'title' => $session->draft_json['title'] ?? null,
                    'focus_keyword' => $session->draft_json['focus_keyword'] ?? null,
                    'meta_description' => $session->draft_json['meta_description'] ?? null,
                    'quality' => $session->draft_json['quality'] ?? null,
                    'faqs_count' => is_array($session->draft_json['faqs'] ?? null)
                        ? count($session->draft_json['faqs'])
                        : 0,
                    'body_excerpt' => Str::limit(strip_tags((string) ($session->draft_json['body_html'] ?? '')), 1200, ''),
                ],
                'image_review' => $session->image_json['review'] ?? null,
                'status' => $session->status,
            ],
            'context' => $context,
            'product_brief' => $this->briefBuilder->build($cluster),
            'cluster_landing' => $this->landingContext->forCluster($cluster),
            'pass_score' => $passScore,
        ];

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE)],
        ], 0.2);

        $data = $this->openAi->decodeJsonObject($result['content']);
        $decision = strtolower(trim((string) ($data['decision'] ?? 'revise')));
        if (! in_array($decision, ['advance', 'revise', 'abort'], true)) {
            $decision = $rule['decision'];
        }
        $score = max(0, min(100, (int) ($data['score'] ?? $rule['score'])));
        $failures = collect($data['failures'] ?? [])
            ->map(fn ($f) => Str::snake(trim((string) $f)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $pass = (bool) ($data['pass'] ?? false);
        if ($decision === 'advance' && $score < $passScore) {
            $pass = false;
            $decision = 'revise';
        }
        if ($decision !== 'advance') {
            $pass = false;
        }

        return [
            'pass' => $pass,
            'score' => $score,
            'decision' => $decision,
            'failures' => $failures,
            'fix_instructions' => filled($data['fix_instructions'] ?? null)
                ? Str::limit(trim((string) $data['fix_instructions']), 800, '')
                : $rule['fix_instructions'],
            'notes' => filled($data['notes'] ?? null)
                ? Str::limit(trim((string) $data['notes']), 240, '')
                : null,
            'usage' => $result['usage'],
        ];
    }

    private function clusterMustLinkPath(BlogAiSession $session): ?string
    {
        $cluster = trim((string) ($session->cluster ?: ''));
        if ($cluster === '') {
            return null;
        }

        $path = $this->landingContext->forCluster($cluster)['primary_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function bodyHasInternalPath(string $bodyHtml, string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        $escaped = preg_quote($path, '/');

        return (bool) preg_match('/href=["\']'.$escaped.'["\']/i', $bodyHtml);
    }
}
