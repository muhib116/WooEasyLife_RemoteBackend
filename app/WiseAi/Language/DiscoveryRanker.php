<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseLanguageReview;
use Illuminate\Support\Facades\Cache;

/**
 * Rank discovery candidates by frequency × non-sandbox merchant breadth.
 * Cross-key refresh is queued — never run full refresh on the decide hot path.
 */
class DiscoveryRanker
{
    private const DIRTY_CACHE_KEY = 'wise_discovery_dirty_tokens';

    public function score(int $hitCount, int $keyBreadth, ?\DateTimeInterface $lastSeen = null): float
    {
        $hits = max(1, $hitCount);
        $breadth = max(1, $keyBreadth);
        $recency = 1.0;
        if ($lastSeen) {
            $days = max(0, now()->diffInDays($lastSeen));
            $recency = 1.0 / (1.0 + ($days / 14.0));
        }

        return round($hits * (1.0 + log($breadth + 1)) * (0.5 + 0.5 * $recency), 2);
    }

    /** Cheap per-row provisional score (no cross-key query). */
    public function provisionalScore(int $hitCount, int $keyBreadth = 1, ?\DateTimeInterface $lastSeen = null): float
    {
        return $this->score($hitCount, $keyBreadth, $lastSeen ?? now());
    }

    /** @param  list<string>  $tokens */
    public function queueRefresh(array $tokens): void
    {
        if ($tokens === []) {
            return;
        }
        $set = Cache::get(self::DIRTY_CACHE_KEY, []);
        if (! is_array($set)) {
            $set = [];
        }
        foreach ($tokens as $token) {
            $token = mb_strtolower(trim((string) $token));
            if ($token !== '') {
                $set[$token] = true;
            }
        }
        Cache::put(self::DIRTY_CACHE_KEY, $set, now()->addHours(6));
    }

    /** Flush dirty tokens after the HTTP response (decide stays fast). */
    public function flushQueuedAfterResponse(): void
    {
        dispatch(function () {
            $this->flushQueued();
        })->afterResponse();
    }

    public function flushQueued(): int
    {
        $set = Cache::pull(self::DIRTY_CACHE_KEY, []);
        if (! is_array($set) || $set === []) {
            return 0;
        }
        foreach (array_keys($set) as $token) {
            $this->refreshToken((string) $token);
        }

        return count($set);
    }

    /** Refresh breadth + rank for one surface across non-sandbox keys. */
    public function refreshToken(string $token): void
    {
        $token = mb_strtolower(trim($token));
        if ($token === '') {
            return;
        }

        $sandboxIds = WiseApiKey::query()
            ->where(function ($q) {
                $q->where('meta->sandbox', true)
                    ->orWhere('meta->sandbox', 1)
                    ->orWhere('meta->governance->sandbox', true);
            })
            ->pluck('id')
            ->all();

        $breadthQ = WiseLanguageReview::query()->where('token', $token);
        if ($sandboxIds !== []) {
            $breadthQ->whereNotIn('wise_api_key_id', $sandboxIds);
        }
        $breadth = (int) $breadthQ->selectRaw('count(distinct wise_api_key_id) as c')->value('c');
        $breadth = max(1, $breadth);

        $rows = WiseLanguageReview::query()->where('token', $token)->get();
        foreach ($rows as $row) {
            $row->key_breadth = $breadth;
            $row->rank_score = $this->score((int) $row->hit_count, $breadth, $row->last_seen_at);
            $row->save();
        }
    }

    /** Recompute open queue ranks (bounded) — artisan / cron. */
    public function refreshOpen(int $limit = 500): int
    {
        $tokens = WiseLanguageReview::query()
            ->where('status', 'open')
            ->orderByDesc('hit_count')
            ->limit($limit)
            ->pluck('token')
            ->unique()
            ->values();

        foreach ($tokens as $token) {
            $this->refreshToken((string) $token);
        }

        return $tokens->count();
    }
}
