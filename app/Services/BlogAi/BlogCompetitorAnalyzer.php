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
        private BlogCompetitorDiscoveryService $discovery,
        private BlogCompetitorGapService $gaps,
    ) {}

    /**
     * Fetch competitor pages for a target keyword and produce gap analysis for AI drafting.
     * Empty URLs trigger auto-discovery when enabled.
     *
     * @param  list<string>  $urls
     * @return array{
     *     analysis: BlogCompetitorAnalysis,
     *     prompt_block: array<string, mixed>
     * }
     */
    public function analyze(
        string $keyword,
        array $urls = [],
        ?string $cluster = null,
        ?int $userId = null,
        bool $allowDiscover = true,
    ): array {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw ValidationException::withMessages([
                'keyword' => 'Enter a target keyword to analyze competitors.',
            ]);
        }

        $urls = $this->normalizeUrls($urls);
        $discoveryMeta = null;

        if ($urls === [] && $allowDiscover && config('blog_ai.competitors.discovery.enabled', true)) {
            $found = $this->discovery->discover($keyword);
            $discoveryMeta = [
                'provider' => $found[0]['provider'] ?? (string) config('blog_ai.competitors.discovery.provider', 'auto'),
                'results' => $found,
            ];
            $urls = array_values(array_map(fn (array $row) => $row['url'], $found));
        }

        if ($urls === []) {
            throw ValidationException::withMessages([
                'urls' => 'Paste competitor URLs or use Find rivals so we have pages to analyze.',
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
        $ourPost = $this->gaps->findOurPost($keyword, $cluster);
        $ourSnapshot = $this->gaps->ourSnapshot($ourPost);

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
  "writing_guidance": ["short rules for the next draft"],
  "gap_checklist": [
    {
      "id": "g1",
      "gap": "short concrete angle to cover",
      "why": "why this beats rivals",
      "status": "open|covered|partial",
      "evidence": "cite our_snapshot heading/FAQ if covered/partial, else null"
    }
  ]
}
Rules:
- Ground claims in the provided page snapshots (titles, headings, excerpts, FAQs). Do not invent competitor brand partnerships.
- Prefer practical BD COD / courier / fraud / return-loss angles over generic US ecom advice.
- Stay inside WooEasyLife product truth from product_brief.
- beat_score = how beatable they look for this keyword (higher = easier to outrank with a better post).
- Build gap_checklist (6–12 items) from content_gaps + must_cover_angles + faq_gaps.
- When our_snapshot is present, mark status covered/partial only if our headings/FAQs/excerpt clearly address the gap; otherwise open.
- When our_snapshot is null, mark all gap_checklist status as open.
TXT;

        $user = json_encode([
            'keyword' => $keyword,
            'cluster' => $cluster,
            'cluster_landing' => $cluster ? $this->landingContext->forCluster($cluster) : null,
            'product_brief' => $brief,
            'our_snapshot' => $ourSnapshot,
            'competitor_snapshots' => $snapshots,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJsonPlanning([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $user],
        ], 0.35);

        $insight = $this->openAi->decodeJsonObject($result['content']);
        $insight['gap_checklist'] = $this->gaps->normalizeGapChecklist($insight['gap_checklist'] ?? null);

        // If model omitted checklist, synthesize from classic gap lists.
        if ($insight['gap_checklist'] === []) {
            $fallback = array_values(array_unique(array_filter([
                ...array_slice($insight['must_cover_angles'] ?? [], 0, 6),
                ...array_slice($insight['content_gaps'] ?? [], 0, 6),
                ...array_slice($insight['faq_gaps'] ?? [], 0, 4),
            ])));
            $insight['gap_checklist'] = $this->gaps->normalizeGapChecklist($fallback);
        }

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
            'discovery_json' => $discoveryMeta,
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
     * Auto-discover + analyze when no fresh analysis exists (Smart Post path).
     *
     * @return array{analysis: BlogCompetitorAnalysis, prompt_block: array<string, mixed>}|null
     */
    public function ensureAnalysisForKeyword(
        string $keyword,
        ?string $cluster = null,
        ?int $userId = null,
    ): ?array {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return null;
        }

        if ($this->promptBlockForKeyword($keyword) !== null) {
            return null;
        }

        if (! config('blog_ai.competitors.enabled', true)
            || ! config('blog_ai.competitors.discovery.enabled', true)
            || ! config('blog_ai.competitors.discovery.auto_on_smart_post', true)) {
            return null;
        }

        try {
            return $this->analyze(
                keyword: $keyword,
                urls: [],
                cluster: $cluster,
                userId: $userId,
                allowDiscover: true,
            );
        } catch (\Throwable) {
            return null;
        }
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
        $checklist = $this->gaps->normalizeGapChecklist($insight['gap_checklist'] ?? []);
        $openCount = collect($checklist)->where('status', '!=', 'covered')->count();

        return [
            'id' => $row->id,
            'keyword' => $row->keyword,
            'cluster' => $row->cluster,
            'competitor_urls' => $row->competitor_urls ?? [],
            'discovered' => is_array($row->discovery_json),
            'summary_bn' => $row->summary_bn,
            'beat_score' => $row->beat_score,
            'content_gaps' => array_slice($insight['content_gaps'] ?? [], 0, 6),
            'must_cover_angles' => array_slice($insight['must_cover_angles'] ?? [], 0, 6),
            'title_angles' => array_slice($insight['title_angles'] ?? [], 0, 5),
            'writing_guidance' => array_slice($insight['writing_guidance'] ?? [], 0, 5),
            'gap_checklist' => $checklist,
            'open_gaps' => $openCount,
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
        $checklist = $this->gaps->normalizeGapChecklist($insight['gap_checklist'] ?? []);
        $openGaps = $this->gaps->openGapTexts($checklist);

        $diffChecklist = $openGaps !== []
            ? array_slice($openGaps, 0, 12)
            : array_values(array_unique(array_filter([
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
            'gap_checklist' => $checklist,
            'diff_checklist' => $diffChecklist,
            'competitor_avg_word_count' => $avgWords !== null ? (int) round($avgWords) : null,
            'beat_rules' => [
                'Cover every open diff_checklist / gap_checklist item explicitly (H2 or FAQ).',
                'Match or exceed competitor_avg_word_count with practical BD COD depth when set.',
                'Use title_angles that beat competitor CTR without clickbait lies.',
                'Prefer open gaps over already-covered angles from our_snapshot.',
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
            if (! $this->isSafePublicHttpUrl($url)) {
                continue;
            }
            $out[] = $url;
        }

        return array_values(array_unique($out));
    }

    /**
     * Block localhost / private / reserved targets (SSRF).
     */
    public function isSafePublicHttpUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (preg_match('/\.(local|internal|lan|home|corp)$/i', $host)) {
            return false;
        }

        // Literal IP host
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        $ips = @gethostbynamel($host) ?: [];
        if ($ips === []) {
            // IPv6-only hosts: try dns_get_record if available.
            if (function_exists('dns_get_record')) {
                $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
                foreach ($aaaa as $row) {
                    if (! empty($row['ipv6'])) {
                        $ips[] = $row['ipv6'];
                    }
                }
            }
        }

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSnapshot(string $url): array
    {
        $timeout = (int) config('blog_ai.competitors.fetch_timeout', 12);
        $current = $url;
        $maxRedirects = 5;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            if (! $this->isSafePublicHttpUrl($current)) {
                return [
                    'url' => $url,
                    'ok' => false,
                    'error' => 'Blocked unsafe URL target',
                ];
            }

            try {
                // Follow redirects manually so each hop is re-checked for SSRF.
                $response = Http::timeout($timeout)
                    ->connectTimeout(8)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders([
                        'User-Agent' => 'WooEasyLifeBlogBot/1.0 (+competitor-analyzer)',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->get($current);

                if ($response->status() >= 300 && $response->status() < 400) {
                    $location = trim((string) $response->header('Location'));
                    if ($location === '' || $hop >= $maxRedirects) {
                        return [
                            'url' => $url,
                            'ok' => false,
                            'status' => $response->status(),
                            'error' => 'Redirect could not be followed safely',
                        ];
                    }
                    $next = $this->resolveRedirectUrl($current, $location);
                    if ($next === null || ! $this->isSafePublicHttpUrl($next)) {
                        return [
                            'url' => $url,
                            'ok' => false,
                            'status' => $response->status(),
                            'error' => 'Redirect blocked for SSRF safety',
                        ];
                    }
                    $current = $next;
                    continue;
                }

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

                return $this->parseHtmlSnapshot($current, $html, $response->status());
            } catch (\Throwable $e) {
                return [
                    'url' => $url,
                    'ok' => false,
                    'error' => Str::limit($e->getMessage(), 160),
                ];
            }
        }

        return [
            'url' => $url,
            'ok' => false,
            'error' => 'Too many redirects',
        ];
    }

    private function resolveRedirectUrl(string $fromUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($fromUrl);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $basePath = $parts['path'] ?? '/';
        $dir = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';

        return $origin.$dir.$location;
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
        if (preg_match_all('/<h([2-4])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $text = $this->cleanText(strip_tags($match[2]));
                if ($text !== '') {
                    $headings[] = 'H'.$match[1].': '.$text;
                }
                if (count($headings) >= 40) {
                    break;
                }
            }
        }

        $faqs = $this->extractFaqQuestions($html);
        $listItemCount = preg_match_all('/<li\b/i', $html) ?: 0;
        $host = parse_url($url, PHP_URL_HOST);
        $outbound = 0;
        if (preg_match_all('/<a\b[^>]*\bhref=["\'](https?:\/\/[^"\']+)["\']/i', $html, $linkMatches)) {
            foreach ($linkMatches[1] as $href) {
                $linkHost = parse_url($href, PHP_URL_HOST);
                if (is_string($linkHost) && is_string($host) && strcasecmp($linkHost, $host) !== 0) {
                    $outbound++;
                }
                if ($outbound >= 40) {
                    break;
                }
            }
        }

        $plain = $this->htmlToPlain($html);
        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $excerptLimit = (int) config('blog_ai.competitors.excerpt_chars', 3500);
        $excerpt = Str::limit($plain, max(1200, $excerptLimit), '…');

        return [
            'url' => $url,
            'ok' => true,
            'status' => $status,
            'title' => $title,
            'meta_description' => $meta,
            'h1' => $h1,
            'headings' => $headings,
            'faqs' => $faqs,
            'list_item_count' => $listItemCount,
            'outbound_link_count' => $outbound,
            'word_count' => count($words),
            'excerpt' => $excerpt !== '' ? $excerpt : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractFaqQuestions(string $html): array
    {
        $questions = [];

        if (preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $blocks
        )) {
            foreach ($blocks[1] as $jsonRaw) {
                $decoded = json_decode(html_entity_decode(trim($jsonRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
                if (! is_array($decoded)) {
                    continue;
                }
                $nodes = isset($decoded[0]) ? $decoded : [$decoded];
                foreach ($nodes as $node) {
                    if (! is_array($node)) {
                        continue;
                    }
                    $type = $node['@type'] ?? null;
                    $types = is_array($type) ? $type : [$type];
                    if (! in_array('FAQPage', $types, true)) {
                        continue;
                    }
                    $entities = $node['mainEntity'] ?? [];
                    if (! is_array($entities)) {
                        continue;
                    }
                    foreach ($entities as $entity) {
                        if (! is_array($entity)) {
                            continue;
                        }
                        $q = $this->cleanText((string) ($entity['name'] ?? $entity['question'] ?? ''));
                        if ($q !== '') {
                            $questions[] = $q;
                        }
                        if (count($questions) >= 12) {
                            return $questions;
                        }
                    }
                }
            }
        }

        return $questions;
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
