<?php

namespace App\Services\BlogAi;

use App\Models\BlogCompetitorAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogCompetitorAnalyzer
{
    public function __construct(
        private OpenAiBlogClient $openAi,
        private BlogProductBriefBuilder $briefBuilder,
        private BlogLandingContextService $landingContext,
    ) {}

    /**
     * Fetch competitor pages for a target keyword and produce gap analysis for AI drafting.
     *
     * @param  list<string>  $urls
     * @return array{
     *     analysis: BlogCompetitorAnalysis,
     *     prompt_block: array<string, mixed>
     * }
     */
    public function analyze(
        string $keyword,
        array $urls,
        ?string $cluster = null,
        ?int $userId = null,
    ): array {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw ValidationException::withMessages([
                'keyword' => 'Enter a target keyword to analyze competitors.',
            ]);
        }

        $urls = $this->normalizeUrls($urls);
        if ($urls === []) {
            throw ValidationException::withMessages([
                'urls' => 'Paste 1–5 competitor blog/article URLs (https).',
            ]);
        }

        $maxUrls = (int) config('blog_ai.competitors.max_urls', 5);
        $urls = array_slice($urls, 0, max(1, $maxUrls));

        $snapshots = [];
        foreach ($urls as $url) {
            $snapshots[] = $this->fetchSnapshot($url);
        }

        $usable = collect($snapshots)->filter(fn (array $s) => ($s['ok'] ?? false) === true)->values()->all();
        if ($usable === []) {
            throw ValidationException::withMessages([
                'urls' => 'Could not fetch any competitor pages. Check the URLs are public https articles.',
            ]);
        }

        $cluster = filled($cluster) ? (string) $cluster : null;
        $brief = $this->briefBuilder->build($cluster);

        $system = <<<'TXT'
You are a Bangladesh SEO competitor analyst for WooEasyLife (WooCommerce COD seller SaaS).
Compare competitor articles for the target keyword and return JSON only:
{
  "summary_bn": "2-3 Bangla sentences: what competitors cover and how we can beat them",
  "beat_score": 0-100,
  "competitor_strengths": ["..."],
  "competitor_weaknesses": ["..."],
  "content_gaps": ["topics/angles they miss that BD COD sellers need"],
  "must_cover_angles": ["angles our post must include to outrank"],
  "title_angles": ["3-5 Bangla/hybrid title angles better than theirs"],
  "faq_gaps": ["FAQ questions they omit"],
  "differentiation": ["WooEasyLife-specific proof/tools to mention"],
  "writing_guidance": ["short rules for the next draft"]
}
Rules:
- Ground claims in the provided page snapshots (titles, headings, excerpts). Do not invent competitor brand partnerships.
- Prefer practical BD COD / courier / fraud / return-loss angles over generic US ecom advice.
- Stay inside WooEasyLife product truth from product_brief.
- beat_score = how beatable they look for this keyword (higher = easier to outrank with a better post).
TXT;

        $user = json_encode([
            'keyword' => $keyword,
            'cluster' => $cluster,
            'cluster_landing' => $cluster ? $this->landingContext->forCluster($cluster) : null,
            'product_brief' => $brief,
            'competitor_snapshots' => $snapshots,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.35);

        $insight = $this->openAi->decodeJsonObject($result['content']);
        $summaryBn = trim((string) ($insight['summary_bn'] ?? ''));
        $beatScore = isset($insight['beat_score']) ? (int) $insight['beat_score'] : null;
        if ($beatScore !== null) {
            $beatScore = max(0, min(100, $beatScore));
        }

        $analysis = BlogCompetitorAnalysis::query()->create([
            'user_id' => $userId,
            'keyword' => $keyword,
            'cluster' => $cluster,
            'competitor_urls' => $urls,
            'snapshots_json' => $snapshots,
            'insight_json' => $insight,
            'summary_bn' => $summaryBn !== '' ? $summaryBn : null,
            'beat_score' => $beatScore,
            'prompt_tokens' => (int) ($result['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($result['usage']['completion_tokens'] ?? 0),
        ]);

        try {
            app(BlogMemoryService::class)->absorbFromCompetitor($analysis);
        } catch (\Throwable) {
            // best-effort
        }

        return [
            'analysis' => $analysis,
            'prompt_block' => $this->toPromptBlock($analysis),
        ];
    }

    /**
     * Latest competitor insight for a keyword (for AI prompts).
     *
     * @return array<string, mixed>|null
     */
    public function promptBlockForKeyword(?string $keyword): ?array
    {
        if (! filled($keyword)) {
            return null;
        }

        $row = BlogCompetitorAnalysis::latestForKeyword((string) $keyword);
        if (! $row) {
            return null;
        }

        $maxAgeDays = (int) config('blog_ai.competitors.max_age_days', 30);
        if ($row->created_at && $row->created_at->lt(now()->subDays(max(1, $maxAgeDays)))) {
            return null;
        }

        return $this->toPromptBlock($row);
    }

    /**
     * Recent analyses for admin UI.
     *
     * @return list<array<string, mixed>>
     */
    public function recentForAdmin(int $limit = 8): array
    {
        return BlogCompetitorAnalysis::query()
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (BlogCompetitorAnalysis $row) => $this->toAdminRow($row))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminRow(BlogCompetitorAnalysis $row): array
    {
        $insight = is_array($row->insight_json) ? $row->insight_json : [];

        return [
            'id' => $row->id,
            'keyword' => $row->keyword,
            'cluster' => $row->cluster,
            'competitor_urls' => $row->competitor_urls ?? [],
            'summary_bn' => $row->summary_bn,
            'beat_score' => $row->beat_score,
            'content_gaps' => array_slice($insight['content_gaps'] ?? [], 0, 6),
            'must_cover_angles' => array_slice($insight['must_cover_angles'] ?? [], 0, 6),
            'title_angles' => array_slice($insight['title_angles'] ?? [], 0, 5),
            'writing_guidance' => array_slice($insight['writing_guidance'] ?? [], 0, 5),
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPromptBlock(BlogCompetitorAnalysis $row): array
    {
        $insight = is_array($row->insight_json) ? $row->insight_json : [];
        $snapshots = is_array($row->snapshots_json) ? $row->snapshots_json : [];

        $diffChecklist = array_values(array_unique(array_filter([
            ...array_slice($insight['must_cover_angles'] ?? [], 0, 6),
            ...array_slice($insight['content_gaps'] ?? [], 0, 6),
            ...array_slice($insight['faq_gaps'] ?? [], 0, 4),
        ])));

        $avgWords = collect($snapshots)
            ->filter(fn ($s) => is_array($s) && ($s['ok'] ?? false))
            ->avg('word_count');

        return [
            'status' => 'ready',
            'keyword' => $row->keyword,
            'cluster' => $row->cluster,
            'analyzed_at' => optional($row->created_at)?->toIso8601String(),
            'beat_score' => $row->beat_score,
            'summary_bn' => $row->summary_bn,
            'competitor_urls' => $row->competitor_urls ?? [],
            'competitor_strengths' => array_slice($insight['competitor_strengths'] ?? [], 0, 6),
            'competitor_weaknesses' => array_slice($insight['competitor_weaknesses'] ?? [], 0, 6),
            'content_gaps' => array_slice($insight['content_gaps'] ?? [], 0, 8),
            'must_cover_angles' => array_slice($insight['must_cover_angles'] ?? [], 0, 8),
            'title_angles' => array_slice($insight['title_angles'] ?? [], 0, 5),
            'faq_gaps' => array_slice($insight['faq_gaps'] ?? [], 0, 6),
            'differentiation' => array_slice($insight['differentiation'] ?? [], 0, 6),
            'writing_guidance' => array_slice($insight['writing_guidance'] ?? [], 0, 6),
            'diff_checklist' => $diffChecklist,
            'competitor_avg_word_count' => $avgWords !== null ? (int) round($avgWords) : null,
            'beat_rules' => [
                'Cover every diff_checklist item explicitly (H2 or FAQ).',
                'Match or exceed competitor_avg_word_count with practical BD COD depth when set.',
                'Use title_angles that beat competitor CTR without clickbait lies.',
            ],
        ];
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function normalizeUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $raw) {
            $url = trim((string) $raw);
            if ($url === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://'.$url;
            }
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $host = parse_url($url, PHP_URL_HOST);
            if (! is_string($host) || $host === '') {
                continue;
            }
            $out[] = $url;
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{
     *     url: string,
     *     ok: bool,
     *     status?: int|null,
     *     title?: string|null,
     *     meta_description?: string|null,
     *     h1?: string|null,
     *     headings?: list<string>,
     *     word_count?: int,
     *     excerpt?: string|null,
     *     error?: string|null
     * }
     */
    private function fetchSnapshot(string $url): array
    {
        $timeout = (int) config('blog_ai.competitors.fetch_timeout', 12);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(8)
                ->withHeaders([
                    'User-Agent' => 'WooEasyLifeBlogBot/1.0 (+competitor-analyzer)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (! $response->successful()) {
                return [
                    'url' => $url,
                    'ok' => false,
                    'status' => $response->status(),
                    'error' => 'HTTP '.$response->status(),
                ];
            }

            $html = (string) $response->body();
            $maxBytes = (int) config('blog_ai.competitors.max_html_bytes', 500_000);
            if (strlen($html) > $maxBytes) {
                $html = substr($html, 0, $maxBytes);
            }

            return $this->parseHtmlSnapshot($url, $html, $response->status());
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'ok' => false,
                'error' => Str::limit($e->getMessage(), 160),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHtmlSnapshot(string $url, string $html, int $status): array
    {
        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = $this->cleanText($m[1]);
        }

        $meta = null;
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $m)) {
            $meta = $this->cleanText($m[1]);
        } elseif (preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']description["\']/is', $html, $m)) {
            $meta = $this->cleanText($m[1]);
        }

        $h1 = null;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $h1 = $this->cleanText(strip_tags($m[1]));
        }

        $headings = [];
        if (preg_match_all('/<h([2-3])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $text = $this->cleanText(strip_tags($match[2]));
                if ($text !== '') {
                    $headings[] = $text;
                }
                if (count($headings) >= 20) {
                    break;
                }
            }
        }

        $plain = $this->htmlToPlain($html);
        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $excerpt = Str::limit($plain, 1200, '…');

        return [
            'url' => $url,
            'ok' => true,
            'status' => $status,
            'title' => $title,
            'meta_description' => $meta,
            'h1' => $h1,
            'headings' => $headings,
            'word_count' => count($words),
            'excerpt' => $excerpt !== '' ? $excerpt : null,
        ];
    }

    private function htmlToPlain(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|svg|iframe)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<!--.*?-->/s', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->cleanText($text);
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
