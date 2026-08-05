<?php

namespace App\WiseAi\Learning;

use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseTurn;
use Illuminate\Support\Collection;

/**
 * Unified Learning Inbox — one human loop over gaps, language suggests, assist, rejects.
 */
class LearningInbox
{
    public const KINDS = ['all', 'gap', 'language', 'assist', 'reject'];

    /**
     * @return array{
     *     gaps_open: int,
     *     language_open: int,
     *     assist_pending: int,
     *     rejects_recent: int,
     *     open_total: int
     * }
     */
    public function stats(): array
    {
        $gaps = WiseTurn::query()->where('gap', true)->whereNull('gap_handled_at')->count();
        $language = WiseLanguageReview::query()->where('status', 'open')->count();
        $assist = WiseTurn::query()
            ->whereIn('decision->action', ['suggest_reply', 'clarify'])
            ->whereDoesntHave('feedbacks')
            ->count();
        $rejects = WiseFeedback::query()
            ->where('outcome', 'rejected')
            ->where('created_at', '>=', now()->subDays(14))
            ->count();

        return [
            'gaps_open' => $gaps,
            'language_open' => $language,
            'assist_pending' => $assist,
            'rejects_recent' => $rejects,
            'open_total' => $gaps + $language + $assist,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function feed(string $kind = 'all', int $limit = 80): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            $kind = 'all';
        }

        // Discovery Queue: preserve rank_score order (do not re-sort by occurred_at).
        if ($kind === 'language') {
            return $this->languageRows($limit);
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();

        // "all" = open work only (gaps + language + assist). Rejects are historical —
        // never merge them here or they starve actionable rows under the take() cap.
        if ($kind === 'all' || $kind === 'gap') {
            $rows = $rows->merge($this->gapRows($kind === 'gap' ? $limit : 40));
        }
        if ($kind === 'all') {
            $rows = $rows->merge($this->languageRows(40));
        }
        if ($kind === 'all' || $kind === 'assist') {
            $rows = $rows->merge($this->assistRows($kind === 'assist' ? $limit : 40));
        }
        if ($kind === 'reject') {
            $rows = $rows->merge($this->rejectRows($limit));
        }

        return $rows
            ->sortByDesc(fn (array $row) => $row['occurred_at'] ?? '')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function gapRows(int $limit): array
    {
        return WiseTurn::query()
            ->with(['apiKey:id,name', 'gapAutoDraft:id,title,status'])
            ->where('gap', true)
            ->whereNull('gap_handled_at')
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'wise_api_key_id', 'channel', 'text', 'decision', 'gap_auto_draft_id', 'created_at'])
            ->map(fn (WiseTurn $turn) => [
                'uid' => 'gap:'.$turn->id,
                'kind' => 'gap',
                'ref_id' => $turn->id,
                'turn_id' => $turn->id,
                'key_name' => $turn->apiKey?->name,
                'channel' => $turn->channel,
                'title' => $turn->text ?: '(empty)',
                'detail' => 'Knowledge gap · '.($turn->decision['intent'] ?? 'unknown')
                    .($turn->gap_auto_draft_id ? ' · auto-draft ready' : ''),
                'intent' => $turn->decision['intent'] ?? null,
                'suggested_reply' => is_string($turn->decision['suggested_reply'] ?? null)
                    ? (string) $turn->decision['suggested_reply']
                    : null,
                'auto_draft_id' => $turn->gap_auto_draft_id,
                'auto_draft_status' => $turn->gapAutoDraft?->status,
                'auto_draft_title' => $turn->gapAutoDraft?->title,
                'psych' => $turn->decision['psych'] ?? null,
                'opportunities' => $turn->decision['opportunities']['items'] ?? [],
                'reason_code' => null,
                'reason_label' => null,
                'hit_count' => null,
                'occurred_at' => $turn->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function languageRows(int $limit): array
    {
        return WiseLanguageReview::query()
            ->with(['apiKey:id,name'])
            ->where('status', 'open')
            ->orderByDesc('rank_score')
            ->orderByDesc('hit_count')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(fn (WiseLanguageReview $review) => [
                'uid' => 'language:'.$review->id,
                'kind' => 'language',
                'ref_id' => $review->id,
                'turn_id' => $review->last_turn_id,
                'key_name' => $review->apiKey?->name,
                'channel' => $review->channel ?? null,
                'title' => $review->token,
                'detail' => 'Discovery · hits '.$review->hit_count
                    .(isset($review->rank_score) ? ' · rank '.round((float) $review->rank_score, 1) : '')
                    .($review->sample_text ? ' · “'.$this->clip((string) $review->sample_text, 60).'”' : ''),
                'intent' => null,
                'suggested_reply' => null,
                'reason_code' => null,
                'reason_label' => null,
                'hit_count' => $review->hit_count,
                'sample_text' => $review->sample_text,
                'suggested_pack' => $review->suggested_pack_slug ?? null,
                'suggested_category' => $review->suggested_category ?? null,
                'rank_score' => $review->rank_score ?? null,
                'occurred_at' => ($review->last_seen_at ?? $review->created_at)?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assistRows(int $limit): array
    {
        $rows = WiseTurn::query()
            ->with(['apiKey:id,name'])
            ->whereIn('decision->action', ['suggest_reply', 'clarify'])
            ->whereDoesntHave('feedbacks')
            ->latest('id')
            ->limit(max($limit * 2, 40))
            ->get(['id', 'wise_api_key_id', 'channel', 'text', 'decision', 'created_at'])
            ->map(fn (WiseTurn $turn) => [
                'uid' => 'assist:'.$turn->id,
                'kind' => 'assist',
                'ref_id' => $turn->id,
                'turn_id' => $turn->id,
                'key_name' => $turn->apiKey?->name,
                'channel' => $turn->channel,
                'title' => $turn->text ?: '(empty)',
                'detail' => 'Review suggestion · '.($turn->decision['action'] ?? '')
                    .' · '.($turn->decision['intent'] ?? '?'),
                'intent' => $turn->decision['intent'] ?? null,
                'suggested_reply' => $turn->decision['suggested_reply'] ?? null,
                'psych' => $turn->decision['psych'] ?? null,
                'opportunities' => $turn->decision['opportunities']['items'] ?? [],
                'assist_priority' => $turn->decision['psych']['priority'] ?? 'normal',
                'reason_code' => null,
                'reason_label' => null,
                'hit_count' => null,
                'occurred_at' => $turn->created_at?->toDateTimeString(),
            ]);

        $rank = ['critical' => 0, 'high' => 1, 'normal' => 2];

        return $rows
            ->sortBy(fn (array $row) => [
                $rank[$row['assist_priority'] ?? 'normal'] ?? 2,
                -strtotime((string) ($row['occurred_at'] ?? '1970-01-01')),
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rejectRows(int $limit): array
    {
        return WiseFeedback::query()
            ->with(['apiKey:id,name', 'turn:id,text,channel,decision'])
            ->where('outcome', 'rejected')
            ->where('created_at', '>=', now()->subDays(14))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (WiseFeedback $fb) {
                $code = (string) ($fb->reason_code ?? ReasonCodes::OTHER);

                return [
                    'uid' => 'reject:'.$fb->id,
                    'kind' => 'reject',
                    'ref_id' => $fb->id,
                    'turn_id' => $fb->wise_turn_id,
                    'key_name' => $fb->apiKey?->name,
                    'channel' => $fb->turn?->channel,
                    'title' => $fb->turn?->text ?: '(empty)',
                    'detail' => 'Rejected · '.ReasonCodes::label($code),
                    'intent' => $fb->turn?->decision['intent'] ?? null,
                    'suggested_reply' => $fb->turn?->decision['suggested_reply'] ?? null,
                    'reason_code' => $code,
                    'reason_label' => ReasonCodes::label($code),
                    'hit_count' => null,
                    'occurred_at' => $fb->created_at?->toDateTimeString(),
                ];
            })
            ->all();
    }

    private function clip(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }
}
