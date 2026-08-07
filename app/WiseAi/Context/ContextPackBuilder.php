<?php

namespace App\WiseAi\Context;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Assist\ToolDecision;
use App\WiseAi\ConversationMemory;
use App\WiseAi\KnowledgeResolver;
use Illuminate\Support\Collection;

/**
 * Compress payload context + recent turns + retrieved brain chunks for grounded assist.
 * Never dumps full history into the model.
 */
final class ContextPackBuilder
{
    public function __construct(
        private ConversationMemory $memory,
        private KnowledgeResolver $knowledge,
        private ToolDecision $tools = new ToolDecision,
    ) {}

    /**
     * @param  array<string, mixed>  $language  LanguageNormalizer output
     * @param  array<string, mixed>  $classified
     * @param  array<string, mixed>|null  $productSubject
     * @param  array<string, mixed>  $turnContext  IncomingTurn.context
     * @return array{
     *     message: string,
     *     language: string|null,
     *     emotion: string|null,
     *     urgency: string|null,
     *     intent_hint: string,
     *     intent_confidence: int,
     *     product_subject: array<string, mixed>|null,
     *     thread: array<string, mixed>,
     *     funnel: array<string, mixed>,
     *     customer: array<string, mixed>,
     *     signals: array<string, mixed>,
     *     candidates: list<array<string, mixed>>,
     *     hints: array<string, mixed>,
     *     recent_messages: list<array{role: string, text: string}>,
     *     conversation_summary: string|null,
     *     goal: string|null,
     *     evidence_pack: list<array<string, mixed>>,
     *     tool_facts: list<array<string, mixed>>,
     *     rules_slice: list<string>,
     *     pack_meta: array<string, mixed>
     * }
     */
    public function build(
        WiseApiKey $apiKey,
        string $message,
        array $language,
        array $classified,
        ?array $productSubject,
        array $turnContext,
        ?string $conversationId,
    ): array {
        $recentLimit = (int) config('wise_ai.grounded_assist.recent_turns', 12);
        $maxChunks = (int) config('wise_ai.grounded_assist.max_chunks', 8);

        $recent = $this->memory->recent($apiKey, $conversationId, $recentLimit);
        $recentMessages = $this->formatRecent($recent);

        $thread = is_array($turnContext['thread'] ?? null) ? $turnContext['thread'] : [];
        $funnel = is_array($turnContext['funnel'] ?? null) ? $turnContext['funnel'] : [];
        $customer = is_array($turnContext['customer'] ?? null) ? $turnContext['customer'] : [];
        $signals = is_array($turnContext['signals'] ?? null) ? $turnContext['signals'] : [];
        $hints = is_array($turnContext['hints'] ?? null) ? $turnContext['hints'] : [];
        $candidates = is_array($turnContext['candidates'] ?? null) ? $turnContext['candidates'] : [];

        $summary = isset($thread['summary']) && is_string($thread['summary'])
            ? mb_substr(trim($thread['summary']), 0, (int) config('wise_ai.conversation_memory.summary_max_chars', 800))
            : null;
        $goal = isset($funnel['goal']) && is_string($funnel['goal'])
            ? mb_substr(trim($funnel['goal']), 0, 40)
            : null;

        // Persisted soft memory, then rollup from prior assist turns.
        if (($summary === null || $summary === '') && $conversationId) {
            $stored = \App\Models\WiseAi\WiseConversationMemory::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('conversation_id', $conversationId)
                ->first();
            if ($stored) {
                if (is_string($stored->summary) && trim($stored->summary) !== '') {
                    $summary = mb_substr(trim($stored->summary), 0, 800);
                }
                if (($goal === null || $goal === '') && is_string($stored->goal) && trim($stored->goal) !== '') {
                    $goal = mb_substr(trim($stored->goal), 0, 40);
                }
            }
        }

        if ($summary === null || $summary === '') {
            $rolled = $this->rollupSummary($recent);
            $summary = $rolled['summary'];
            $goal = $goal ?: $rolled['goal'];
        }

        $intent = (string) ($classified['intent'] ?? 'unknown');
        $chunks = $this->knowledge->topChunks(
            $apiKey,
            $message,
            $intent,
            $turnContext,
            $productSubject,
            $maxChunks,
        );

        $toolFacts = $this->tools->collect($turnContext);

        $emotion = isset($signals['emotion']) && is_string($signals['emotion'])
            ? $signals['emotion']
            : null;
        $urgency = isset($signals['urgency']) && is_string($signals['urgency'])
            ? $signals['urgency']
            : null;

        return [
            'message' => mb_substr($message, 0, 2000),
            'language' => isset($language['canonical']) ? (string) $language['canonical'] : null,
            'emotion' => $emotion,
            'urgency' => $urgency,
            'intent_hint' => $intent,
            'intent_confidence' => (int) ($classified['confidence'] ?? 0),
            'product_subject' => $productSubject,
            'thread' => [
                'summary' => $summary,
                'pending_question' => $thread['pending_question'] ?? null,
                'open_issues' => $thread['open_issues'] ?? [],
            ],
            'funnel' => [
                'stage' => $funnel['stage'] ?? null,
                'goal' => $goal,
                'product_confirmed' => (bool) ($funnel['product_confirmed'] ?? false),
            ],
            'customer' => [
                'external_id' => $customer['external_id'] ?? null,
                'tags' => is_array($customer['tags'] ?? null) ? array_values($customer['tags']) : [],
                'language_pref' => $customer['language_pref'] ?? null,
            ],
            'signals' => [
                'emotion' => $emotion,
                'urgency' => $urgency,
                'risk' => $signals['risk'] ?? null,
            ],
            'candidates' => array_values(array_slice($candidates, 0, 8)),
            'hints' => $hints,
            'recent_messages' => $recentMessages,
            'conversation_summary' => $summary,
            'goal' => $goal,
            'evidence_pack' => $chunks,
            'tool_facts' => $toolFacts,
            'rules_slice' => [
                'Never invent price, delivery charge, or stock.',
                'If offer/product unknown for a factual ask, clarify.',
                'Digits in reply must appear in evidence_pack or tool_facts.',
                'Mirror customer language; human conversational tone.',
            ],
            'pack_meta' => [
                'recent_count' => count($recentMessages),
                'chunk_count' => count($chunks),
                'tool_fact_count' => count($toolFacts),
                'offer_kind' => $turnContext['offer_kind'] ?? null,
                'region' => $turnContext['region'] ?? ($turnContext['locale_region'] ?? null),
                'platform' => $turnContext['platform'] ?? null,
            ],
        ];
    }

