<?php

namespace App\WiseAi\Assist;

/**
 * Wave 4 — decide which factual tools/facts to surface from payload context.
 * LLM must not invent order/courier/stock; adapter-supplied context only in v1.
 */
final class ToolDecision
{
    /**
     * @param  array<string, mixed>  $context
     * @return list<array{source: string, key: string, value: string}>
     */
    public function collect(array $context): array
    {
        $facts = [];

        foreach (['order_id', 'order_status', 'tracking_id', 'courier'] as $key) {
            if (! empty($context[$key]) && (is_string($context[$key]) || is_numeric($context[$key]))) {
                $facts[] = [
                    'source' => 'context_tool',
                    'key' => $key,
                    'value' => mb_substr(trim((string) $context[$key]), 0, 120),
                ];
            }
        }

        $order = is_array($context['order'] ?? null) ? $context['order'] : [];
        foreach (['id', 'status', 'tracking', 'total'] as $key) {
            if (! empty($order[$key]) && (is_string($order[$key]) || is_numeric($order[$key]))) {
                $facts[] = [
                    'source' => 'order',
                    'key' => $key,
                    'value' => mb_substr(trim((string) $order[$key]), 0, 120),
                ];
            }
        }

        $stock = $context['stock'] ?? null;
        if (is_string($stock) || is_numeric($stock) || is_bool($stock)) {
            $facts[] = [
                'source' => 'inventory',
                'key' => 'stock',
                'value' => is_bool($stock) ? ($stock ? 'in_stock' : 'out_of_stock') : mb_substr(trim((string) $stock), 0, 80),
            ];
        }

        return array_slice($facts, 0, 12);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{action: string, reason: string}
     */
    public function decide(array $context, string $intent): array
    {
        if (! empty($context['signals']['risk']) || ($context['signals']['risk'] ?? null) === 'policy_scare') {
            return ['action' => 'handoff', 'reason' => 'risk_signal'];
        }

        if (in_array($intent, ['tracking', 'order_status'], true) || ! empty($context['order_id'])) {
            if ($this->collect($context) === []) {
                return ['action' => 'clarify', 'reason' => 'need_order_id'];
            }

            return ['action' => 'use_tool_facts', 'reason' => 'order_context'];
        }

        if (! empty($context['product_id']) || ! empty($context['external_id'])) {
            return ['action' => 'knowledge', 'reason' => 'offer_asserted'];
        }

        return ['action' => 'knowledge', 'reason' => 'default'];
    }
}
