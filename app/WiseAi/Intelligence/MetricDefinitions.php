<?php

namespace App\WiseAi\Intelligence;

/**
 * Versioned Merchant Intelligence metric registry (Wave C1).
 * Seal {@see VERSION} on each turn so Replay/BI can interpret historical rates.
 */
class MetricDefinitions
{
    public const VERSION = '1.1';

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     unit: string,
     *     definition: string,
     *     group: string
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'turns',
                'label' => 'Turns',
                'unit' => 'count',
                'definition' => 'Decide calls in the window (non-sandbox when filter on).',
                'group' => 'volume',
            ],
            [
                'id' => 'gap_rate',
                'label' => 'Gap rate',
                'unit' => 'percent',
                'definition' => 'Turns with gap=true ÷ turns.',
                'group' => 'quality',
            ],
            [
                'id' => 'clarify_rate',
                'label' => 'Clarify rate',
                'unit' => 'percent',
                'definition' => 'decision.action=clarify ÷ turns (missing context / soft unknown).',
                'group' => 'quality',
            ],
            [
                'id' => 'needs_human_rate',
                'label' => 'Needs-human rate',
                'unit' => 'percent',
                'definition' => 'decision.action=needs_human ÷ turns.',
                'group' => 'quality',
            ],
            [
                'id' => 'suggest_rate',
                'label' => 'Suggest rate',
                'unit' => 'percent',
                'definition' => 'decision.action=suggest_reply ÷ turns.',
                'group' => 'quality',
            ],
            [
                'id' => 'accept_rate',
                'label' => 'Accept rate',
                'unit' => 'percent',
                'definition' => 'Feedback outcome=approved ÷ reviewed feedbacks in window.',
                'group' => 'feedback',
            ],
            [
                'id' => 'edit_rate',
                'label' => 'Edit rate',
                'unit' => 'percent',
                'definition' => 'Feedback outcome=edited ÷ reviewed feedbacks in window.',
                'group' => 'feedback',
            ],
            [
                'id' => 'reject_rate',
                'label' => 'Reject rate',
                'unit' => 'percent',
                'definition' => 'Feedback outcome=rejected ÷ reviewed feedbacks in window.',
                'group' => 'feedback',
            ],
            [
                'id' => 'assist_pending',
                'label' => 'Assist pending',
                'unit' => 'count',
                'definition' => 'Open suggest/clarify turns with no feedback (live queue, not windowed).',
                'group' => 'queue',
            ],
            [
                'id' => 'gaps_open',
                'label' => 'Gaps open',
                'unit' => 'count',
                'definition' => 'gap=true and gap_handled_at null (live queue).',
                'group' => 'queue',
            ],
            [
                'id' => 'language_open',
                'label' => 'Language open',
                'unit' => 'count',
                'definition' => 'Open language reviews (live queue).',
                'group' => 'queue',
            ],
            [
                'id' => 'knowledge_leak_proxy',
                'label' => 'Knowledge leak proxy',
                'unit' => 'percent',
                'definition' => 'Rejects with reason wrong_fact|missing_knowledge|outdated ÷ rejects (quality leak; not money).',
                'group' => 'leak',
            ],
            [
                'id' => 'attributed_orders',
                'label' => 'Attributed orders',
                'unit' => 'count',
                'definition' => 'order_created/order_paid events with conversation_id that had ≥1 prior turn (adapter must send events).',
                'group' => 'commerce',
            ],
            [
                'id' => 'assisted_order_rate',
                'label' => 'Assisted order rate',
                'unit' => 'percent',
                'definition' => 'Attributed orders ÷ conversations with turns in window — not total store revenue.',
                'group' => 'commerce',
            ],
            [
                'id' => 'attributed_gmv',
                'label' => 'Attributed GMV',
                'unit' => 'money',
                'definition' => 'Sum of amount on attributed order events only; null if adapters omit amount.',
                'group' => 'commerce',
            ],
            [
                'id' => 'lost_sales_attributed',
                'label' => 'Lost sales (attributed)',
                'unit' => 'count',
                'definition' => 'order_canceled/order_returned with prior conversation turns — honest only when adapters send cancels.',
                'group' => 'commerce',
            ],
            [
                'id' => 'avg_latency_ms',
                'label' => 'Avg latency',
                'unit' => 'ms',
                'definition' => 'Mean wise_turns.latency_ms in the window.',
                'group' => 'ops',
            ],
        ];
    }

    /**
     * @return array<string, array{id: string, label: string, unit: string, definition: string, group: string}>
     */
    public static function keyed(): array
    {
        $out = [];
        foreach (self::all() as $row) {
            $out[$row['id']] = $row;
        }

        return $out;
    }
}