    /**
     * @param  Collection<int, WiseTurn>  $recent
     * @return list<array{role: string, text: string}>
     */
    private function formatRecent(Collection $recent): array
    {
        $out = [];
        foreach ($recent->reverse()->values() as $turn) {
            $text = trim((string) ($turn->text ?? ''));
            if ($text !== '') {
                $out[] = ['role' => 'customer', 'text' => mb_substr($text, 0, 300)];
            }
            $reply = trim((string) ($turn->decision['suggested_reply'] ?? ''));
            if ($reply !== '') {
                $out[] = ['role' => 'wise', 'text' => mb_substr($reply, 0, 300)];
            }
        }

        return array_slice($out, -24);
    }

    /**
     * @param  Collection<int, WiseTurn>  $recent
     * @return array{summary: string|null, goal: string|null}
     */
    private function rollupSummary(Collection $recent): array
    {
        foreach ($recent as $turn) {
            $assist = $turn->decision['grounded_assist'] ?? null;
            if (! is_array($assist)) {
                continue;
            }
            $summary = isset($assist['conversation_summary']) && is_string($assist['conversation_summary'])
                ? trim($assist['conversation_summary'])
                : '';
            $goal = isset($assist['goal']) && is_string($assist['goal'])
                ? trim($assist['goal'])
                : null;
            if ($summary !== '') {
                return ['summary' => mb_substr($summary, 0, 800), 'goal' => $goal];
            }
        }

        return ['summary' => null, 'goal' => null];
    }
}
