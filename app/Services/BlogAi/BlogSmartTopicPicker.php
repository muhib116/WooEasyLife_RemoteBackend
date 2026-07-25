<?php

namespace App\Services\BlogAi;

use App\Models\BlogGscQueryMetric;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Schema;

/**
 * Scores GSC / learning candidates for Smart One-Click (new vs refresh).
 */
class BlogSmartTopicPicker
{
    public function __construct(
        private BlogLearningService $learning,
        private BlogLandingContextService $landingContext,
        private BlogCompetitorAnalyzer $competitors,
    ) {}

    /**
     * @param  array<string, mixed>  $learning
     * @param  array<string, mixed>  $input
     * @return array{
     *     cluster: string,
     *     seed_topic: string,
     *     keyword: string,
     *     reason: string,
     *     competitor_ready: bool,
     *     action: string,
     *     target_slug: string|null,
     *     target_post_id: int|null,
     *     bucket: string|null,
     *     opportunity_score: float,
     * }
     */
    public function pick(?string $explicitCluster, ?string $explicitSeed, array $learning, array $input = []): array
    {
        $explicitCluster = trim((string) $explicitCluster);
        $explicitSeed = trim((string) $explicitSeed);

        if ($explicitSeed !== '') {
            return $this->finalizeCandidate([
                'seed_topic' => $explicitSeed,
                'keyword' => $explicitSeed,
                'cluster' => $explicitCluster,
                'reason' => 'explicit_seed',
                'bucket' => is_string($input['bucket'] ?? null) ? $input['bucket'] : null,
                'opportunity_score' => (float) ($input['opportunity_score'] ?? 0),
                'target_slug' => filled($input['target_slug'] ?? null) ? (string) $input['target_slug'] : null,
                'forced_action' => filled($input['action'] ?? null) ? (string) $input['action'] : null,
            ], $learning);
        }

        $candidates = [];

        // Rank opportunities (full GSC admin payload) — highest fidelity.
        $rankItems = $this->learning->rankOpportunitiesForAdmin(40);
        foreach ($rankItems['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $query = trim((string) ($item['query'] ?? ''));
            if ($query === '') {
                continue;
            }
            $candidates[] = [
                'seed_topic' => $query,
                'keyword' => $query,
                'cluster' => '',
                'reason' => 'gsc_rank_'.($item['bucket'] ?? 'opportunity'),
                'bucket' => (string) ($item['bucket'] ?? ''),
                'opportunity_score' => (float) ($item['opportunity_score'] ?? 0),
                'target_slug' => filled($item['slug'] ?? null) ? (string) $item['slug'] : null,
                'impressions' => (int) ($item['impressions_28d'] ?? 0),
                'source' => 'gsc',
            ];
        }

        foreach ($this->learning->gscKeywordSeeds(20) as $row) {
            $query = trim((string) ($row['query'] ?? ''));
            if ($query === '') {
                continue;
            }
            $candidates[] = [
                'seed_topic' => $query,
                'keyword' => $query,
                'cluster' => '',
                'reason' => 'gsc_seed_'.($row['bucket'] ?? 'opportunity'),
                'bucket' => (string) ($row['bucket'] ?? ''),
                'opportunity_score' => (float) ($row['opportunity_score'] ?? $this->estimateScore($row)),
                'target_slug' => filled($row['slug'] ?? null) ? (string) $row['slug'] : null,
                'impressions' => (int) ($row['impressions'] ?? 0),
                'source' => 'gsc',
            ];
        }

        foreach ($learning['next_post_ideas'] ?? [] as $idea) {
            if (! is_array($idea)) {
                continue;
            }
            $seed = trim((string) ($idea['seed_topic'] ?? $idea['suggested_title'] ?? ''));
            if ($seed === '') {
                continue;
            }
            $isGscIdea = str_starts_with((string) ($idea['reason'] ?? ''), 'gsc_');
            $candidates[] = [
                'seed_topic' => $seed,
                'keyword' => $seed,
                'cluster' => (string) ($idea['cluster'] ?? ''),
                'reason' => (string) ($idea['reason'] ?? 'learning_next_idea'),
                'bucket' => $isGscIdea
                    ? substr((string) $idea['reason'], 4)
                    : null,
                'opportunity_score' => $isGscIdea ? 35.0 : 12.0,
                'target_slug' => null,
                'impressions' => 0,
                'source' => $isGscIdea ? 'gsc' : 'learning',
            ];
        }

        // Curated SEO inventory planned blogs (long-tail roadmap) — below GSC, above cold cluster seeds.
        try {
            $inventoryTopics = app(\App\Services\Seo\SeoKeywordInventory::class)
                ->plannedBlogTopics($explicitCluster !== '' ? $explicitCluster : null, 12);
            foreach ($inventoryTopics as $topic) {
                $candidates[] = [
                    'seed_topic' => $topic['seed_topic'],
                    'keyword' => $topic['keyword'],
                    'cluster' => $topic['cluster'],
                    'reason' => 'seo_keyword_inventory',
                    'bucket' => null,
                    'opportunity_score' => 18.0,
                    'target_slug' => $topic['target_slug'],
                    'impressions' => 0,
                    'source' => 'inventory',
                ];
            }
        } catch (\Throwable) {
            // ignore
        }

        $preferGsc = (bool) config('blog_ai.auto.prefer_gsc', true);
        $hasGsc = collect($candidates)->contains(fn (array $c) => ($c['source'] ?? '') === 'gsc');

        $best = null;
        $bestScore = -1.0;
        $recentAngles = $this->recentAnglesByCluster();

        foreach ($candidates as $candidate) {
            if ($preferGsc && $hasGsc && ($candidate['source'] ?? '') !== 'gsc') {
                // Free real demand first: skip cluster/learning ideas when GSC has anything.
                continue;
            }

            $resolved = $this->finalizeCandidate($candidate, $learning, $explicitCluster !== '' ? $explicitCluster : null, scoreOnly: true);
            if ($resolved === null) {
                continue;
            }

            if ($resolved['action'] !== 'refresh' && $this->isRecentDuplicateAngle($resolved, $recentAngles)) {
                // Soft-skip near-duplicate *new* angles from the last 30 days (same cluster).
                // Refresh of an existing post is intentional and must not be filtered out.
                continue;
            }

            $score = $this->scoreCandidate($candidate, $resolved);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $resolved;
                $best['opportunity_score'] = round($score, 2);
                $best['source'] = (string) ($candidate['source'] ?? 'unknown');
            }
        }

        // If everything collided with recent angles, fall through without the filter.
        if ($best === null) {
            foreach ($candidates as $candidate) {
                $resolved = $this->finalizeCandidate($candidate, $learning, $explicitCluster !== '' ? $explicitCluster : null, scoreOnly: true);
                if ($resolved === null) {
                    continue;
                }
                $score = $this->scoreCandidate($candidate, $resolved);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $resolved;
                    $best['opportunity_score'] = round($score, 2);
                    $best['source'] = (string) ($candidate['source'] ?? 'unknown');
                    $best['reason'] = ($best['reason'] ?? 'scored').'_recent_fallback';
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        $cluster = $explicitCluster !== '' ? $explicitCluster : 'fake_order';
        $catalog = app(BlogClusterCatalog::class);
        try {
            $inventoryFirst = app(\App\Services\Seo\SeoKeywordInventory::class)->plannedBlogTopics($cluster, 1);
        } catch (\Throwable) {
            $inventoryFirst = [];
        }
        if ($inventoryFirst !== []) {
            return $this->finalizeCandidate([
                'seed_topic' => $inventoryFirst[0]['seed_topic'],
                'keyword' => $inventoryFirst[0]['keyword'],
                'cluster' => $inventoryFirst[0]['cluster'] ?: $cluster,
                'reason' => 'cold_start_inventory',
                'bucket' => null,
                'opportunity_score' => 2,
                'target_slug' => $inventoryFirst[0]['target_slug'],
            ], $learning);
        }
        $seeds = $catalog->seedQueries($cluster);
        $fallback = $seeds !== []
            ? (string) $seeds[0]
            : $catalog->label($cluster);

        return $this->finalizeCandidate([
            'seed_topic' => $fallback,
            'keyword' => $fallback,
            'cluster' => $cluster,
            'reason' => 'cold_start_fallback',
            'bucket' => null,
            'opportunity_score' => 1,
            'target_slug' => null,
        ], $learning);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $learning
     * @return array<string, mixed>|null
     */
    private function finalizeCandidate(
        array $candidate,
        array $learning,
        ?string $forceCluster = null,
        bool $scoreOnly = false,
    ): ?array {
        $keyword = trim((string) ($candidate['keyword'] ?? $candidate['seed_topic'] ?? ''));
        $seed = trim((string) ($candidate['seed_topic'] ?? $keyword));
        if ($keyword === '' || $seed === '') {
            return null;
        }

        $cluster = trim((string) ($forceCluster ?: ($candidate['cluster'] ?? '')));
        if ($cluster === '') {
            $cluster = (string) ($this->landingContext->resolveCluster(null, $seed, [$keyword], $learning)['cluster'] ?? 'fake_order');
        }

        $collision = $this->findFocusCollision($keyword);
        $bucket = $candidate['bucket'] ?? null;
        $slug = filled($candidate['target_slug'] ?? null)
            ? (string) $candidate['target_slug']
            : ($collision['slug'] ?? null);
        $postId = $collision['id'] ?? ($slug ? $this->postIdForSlug($slug) : null);

        $forced = $candidate['forced_action'] ?? null;
        $action = is_string($forced) && in_array($forced, ['new', 'refresh'], true)
            ? $forced
            : $this->decideAction($bucket, $slug, $postId);

        // Skip "new" when an exact focus keyword already exists — prefer refresh or drop.
        if ($action === 'new' && $collision !== null) {
            if ($collision['slug']) {
                $action = 'refresh';
                $slug = $collision['slug'];
                $postId = $collision['id'];
            } elseif ($scoreOnly) {
                return null;
            }
        }

        if ($action === 'refresh' && ! $postId && ! $slug) {
            $action = 'new';
        }

        return [
            'cluster' => $cluster,
            'seed_topic' => $seed,
            'keyword' => $keyword,
            'reason' => (string) ($candidate['reason'] ?? 'scored'),
            'competitor_ready' => $this->competitors->promptBlockForKeyword($keyword) !== null,
            'action' => $action,
            'target_slug' => $slug,
            'target_post_id' => $postId,
            'bucket' => is_string($bucket) ? $bucket : null,
            'opportunity_score' => (float) ($candidate['opportunity_score'] ?? 0),
            'source' => (string) ($candidate['source'] ?? 'unknown'),
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $resolved
     */
    private function scoreCandidate(array $candidate, array $resolved): float
    {
        $score = (float) ($resolved['opportunity_score'] ?? 0);
        if ($resolved['competitor_ready'] ?? false) {
            $score += 12;
        }
        if (($resolved['action'] ?? '') === 'refresh') {
            $score += 8;
        }
        $score += $this->bucketBoost($resolved['bucket'] ?? null);
        // Real GSC demand: impressions matter more than cluster seed guesses.
        $score += min(25, ((int) ($candidate['impressions'] ?? 0)) / 30);
        if (($candidate['source'] ?? '') === 'gsc') {
            $score += 40;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function estimateScore(array $row): float
    {
        $impressions = (int) ($row['impressions'] ?? 0);
        $clicks = (int) ($row['clicks'] ?? 0);
        $position = isset($row['position']) ? (float) $row['position'] : 50.0;

        return round(($impressions * 0.1) + ($clicks * 2) + max(0, 40 - $position), 2);
    }

    private function bucketBoost(?string $bucket): float
    {
        return match ($bucket) {
            BlogGscQueryMetric::BUCKET_STRIKING => 40,
            BlogGscQueryMetric::BUCKET_FIX_CTR => 45,
            BlogGscQueryMetric::BUCKET_DEFEND => 28,
            BlogGscQueryMetric::BUCKET_BURIED => 18,
            BlogGscQueryMetric::BUCKET_CANNIBALIZED => 10,
            default => 0,
        };
    }

    private function decideAction(?string $bucket, ?string $slug, ?int $postId): string
    {
        $hasPost = $postId || filled($slug);
        if ($hasPost && in_array($bucket, [
            BlogGscQueryMetric::BUCKET_FIX_CTR,
            BlogGscQueryMetric::BUCKET_DEFEND,
            BlogGscQueryMetric::BUCKET_CANNIBALIZED,
        ], true)) {
            return 'refresh';
        }

        return 'new';
    }

    /**
     * @return array{id: int, slug: string, focus_keyword: string}|null
     */
    private function findFocusCollision(string $keyword): ?array
    {
        if (! Schema::hasTable('blog_posts')) {
            return null;
        }

        $key = mb_strtolower(trim($keyword));
        if ($key === '') {
            return null;
        }

        $post = BlogPost::query()
            ->whereNotNull('focus_keyword')
            ->whereRaw('LOWER(focus_keyword) = ?', [$key])
            ->orderByDesc('id')
            ->first(['id', 'slug', 'focus_keyword']);

        if (! $post) {
            return null;
        }

        return [
            'id' => (int) $post->id,
            'slug' => (string) $post->slug,
            'focus_keyword' => (string) $post->focus_keyword,
        ];
    }

    private function postIdForSlug(string $slug): ?int
    {
        if (! Schema::hasTable('blog_posts') || $slug === '') {
            return null;
        }

        $id = BlogPost::query()->where('slug', $slug)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @return array<string, list<string>> cluster => normalized seeds/titles/focus
     */
    private function recentAnglesByCluster(): array
    {
        if (! Schema::hasTable('blog_posts')) {
            return [];
        }

        $rows = BlogPost::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['cluster', 'title', 'focus_keyword', 'slug']);

        $out = [];
        foreach ($rows as $row) {
            $cluster = trim((string) ($row->cluster ?? '')) ?: 'general';
            foreach ([$row->focus_keyword, $row->title, $row->slug] as $raw) {
                $n = $this->normalizeAngle((string) $raw);
                if ($n !== '') {
                    $out[$cluster][] = $n;
                }
            }
        }

        foreach ($out as $cluster => $list) {
            $out[$cluster] = array_values(array_unique($list));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @param  array<string, list<string>>  $recentAngles
     */
    private function isRecentDuplicateAngle(array $resolved, array $recentAngles): bool
    {
        $cluster = trim((string) ($resolved['cluster'] ?? '')) ?: 'general';
        $pool = $recentAngles[$cluster] ?? [];
        if ($pool === []) {
            return false;
        }

        $needles = array_filter([
            $this->normalizeAngle((string) ($resolved['keyword'] ?? '')),
            $this->normalizeAngle((string) ($resolved['seed_topic'] ?? '')),
        ]);

        foreach ($needles as $needle) {
            foreach ($pool as $existing) {
                if ($needle === $existing) {
                    return true;
                }
                // Near-duplicate: one contains the other and both reasonably long.
                if (mb_strlen($needle) >= 8 && mb_strlen($existing) >= 8
                    && (str_contains($existing, $needle) || str_contains($needle, $existing))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeAngle(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
