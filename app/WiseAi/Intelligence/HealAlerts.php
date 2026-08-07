<?php

namespace App\WiseAi\Intelligence;

use App\Models\WiseAi\WiseTurn;

/**
 * Observe-only heal alerts for Dashboard / live poll.
 * Does not remediate — humans close gaps via Learning.
 */
class HealAlerts
{
    public const VERSION = 'heal-alerts-1.0';

    public const MIN_TURNS = 10;

    public const GAP_RATE_WARN = 40.0;

    public const SCORE_CRITICAL = 40;

    public const SCORE_WARN = 60;

    public const GAPS_OPEN_WARN = 25;

    /**
     * @param  array{
     *     score: int,
     *     label: string,
     *     metrics: array<string, float|int|bool|null>
     * }  $live
     * @return list<array{id: string, severity: string, label: string, message: string, href_kind: string}>
     */
    public function fromLive(array $live, ?int $gapsOpen = null): array
    {
        $metrics = is_array($live['metrics'] ?? null) ? $live['metrics'] : [];
        $turns = (int) ($metrics['turns'] ?? 0);
        $gapRate = (float) ($metrics['gap_rate'] ?? 0);
        $score = (int) ($live['score'] ?? 0);
        $gapsOpen ??= WiseTurn::query()->where('gap', true)->whereNull('gap_handled_at')->count();

        $alerts = [];

        if ($turns >= self::MIN_TURNS && $gapRate >= self::GAP_RATE_WARN) {
            $alerts[] = [
                'id' => 'high_gap_rate',
                'severity' => 'warning',
                'label' => 'High gap rate',
                'message' => "Gap rate {$gapRate}% over {$turns} turns (last window) — review Learning gaps.",
                'href_kind' => 'gap',
            ];
        }

        if ($turns >= self::MIN_TURNS && $score < self::SCORE_CRITICAL) {
            $alerts[] = [
                'id' => 'low_ai_health',
                'severity' => 'critical',
                'label' => 'AI health critical',
                'message' => "Health score {$score}/100 — check gaps, rejects, and drafts.",
                'href_kind' => 'gap',
            ];
        } elseif ($turns >= self::MIN_TURNS && $score < self::SCORE_WARN) {
            $alerts[] = [
                'id' => 'low_ai_health',
                'severity' => 'warning',
                'label' => 'AI health needs attention',
                'message' => "Health score {$score}/100 ({$live['label']}) — open Learning workbench.",
                'href_kind' => 'gap',
            ];
        }

        if ($gapsOpen >= self::GAPS_OPEN_WARN) {
            $alerts[] = [
                'id' => 'queue_gaps',
                'severity' => 'warning',
                'label' => 'Gap queue backlog',
                'message' => "{$gapsOpen} open gaps waiting — auto-drafts ready for human review.",
                'href_kind' => 'gap',
            ];
        }

        $clOpen = \App\Models\WiseAi\WiseKnowledgeItem::query()
            ->where('status', 'draft')
            ->where('meta->source', \App\WiseAi\Learning\ConversationLearningExtractor::META_SOURCE)
            ->count();
        if ($clOpen >= 10) {
            $alerts[] = [
                'id' => 'cl_drafts_backlog',
                'severity' => 'warning',
                'label' => 'Learning drafts awaiting review',
                'message' => "{$clOpen} continuous-learning FAQ drafts — publish or reject in Learning.",
                'href_kind' => 'cl_candidate',
            ];
        }

        return $alerts;
    }
}
