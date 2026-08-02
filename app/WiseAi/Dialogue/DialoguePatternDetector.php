<?php

namespace App\WiseAi\Dialogue;

/**
 * Detect dialogue act after Language + Intent + Memory (+ optional product subject).
 * Never invents facts; never replaces Judge.
 */
class DialoguePatternDetector
{
    /**
     * @param  array{
     *     intent: string,
     *     kind: string,
     *     memory_applied?: string|null,
     *     memory_used?: bool,
     *     product_from_memory?: bool,
     *     has_product?: bool,
     *     prior_intent?: string|null,
     *     canonical?: string,
     *     unknown_tokens?: list<string>,
     *     region?: string|null
     * }  $input
     * @return array{
     *     id: string,
     *     label: string,
     *     family: string,
     *     version: string,
     *     from_intent: string,
     *     memory: bool,
     *     slots: array<string, mixed>
     * }
     */
    public function detect(array $input): array
    {
        $intent = (string) ($input['intent'] ?? 'unknown');
        $kind = (string) ($input['kind'] ?? 'unknown');
        $memoryApplied = $input['memory_applied'] ?? null;
        $hasProduct = (bool) ($input['has_product'] ?? false);
        $priorIntent = $input['prior_intent'] ?? null;
        $region = $input['region'] ?? null;
        $unknown = is_array($input['unknown_tokens'] ?? null) ? $input['unknown_tokens'] : [];
        $productFromMemory = (bool) ($input['product_from_memory'] ?? false);
        $memoryUsed = (bool) ($input['memory_used'] ?? false)
            || ($memoryApplied !== null && $memoryApplied !== '')
            || $productFromMemory;

        $id = $this->resolveId($intent, $kind, $memoryApplied, $hasProduct);

        $meta = DialoguePatterns::CATALOG[$id] ?? [
            'label' => $id,
            'family' => 'other',
        ];

        $slots = [
            'has_product' => $hasProduct,
            'unknown_count' => count($unknown),
        ];
        if ($priorIntent !== null && $priorIntent !== '') {
            $slots['prior_intent'] = $priorIntent;
        }
        if ($memoryApplied !== null && $memoryApplied !== '') {
            $slots['memory_applied'] = $memoryApplied;
        }
        if ($productFromMemory) {
            $slots['product_from_memory'] = true;
        }
        if ($region !== null && $region !== '') {
            $slots['region'] = $region;
        }

        return [
            'id' => $id,
            'label' => $meta['label'],
            'family' => $meta['family'],
            'version' => DialoguePatterns::VERSION,
            'from_intent' => $intent,
            'memory' => $memoryUsed,
            'slots' => $slots,
        ];
    }

    private function resolveId(string $intent, string $kind, mixed $memoryApplied, bool $hasProduct): string
    {
        if ($memoryApplied === 'intent_carry') {
            return 'followup_carry';
        }

        return match ($intent) {
            'greeting' => 'open_greeting',
            'thanks' => 'close_thanks',
            'ack' => 'soft_ack',
            'price' => $hasProduct ? 'ask_price_on_offer' : 'ask_price_bare',
            'delivery' => 'ask_delivery',
            'order_status' => 'ask_order_status',
            'complaint' => 'raise_complaint',
            'unknown' => 'soft_clarify_unknown',
            default => $kind === 'social' ? 'general_social' : 'general_business',
        };
    }
}
