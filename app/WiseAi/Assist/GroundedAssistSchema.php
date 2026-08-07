<?php

namespace App\WiseAi\Assist;

/**
 * Structured DTO / JSON contract for GroundedAssistEngine.
 */
final class GroundedAssistSchema
{
    public const PROMPT_VERSION = 'grounded-assist-v1';

    /**
     * @return array<string, mixed>
     */
    public static function emptyResult(string $reason = 'skipped'): array
    {
        return [
            'applied' => false,
            'reason' => $reason,
            'attempts' => 0,
            'score' => null,
            'confidence' => null,
            'reply' => null,
            'plan' => [],
            'reasoning' => null,
            'need_clarify' => false,
            'used_knowledge_ids' => [],
            'model' => null,
            'latency_ms' => 0,
            'prompt_version' => (string) config('wise_ai.grounded_assist.prompt_version', self::PROMPT_VERSION),
        ];
    }

    /**
     * Normalize raw model JSON into a safe assist candidate.
     *
     * @param  array<string, mixed>  $raw
     * @return array{
     *     reply: string,
     *     reasoning: string,
     *     plan: list<string>,
     *     need_clarify: bool,
     *     confidence: int,
     *     score: float,
     *     used_knowledge_ids: list<int>,
     *     intent_refined: string|null
     * }
     */
    public static function normalizeCandidate(array $raw, array $allowedKnowledgeIds = []): array
    {
        $plan = [];
        if (is_array($raw['plan'] ?? null)) {
            foreach ($raw['plan'] as $step) {
                if (is_string($step) && trim($step) !== '') {
                    $plan[] = mb_substr(trim($step), 0, 120);
                }
                if (count($plan) >= 3) {
                    break;
                }
            }
        }

        $ids = [];
        $rawIds = $raw['used_knowledge_ids'] ?? [];
        if (is_array($rawIds)) {
            $allowed = array_fill_keys(array_map('intval', $allowedKnowledgeIds), true);
            foreach ($rawIds as $id) {
                $id = (int) $id;
                if ($id > 0 && ($allowed === [] || isset($allowed[$id]))) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));

        $score = is_numeric($raw['score'] ?? null) ? (float) $raw['score'] : 0.0;
        $score = max(0.0, min(10.0, $score));
        $confidence = is_numeric($raw['confidence'] ?? null) ? (int) $raw['confidence'] : 0;
        $confidence = max(0, min(100, $confidence));

        $intent = isset($raw['intent_refined']) && is_string($raw['intent_refined'])
            ? mb_substr(trim($raw['intent_refined']), 0, 40)
            : null;

        return [
            'reply' => mb_substr(trim((string) ($raw['reply'] ?? '')), 0, 2000),
            'reasoning' => mb_substr(trim((string) ($raw['reasoning'] ?? '')), 0, 1000),
            'plan' => $plan,
            'need_clarify' => (bool) ($raw['need_clarify'] ?? false),
            'confidence' => $confidence,
            'score' => $score,
            'used_knowledge_ids' => $ids,
            'intent_refined' => $intent !== '' ? $intent : null,
        ];
    }

    public static function jsonInstruction(): string
    {
        return <<<'PROMPT'
You are Wise AI grounded assist for Bangladesh commerce chat.
Return ONLY JSON with keys:
- reasoning: string (internal; do not invent fees)
- plan: string[1..3] short steps e.g. empathize, answer, next_action
- reply: string — human conversational Bangla/English matching customer; no markdown; never sound like an AI
- need_clarify: boolean — true if product/offer/slot missing
- confidence: number 0-100
- score: number 0-10 quality of this reply
- used_knowledge_ids: int[] — only ids from evidence_pack
- intent_refined: string|null

Rules:
1. Use ONLY facts in evidence_pack / context_pack. Never invent price, delivery charge, stock, medical, or legal claims.
2. If evidence is insufficient for a factual ask, set need_clarify=true and ask a short clarifying question.
3. Mirror customer language; short natural sentences; emoji sparingly if customer used emoji.
4. Never say "Certainly" or lecture. No template robotic tone.
5. Digits in reply must appear in evidence_pack text (or ask without digits).
PROMPT;
    }
}
