<?php

namespace App\WiseAi\Intelligence;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Governance\MerchantPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Founder fleet health v1 (Mega 5) — ecosystem observe across keys.
 * Not a copy of Merchant BI: key rows + alerts + usage/cost proxies primary.
 */
class FleetHealth
{
    public function __construct(
        private MerchantPolicy $merchantPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(int $days = 7, bool $excludeSandbox = true): array
    {
        $days = max(1, min(90, $days));
        $since = now()->subDays($days)->startOfDay();

        $keys = WiseApiKey::query()->orderBy('name')->get();
        $sandboxKeyIds = $keys->filter(fn (WiseApiKey $k) => $this->isSandboxKey($k))->pluck('id');

        $keysScoped = $excludeSandbox
            ? $keys->reject(fn (WiseApiKey $k) => $sandboxKeyIds->contains($k->id))->values()
            : $keys;

        $turnStats = $this->turnStatsByKey($since, $excludeSandbox);
        $feedbackStats = $this->feedbackStatsByKey($since, $excludeSandbox);
        $queueStats = $this->queueStatsByKey($excludeSandbox, $sandboxKeyIds->all());

        $keyRows = [];
        $alerts = [];
        $fleetTurns = 0;
        $fleetGaps = 0;
        $fleetApproved = 0;
        $fleetEdited = 0;
        $fleetRejected = 0;
        $fleetLatencySum = 0;
        $fleetLatencyN = 0;
        $fleetCostUnits = 0;

        foreach ($keysScoped as $key) {
            /** @var WiseApiKey $key */
            $id = (int) $key->id;
            $t = $turnStats[$id] ?? ['turns' => 0, 'gaps' => 0, 'latency_sum' => 0, 'latency_n' => 0];
            $f = $feedbackStats[$id] ?? ['approved' => 0, 'edited' => 0, 'rejected' => 0];
            $q = $queueStats[$id] ?? ['gaps_open' => 0, 'assist_pending' => 0, 'language_open' => 0];

            $turns = (int) $t['turns'];
            $gaps = (int) $t['gaps'];
            $reviewed = (int) $f['approved'] + (int) $f['edited'] + (int) $f['rejected'];
            $gapRate = $this->pct($gaps, $turns);
            $acceptRate = $this->pct((int) $f['approved'], $reviewed);
            $rejectRate = $this->pct((int) $f['rejected'], $reviewed);
            $avgLatency = $t['latency_n'] > 0
                ? (int) round($t['latency_sum'] / $t['latency_n'])
                : null;
            // Cost proxy until LLM billing: turn-ms compute units (honest label, not money).
            $costUnits = (int) round($t['latency_sum']);

            $gov = $this->merchantPolicy->resolve($key);
            $row = [
                'wise_api_key_id' => $id,
                'key_name' => $key->name,
                'status' => $key->status,
                'sandbox' => $this->isSandboxKey($key),
                'mode' => $gov['mode'],
                'allow_auto' => (bool) $gov['allow_auto'],
                'turns' => $turns,
                'gaps' => $gaps,
                'gap_rate' => $gapRate,
                'approved' => (int) $f['approved'],
                'edited' => (int) $f['edited'],
                'rejected' => (int) $f['rejected'],
                'accept_rate' => $acceptRate,
                'reject_rate' => $rejectRate,
                'avg_latency_ms' => $avgLatency,
                'cost_units' => $costUnits,
                'gaps_open' => (int) $q['gaps_open'],
                'assist_pending' => (int) $q['assist_pending'],
                'language_open' => (int) $q['language_open'],
                'last_used_at' => $key->last_used_at?->toDateTimeString(),
                'created_at' => $key->created_at?->toDateTimeString(),
                'alert_ids' => [],
            ];

            $rowAlerts = $this->alertsForKey($row);
            $row['alert_ids'] = array_column($rowAlerts, 'id');
            foreach ($rowAlerts as $alert) {
                $alerts[] = $alert;
            }

            $keyRows[] = $row;
            $fleetTurns += $turns;
            $fleetGaps += $gaps;
            $fleetApproved += (int) $f['approved'];
            $fleetEdited += (int) $f['edited'];
            $fleetRejected += (int) $f['rejected'];
            $fleetLatencySum += (int) $t['latency_sum'];
            $fleetLatencyN += (int) $t['latency_n'];
            $fleetCostUnits += $costUnits;
        }

        usort($keyRows, fn ($a, $b) => $b['turns'] <=> $a['turns']);
        usort($alerts, function ($a, $b) {
            $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];

            return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
        });

        $fleetReviewed = $fleetApproved + $fleetEdited + $fleetRejected;

        $daily = $this->dailyVolume($since, $excludeSandbox);

        return [
            'metrics_version' => MetricDefinitions::VERSION,
            'alerts_version' => FleetAlerts::VERSION,
            'window' => [
                'days' => $days,
                'since' => $since->toDateTimeString(),
                'exclude_sandbox' => $excludeSandbox,
            ],
            'fleet' => [
                'keys_total' => $keys->count(),
                'keys_scoped' => $keysScoped->count(),
                'keys_active' => $keysScoped->where('status', 'active')->count(),
                'keys_sandbox_hidden' => $excludeSandbox ? $sandboxKeyIds->count() : 0,
                'turns' => $fleetTurns,
                'gap_rate' => $this->pct($fleetGaps, $fleetTurns),
                'accept_rate' => $this->pct($fleetApproved, $fleetReviewed),
                'reject_rate' => $this->pct($fleetRejected, $fleetReviewed),
                'reviewed' => $fleetReviewed,
                'avg_latency_ms' => $fleetLatencyN > 0
                    ? (int) round($fleetLatencySum / $fleetLatencyN)
                    : null,
                'cost_units' => $fleetCostUnits,
                'cost_units_label' => 'turn-ms (latency sum) — usage proxy, not money',
                'alerts_open' => count($alerts),
            ],
            'keys' => $keyRows,
            'alerts' => $alerts,
            'alert_catalog' => FleetAlerts::catalog(),
            'daily' => $daily,
        ];
    }

