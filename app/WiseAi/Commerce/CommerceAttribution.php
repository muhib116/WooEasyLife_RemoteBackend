<?php

namespace App\WiseAi\Commerce;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseCommerceEvent;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Intelligence\SandboxScope;
use Illuminate\Support\Facades\DB;

/**
 * Honest commerce attribution for Merchant BI / Fleet (Wave C4).
 * GMV only from attributed events with amount — otherwise null (never invent money).
 */
class CommerceAttribution
{
    /**
     * @return array<string, mixed>
     */
    public function report(int $days = 7, ?int $apiKeyId = null, bool $excludeSandbox = true): array
    {
        $days = max(1, min(90, $days));
        $since = now()->subDays($days)->startOfDay();

        $eventsQ = WiseCommerceEvent::query()->where('occurred_at', '>=', $since);
        if ($apiKeyId !== null) {
            $eventsQ->where('wise_api_key_id', $apiKeyId);
        }
        if ($excludeSandbox) {
            $sandboxIds = $this->sandboxKeyIds();
            if ($sandboxIds !== []) {
                $eventsQ->whereNotIn('wise_api_key_id', $sandboxIds);
            }
        }

        $totalEvents = (clone $eventsQ)->count();
        $byType = (clone $eventsQ)
            ->selectRaw('event_type, COUNT(*) as c')
            ->groupBy('event_type')
            ->pluck('c', 'event_type')
            ->map(fn ($c) => (int) $c)
            ->all();

        // Attributed = has conversation_id that already had ≥1 turn for that key before/at event.
        $orderEvents = (clone $eventsQ)
            ->whereIn('event_type', CommerceEventTypes::gmvTypes())
            ->whereNotNull('conversation_id')
            ->where('conversation_id', '!=', '')
            ->get(['id', 'wise_api_key_id', 'conversation_id', 'amount', 'currency', 'event_type', 'occurred_at']);

        $attributedOrders = 0;
        $gmv = 0.0;
        $gmvCurrency = null;
        $gmvMixedCurrency = false;
        $hasAnyAmount = false;

        foreach ($orderEvents as $event) {
            $hasPriorTurn = WiseTurn::query()
                ->where('wise_api_key_id', $event->wise_api_key_id)
                ->where('conversation_id', $event->conversation_id)
                ->where('created_at', '<=', $event->occurred_at)
                ->exists();

            if (! $hasPriorTurn) {
                continue;
            }

            $attributedOrders++;
            if ($event->amount !== null) {
                $hasAnyAmount = true;
                $gmv += (float) $event->amount;
                $cur = $event->currency ?: 'XXX';
                if ($gmvCurrency === null) {
                    $gmvCurrency = $cur;
                } elseif ($gmvCurrency !== $cur) {
                    $gmvMixedCurrency = true;
                }
            }
        }

        $conversationsWithTurns = $this->conversationsWithTurns($since, $apiKeyId, $excludeSandbox);
        $assistedOrderRate = $this->pct($attributedOrders, $conversationsWithTurns);

        $lostEvents = (clone $eventsQ)
            ->whereIn('event_type', CommerceEventTypes::lostSaleTypes())
            ->whereNotNull('conversation_id')
            ->where('conversation_id', '!=', '')
            ->count();

        // Lost-sales: attributed cancels/returns after prior turn — still labeled when thin.
        $lostAttributed = 0;
        $lostRows = (clone $eventsQ)
            ->whereIn('event_type', CommerceEventTypes::lostSaleTypes())
            ->whereNotNull('conversation_id')
            ->get(['wise_api_key_id', 'conversation_id', 'occurred_at', 'amount']);
        $lostAmount = 0.0;
        foreach ($lostRows as $event) {
            $hasPriorTurn = WiseTurn::query()
                ->where('wise_api_key_id', $event->wise_api_key_id)
                ->where('conversation_id', $event->conversation_id)
                ->where('created_at', '<=', $event->occurred_at)
                ->exists();
            if ($hasPriorTurn) {
                $lostAttributed++;
                if ($event->amount !== null) {
                    $lostAmount += (float) $event->amount;
                }
            }
        }

        return [
            'schema_version' => CommerceEventTypes::VERSION,
            'window' => [
                'days' => $days,
                'since' => $since->toDateTimeString(),
                'exclude_sandbox' => $excludeSandbox,
                'wise_api_key_id' => $apiKeyId,
            ],
            'events_total' => $totalEvents,
            'events_by_type' => $byType,
            'conversations_with_turns' => $conversationsWithTurns,
            'attributed_orders' => $attributedOrders,
            'assisted_order_rate' => $assistedOrderRate,
            'attributed_gmv' => $hasAnyAmount ? round($gmv, 2) : null,
            'attributed_gmv_currency' => $hasAnyAmount && ! $gmvMixedCurrency ? $gmvCurrency : null,
            'attributed_gmv_note' => $hasAnyAmount
                ? ($gmvMixedCurrency
                    ? 'GMV summed across mixed currencies — treat as indicative only'
                    : 'Sum of amount on attributed order_created/order_paid')
                : 'n/a — no attributed events with amount (adapters must send amount)',
            'lost_sales_events' => $lostEvents,
            'lost_sales_attributed' => $lostAttributed,
            'lost_sales_amount' => $lostAttributed > 0 && $lostAmount > 0 ? round($lostAmount, 2) : null,
            'honest' => true,
        ];
    }

    private function conversationsWithTurns(\DateTimeInterface $since, ?int $apiKeyId, bool $excludeSandbox): int
    {
        $q = WiseTurn::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('conversation_id')
            ->where('conversation_id', '!=', '')
            ->select(DB::raw('COUNT(DISTINCT CONCAT(wise_api_key_id, ":", conversation_id)) as c'));

        if ($apiKeyId !== null) {
            $q->where('wise_api_key_id', $apiKeyId);
        }
        if ($excludeSandbox) {
            SandboxScope::excludeTurns($q);
        }

        return (int) ($q->value('c') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function sandboxKeyIds(): array
    {
        return WiseApiKey::query()
            ->where(function ($q) {
                $q->where('meta->sandbox', true)
                    ->orWhere('meta->sandbox', 1)
                    ->orWhere('meta->governance->sandbox', true);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function pct(int $part, int $whole): ?float
    {
        if ($whole <= 0) {
            return null;
        }

        return round(100 * $part / $whole, 1);
    }
}
