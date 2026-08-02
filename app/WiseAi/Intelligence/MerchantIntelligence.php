<?php

namespace App\WiseAi\Intelligence;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Commerce\CommerceAttribution;
use App\WiseAi\Learning\ReasonCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Merchant Intelligence v1 — quality / queue / leak proxies from sealed turns + feedback.
 * Defaults exclude sandbox (Playground / eval) so BI is not vanity noise.
 */
class MerchantIntelligence
{
    /**
     * @return array<string, mixed>
     */
    public function report(int $days = 7, ?int $apiKeyId = null, bool $excludeSandbox = true): array
    {
        $days = max(1, min(90, $days));
        $since = now()->subDays($days)->startOfDay();

        $turnsQ = $this->turnsQuery($excludeSandbox, $apiKeyId)->where('created_at', '>=', $since);
        $turns = (clone $turnsQ)->count();

        $gaps = (clone $turnsQ)->where('gap', true)->count();
        $clarify = (clone $turnsQ)->where('decision->action', 'clarify')->count();
        $needsHuman = (clone $turnsQ)->where('decision->action', 'needs_human')->count();
        $suggest = (clone $turnsQ)->where('decision->action', 'suggest_reply')->count();

        $avgLatency = (clone $turnsQ)->avg('latency_ms');

        $feedbackBase = $this->feedbackQuery($excludeSandbox, $apiKeyId)
            ->where('wise_feedback.created_at', '>=', $since);

        $accepted = (clone $feedbackBase)->where('wise_feedback.outcome', 'approved')->count();
        $edited = (clone $feedbackBase)->where('wise_feedback.outcome', 'edited')->count();
        $rejected = (clone $feedbackBase)->where('wise_feedback.outcome', 'rejected')->count();
        $reviewed = $accepted + $edited + $rejected;

        $rejectReasons = (clone $feedbackBase)
            ->where('wise_feedback.outcome', 'rejected')
            ->whereNotNull('wise_feedback.reason_code')
            ->selectRaw('wise_feedback.reason_code as reason_code, COUNT(*) as c')
            ->groupBy('wise_feedback.reason_code')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->reason_code,
                'label' => ReasonCodes::label((string) $row->reason_code),
                'count' => (int) $row->c,
            ])
            ->values()
            ->all();

        $knowledgeLeakRejects = (clone $feedbackBase)
            ->where('wise_feedback.outcome', 'rejected')
            ->whereIn('wise_feedback.reason_code', [
                ReasonCodes::WRONG_FACT,
                ReasonCodes::MISSING_KNOWLEDGE,
                ReasonCodes::OUTDATED,
            ])
            ->count();

        $openQueues = $this->openQueues($excludeSandbox, $apiKeyId);

        $byKey = $this->byKey($since, $excludeSandbox, $apiKeyId);

        $drill = $this->drillRows($excludeSandbox, $apiKeyId, 24);

        $commerce = app(CommerceAttribution::class)->report($days, $apiKeyId, $excludeSandbox);

        $metrics = [
            'turns' => $turns,
            'gap_rate' => $this->pct($gaps, $turns),
            'clarify_rate' => $this->pct($clarify, $turns),
            'needs_human_rate' => $this->pct($needsHuman, $turns),
            'suggest_rate' => $this->pct($suggest, $turns),
            'accept_rate' => $this->pct($accepted, $reviewed),
            'edit_rate' => $this->pct($edited, $reviewed),
            'reject_rate' => $this->pct($rejected, $reviewed),
            'assist_pending' => $openQueues['assist_pending'],
            'gaps_open' => $openQueues['gaps_open'],
            'language_open' => $openQueues['language_open'],
            'knowledge_leak_proxy' => $this->pct($knowledgeLeakRejects, $rejected),
            'attributed_orders' => $commerce['attributed_orders'],
            'assisted_order_rate' => $commerce['assisted_order_rate'],
            'attributed_gmv' => $commerce['attributed_gmv'],
            'lost_sales_attributed' => $commerce['lost_sales_attributed'],
            'avg_latency_ms' => $avgLatency !== null ? (int) round((float) $avgLatency) : null,
        ];

        return [
            'metrics_version' => MetricDefinitions::VERSION,
            'definitions' => MetricDefinitions::all(),
            'window' => [
                'days' => $days,
                'since' => $since->toDateTimeString(),
                'exclude_sandbox' => $excludeSandbox,
                'wise_api_key_id' => $apiKeyId,
            ],
            'metrics' => $metrics,
            'commerce' => $commerce,
            'action_mix' => [
                'suggest_reply' => $suggest,
                'clarify' => $clarify,
                'needs_human' => $needsHuman,
                'other' => max(0, $turns - $suggest - $clarify - $needsHuman),
            ],
            'feedback_mix' => [
                'approved' => $accepted,
                'edited' => $edited,
                'rejected' => $rejected,
                'reviewed' => $reviewed,
            ],
            'reject_reasons' => $rejectReasons,
            'queues' => $openQueues,
            'by_key' => $byKey,
            'drill' => $drill,
        ];
    }

    /**
     * @return Builder<WiseTurn>
     */
    private function turnsQuery(bool $excludeSandbox, ?int $apiKeyId): Builder
    {
        $q = WiseTurn::query();
        if ($apiKeyId !== null) {
            $q->where('wise_api_key_id', $apiKeyId);
        }
        if ($excludeSandbox) {
            $this->excludeSandboxTurns($q);
        }

        return $q;
    }

    /**
     * @return Builder<WiseFeedback>
     */
    private function feedbackQuery(bool $excludeSandbox, ?int $apiKeyId): Builder
    {
        $q = WiseFeedback::query()
            ->join('wise_turns', 'wise_turns.id', '=', 'wise_feedback.wise_turn_id');

        if ($apiKeyId !== null) {
            $q->where('wise_feedback.wise_api_key_id', $apiKeyId);
        }
        if ($excludeSandbox) {
            SandboxScope::excludeTurns($q, 'wise_turns.config_snapshot');
        }

        // Do not select wise_feedback.* — callers often groupBy columns (ONLY_FULL_GROUP_BY).
        return $q;
    }

    /**
     * @param  Builder<WiseTurn>  $q
     */
    private function excludeSandboxTurns(Builder $q): void
    {
        SandboxScope::excludeTurns($q);
    }

    /**
     * @return array{assist_pending: int, gaps_open: int, language_open: int, open_total: int}
     */
    private function openQueues(bool $excludeSandbox, ?int $apiKeyId): array
    {
        $gapsQ = WiseTurn::query()->where('gap', true)->whereNull('gap_handled_at');
        $assistQ = WiseTurn::query()
            ->whereIn('decision->action', ['suggest_reply', 'clarify'])
            ->whereDoesntHave('feedbacks');
        $langQ = WiseLanguageReview::query()->where('status', 'open');

        if ($apiKeyId !== null) {
            $gapsQ->where('wise_api_key_id', $apiKeyId);
            $assistQ->where('wise_api_key_id', $apiKeyId);
            $langQ->where('wise_api_key_id', $apiKeyId);
        }
        if ($excludeSandbox) {
            $this->excludeSandboxTurns($gapsQ);
            $this->excludeSandboxTurns($assistQ);
            // Language reviews: drop rows tied to sandbox keys.
            $sandboxKeyIds = WiseApiKey::query()
                ->where(function ($q) {
                    $q->where('meta->sandbox', true)
                        ->orWhere('meta->sandbox', 1)
                        ->orWhere('meta->governance->sandbox', true);
                })
                ->pluck('id');
            if ($sandboxKeyIds->isNotEmpty()) {
                $langQ->whereNotIn('wise_api_key_id', $sandboxKeyIds);
            }
        }

        $gaps = $gapsQ->count();
        $assist = $assistQ->count();
        $language = $langQ->count();

        return [
            'assist_pending' => $assist,
            'gaps_open' => $gaps,
            'language_open' => $language,
            'open_total' => $gaps + $assist + $language,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byKey(\DateTimeInterface $since, bool $excludeSandbox, ?int $apiKeyId): array
    {
        if ($apiKeyId !== null) {
            return [];
        }

        $q = WiseTurn::query()
            ->where('created_at', '>=', $since)
            ->select([
                'wise_api_key_id',
                DB::raw('COUNT(*) as turns'),
                DB::raw('SUM(CASE WHEN gap = 1 THEN 1 ELSE 0 END) as gaps'),
            ])
            ->groupBy('wise_api_key_id')
            ->orderByDesc('turns')
            ->limit(25);

        if ($excludeSandbox) {
            $this->excludeSandboxTurns($q);
        }

        $rows = $q->get();
        $names = WiseApiKey::query()
            ->whereIn('id', $rows->pluck('wise_api_key_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($names) {
            $turns = (int) $row->turns;
            $gaps = (int) $row->gaps;

            return [
                'wise_api_key_id' => (int) $row->wise_api_key_id,
                'key_name' => $names[$row->wise_api_key_id] ?? ('#'.$row->wise_api_key_id),
                'turns' => $turns,
                'gaps' => $gaps,
                'gap_rate' => $this->pct($gaps, $turns),
            ];
        })->values()->all();
    }

    /**
     * Recent turns worth drilling into Explain / Learning.
     *
     * @return list<array<string, mixed>>
     */
    private function drillRows(bool $excludeSandbox, ?int $apiKeyId, int $limit): array
    {
        $q = WiseTurn::query()
            ->with(['apiKey:id,name', 'latestFeedback'])
            ->where(function ($inner) {
                $inner->where('gap', true)
                    ->orWhereIn('decision->action', ['needs_human', 'clarify'])
                    ->orWhereHas('feedbacks', fn ($f) => $f->where('outcome', 'rejected'));
            })
            ->latest('id')
            ->limit($limit);

        if ($apiKeyId !== null) {
            $q->where('wise_api_key_id', $apiKeyId);
        }
        if ($excludeSandbox) {
            $this->excludeSandboxTurns($q);
        }

        return $q->get()->map(function (WiseTurn $turn) {
            $decision = is_array($turn->decision) ? $turn->decision : [];
            $fb = $turn->latestFeedback;

            return [
                'turn_id' => $turn->id,
                'text' => mb_substr((string) $turn->text, 0, 120),
                'key_name' => $turn->apiKey?->name,
                'wise_api_key_id' => $turn->wise_api_key_id,
                'action' => $decision['action'] ?? null,
                'intent' => $decision['intent'] ?? null,
                'gap' => (bool) $turn->gap,
                'feedback' => $fb?->outcome,
                'reason_code' => $fb?->reason_code,
                'created_at' => $turn->created_at?->toDateTimeString(),
                'drill' => $turn->gap
                    ? 'learning_gap'
                    : (($fb?->outcome === 'rejected') ? 'learning_reject' : 'explain'),
            ];
        })->all();
    }

    private function pct(int $part, int $whole): ?float
    {
        if ($whole <= 0) {
            return null;
        }

        return round(100 * $part / $whole, 1);
    }
}
