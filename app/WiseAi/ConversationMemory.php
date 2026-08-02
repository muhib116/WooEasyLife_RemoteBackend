<?php

namespace App\WiseAi;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseTurn;
use Illuminate\Support\Collection;

/**
 * Conversation memory v0 — recent turns for the same API key + conversation_id.
 *
 * Does not invent facts. Helps follow-ups inherit prior business intent and
 * enrich knowledge matching text. Customer/merchant long-term memory is later.
 */
class ConversationMemory
{
    public const RECENT_LIMIT = 5;

    /**
     * @return Collection<int, WiseTurn>
     */
    public function recent(WiseApiKey $apiKey, ?string $conversationId, int $limit = self::RECENT_LIMIT): Collection
    {
        if ($conversationId === null || trim($conversationId) === '') {
            return collect();
        }

        return WiseTurn::query()
            ->where('wise_api_key_id', $apiKey->id)
            ->where('conversation_id', $conversationId)
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'text', 'decision', 'evidence', 'gap', 'created_at']);
    }

    /**
     * Most recent prior turn with a business intent (for follow-up carry).
     *
     * @param  Collection<int, WiseTurn>  $recent
     * @return array{turn_id: int, text: string, intent: string}|null
     */
    public function priorBusiness(Collection $recent): ?array
    {
        foreach ($recent as $turn) {
            $intent = (string) ($turn->decision['intent'] ?? 'unknown');
            if (! in_array($intent, DecideEngine::BUSINESS_INTENTS, true)) {
                continue;
            }

            return [
                'turn_id' => (int) $turn->id,
                'text' => (string) ($turn->text ?? ''),
                'intent' => $intent,
            ];
        }

        return null;
    }

    /**
     * Active product subject from recent turns (decision.product_subject or product evidence).
     *
     * @param  Collection<int, WiseTurn>  $recent
     * @return array{knowledge_id: int, title: string, source: string}|null
     */
    public function activeProduct(Collection $recent): ?array
    {
        foreach ($recent as $turn) {
            $subject = $turn->decision['product_subject'] ?? null;
            if (is_array($subject) && ! empty($subject['title']) && (! empty($subject['knowledge_id']) || ! empty($subject['external_id']))) {
                return [
                    'knowledge_id' => isset($subject['knowledge_id']) ? (int) $subject['knowledge_id'] : null,
                    'title' => (string) $subject['title'],
                    'source' => 'memory',
                    'external_id' => isset($subject['external_id']) ? (string) $subject['external_id'] : null,
                ];
            }

            $evidence = $turn->evidence;
            if (is_array($evidence) && ($evidence['knowledge_type'] ?? '') === 'product' && ! empty($evidence['knowledge_id'])) {
                return [
                    'knowledge_id' => (int) $evidence['knowledge_id'],
                    'title' => (string) ($evidence['title'] ?? 'Product'),
                    'source' => 'memory_evidence',
                    'external_id' => null,
                ];
            }
        }

        return null;
    }

    /**
     * Short / pronoun-like messages that usually depend on prior turns.
     */
    public function isLikelyFollowUp(string $text): bool
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));

        if ($normalized === '') {
            return false;
        }

        // Very short utterances are often contextual.
        if (mb_strlen($normalized) <= 28) {
            $markers = [
                'ওটা', 'এটা', 'সেটা', 'ওইটা', 'আর', 'আবার', 'ওই',
                'that', 'this', 'same', 'again', 'what about',
                'কত', 'কতো', 'কি', 'কী', '??',
            ];
            foreach ($markers as $marker) {
                if (str_contains($normalized, $marker)) {
                    return true;
                }
            }

            // Bare question / filler
            if (preg_match('/^[?!.।]+$/u', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