    /**
     * @return array<int, array{turns: int, gaps: int, latency_sum: float, latency_n: int}>
     */
    private function turnStatsByKey(\DateTimeInterface $since, bool $excludeSandbox): array
    {
        $q = WiseTurn::query()
            ->where('created_at', '>=', $since)
            ->select([
                'wise_api_key_id',
                DB::raw('COUNT(*) as turns'),
                DB::raw('SUM(CASE WHEN gap = 1 THEN 1 ELSE 0 END) as gaps'),
                DB::raw('COALESCE(SUM(latency_ms), 0) as latency_sum'),
                DB::raw('SUM(CASE WHEN latency_ms IS NOT NULL THEN 1 ELSE 0 END) as latency_n'),
            ])
            ->groupBy('wise_api_key_id');

        if ($excludeSandbox) {
            SandboxScope::excludeTurns($q);
        }

        $out = [];
        foreach ($q->get() as $row) {
            $out[(int) $row->wise_api_key_id] = [
                'turns' => (int) $row->turns,
                'gaps' => (int) $row->gaps,
                'latency_sum' => (float) $row->latency_sum,
                'latency_n' => (int) $row->latency_n,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{approved: int, edited: int, rejected: int}>
     */
    private function feedbackStatsByKey(\DateTimeInterface $since, bool $excludeSandbox): array
    {
        $q = WiseFeedback::query()
            ->join('wise_turns', 'wise_turns.id', '=', 'wise_feedback.wise_turn_id')
            ->where('wise_feedback.created_at', '>=', $since)
            ->select([
                'wise_feedback.wise_api_key_id',
                DB::raw("SUM(CASE WHEN wise_feedback.outcome = 'approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN wise_feedback.outcome = 'edited' THEN 1 ELSE 0 END) as edited"),
                DB::raw("SUM(CASE WHEN wise_feedback.outcome = 'rejected' THEN 1 ELSE 0 END) as rejected"),
            ])
            ->groupBy('wise_feedback.wise_api_key_id');

        if ($excludeSandbox) {
            SandboxScope::excludeTurns($q, 'wise_turns.config_snapshot');
        }

        $out = [];
        foreach ($q->get() as $row) {
            $out[(int) $row->wise_api_key_id] = [
                'approved' => (int) $row->approved,
                'edited' => (int) $row->edited,
                'rejected' => (int) $row->rejected,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $sandboxKeyIds
     * @return array<int, array{gaps_open: int, assist_pending: int, language_open: int}>
     */
    private function queueStatsByKey(bool $excludeSandbox, array $sandboxKeyIds): array
    {
        $gapsQ = WiseTurn::query()
            ->where('gap', true)
            ->whereNull('gap_handled_at')
            ->selectRaw('wise_api_key_id, COUNT(*) as c')
            ->groupBy('wise_api_key_id');
        $assistQ = WiseTurn::query()
            ->whereIn('decision->action', ['suggest_reply', 'clarify'])
            ->whereDoesntHave('feedbacks')
            ->selectRaw('wise_api_key_id, COUNT(*) as c')
            ->groupBy('wise_api_key_id');
        $langQ = WiseLanguageReview::query()
            ->where('status', 'open')
            ->selectRaw('wise_api_key_id, COUNT(*) as c')
            ->groupBy('wise_api_key_id');

        if ($excludeSandbox) {
            SandboxScope::excludeTurns($gapsQ);
            SandboxScope::excludeTurns($assistQ);
            if ($sandboxKeyIds !== []) {
                $langQ->whereNotIn('wise_api_key_id', $sandboxKeyIds);
            }
        }

        $out = [];
        $merge = function ($rows, string $field) use (&$out): void {
            foreach ($rows as $row) {
                $id = (int) $row->wise_api_key_id;
                $out[$id] ??= ['gaps_open' => 0, 'assist_pending' => 0, 'language_open' => 0];
                $out[$id][$field] = (int) $row->c;
            }
        };

        $merge($gapsQ->get(), 'gaps_open');
        $merge($assistQ->get(), 'assist_pending');
        $merge($langQ->get(), 'language_open');

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function alertsForKey(array $row): array
    {
        $alerts = [];
        $keyId = $row['wise_api_key_id'];
        $name = $row['key_name'];

        $push = function (string $id, string $severity, string $message) use (&$alerts, $keyId, $name): void {
            $alerts[] = [
                'id' => $id,
                'severity' => $severity,
                'wise_api_key_id' => $keyId,
                'key_name' => $name,
                'message' => $message,
            ];
        };

        if ($row['allow_auto']) {
            $push('auto_enabled', 'critical', 'Auto mode enabled — default should stay off until earned');
        }

        if ($row['turns'] >= FleetAlerts::MIN_TURNS_FOR_RATE
            && $row['gap_rate'] !== null
            && $row['gap_rate'] >= FleetAlerts::GAP_RATE_WARN) {
            $push('high_gap_rate', 'warning', "Gap rate {$row['gap_rate']}% over {$row['turns']} turns");
        }

        $reviewed = $row['approved'] + $row['edited'] + $row['rejected'];
        if ($reviewed >= FleetAlerts::MIN_REVIEWS_FOR_RATE
            && $row['reject_rate'] !== null
            && $row['reject_rate'] >= FleetAlerts::REJECT_RATE_WARN) {
            $push('high_reject_rate', 'warning', "Reject rate {$row['reject_rate']}% over {$reviewed} reviews");
        }

        if ($row['gaps_open'] >= FleetAlerts::GAPS_OPEN_WARN) {
            $push('queue_gaps', 'warning', "{$row['gaps_open']} open gaps");
        }

        if ($row['assist_pending'] >= FleetAlerts::ASSIST_PENDING_WARN) {
            $push('queue_assist', 'warning', "{$row['assist_pending']} assist pending");
        }

        if ($row['avg_latency_ms'] !== null && $row['avg_latency_ms'] >= FleetAlerts::LATENCY_MS_WARN) {
            $push('slow_latency', 'info', "Avg latency {$row['avg_latency_ms']} ms");
        }

        if ($row['status'] === 'active' && $row['turns'] === 0 && ! $row['sandbox']) {
            $staleBefore = now()->subDays(FleetAlerts::STALE_DAYS)->getTimestamp();
            $created = $row['created_at'] ? strtotime((string) $row['created_at']) : null;
            // Brand-new keys are not stale — only keys older than STALE_DAYS with no window usage.
            if ($created !== null && $created < $staleBefore) {
                $last = $row['last_used_at'] ? strtotime((string) $row['last_used_at']) : null;
                if ($last === null || $last < $staleBefore) {
                    $push('stale_key', 'info', 'Active key with no recent usage');
                }
            }
        }

        return $alerts;
    }

    /**
     * @return list<array{date: string, turns: int}>
     */
    private function dailyVolume(\DateTimeInterface $since, bool $excludeSandbox): array
    {
        $q = WiseTurn::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d');

        if ($excludeSandbox) {
            SandboxScope::excludeTurns($q);
        }

        $map = $q->pluck('c', 'd')->map(fn ($c) => (int) $c)->all();
        $out = [];
        $cursor = \Illuminate\Support\Carbon::parse($since)->startOfDay();
        $end = now()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $out[] = ['date' => $key, 'turns' => $map[$key] ?? 0];
            $cursor->addDay();
        }

        return $out;
    }

    private function isSandboxKey(WiseApiKey $key): bool
    {
        $meta = is_array($key->meta) ? $key->meta : [];

        return (bool) (($meta['sandbox'] ?? false) || ($meta['governance']['sandbox'] ?? false));
    }

    private function pct(int $part, int $whole): ?float
    {
        if ($whole <= 0) {
            return null;
        }

        return round(100 * $part / $whole, 1);
    }
}
