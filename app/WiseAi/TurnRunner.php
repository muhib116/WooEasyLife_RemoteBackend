<?php

namespace App\WiseAi;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;

/**
 * Minimal TurnRunner (P1–P9 stubs with real Admit / Observe / Ground / Judge / Deliver).
 */
class TurnRunner
{
    public function __construct(
        private DecideEngine $engine,
        private KnowledgeResolver $knowledge,
    ) {}

    /**
     * @return array{
     *     turn: WiseTurn,
     *     decision: array<string, mixed>,
     *     latency_ms: int
     * }
     */
    public function run(WiseApiKey $apiKey, IncomingTurn $turn): array
    {
        $startedAt = microtime(true);

        // P1 Admit — config snapshot sealed for this turn
        $configSnapshot = [
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'mode' => 'shadow',
            'api_key_id' => $apiKey->id,
            'sealed_at' => now()->toIso8601String(),
        ];

        $trace = [
            'P1_admit' => 'ok',
            'P2_observe' => null,
            'P3_ground' => null,
            'P7_judge' => null,
        ];

        // P2 Observe — classify intent
        $classified = $this->engine->classify($turn->text);
        $trace['P2_observe'] = [
            'intent' => $classified['intent'],
            'kind' => $classified['kind'],
            'confidence' => $classified['confidence'],
        ];

        $evidence = [];
        $gap = false;
        $decision = [
            'intent' => $classified['intent'],
            'confidence' => $classified['confidence'],
            'action' => 'needs_human',
            'suggested_reply' => null,
            'source' => 'pattern',
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'gap' => false,
        ];

        if ($classified['kind'] === 'social' && $classified['social_reply'] !== null) {
            // Social conversation — evidence-free replies allowed
            $decision['action'] = 'suggest_reply';
            $decision['suggested_reply'] = $classified['social_reply'];
            $decision['source'] = 'pattern';
            $trace['P3_ground'] = 'social_skip_knowledge';
            $trace['P7_judge'] = 'pass_social';
        } else {
            // P3 Ground — business facts need published knowledge
            $match = $this->knowledge->resolve($apiKey, $turn->text, $classified['intent']);
            if ($match) {
                $item = $match['item'];
                // Seal the answer text used for this turn so later edits don't rewrite history.
                $evidence = [
                    'knowledge_id' => $item->id,
                    'knowledge_version' => $item->version,
                    'knowledge_type' => $item->type,
                    'title' => $item->title,
                    'answer' => $item->answer,
                    'answer_hash' => hash('sha256', (string) $item->answer),
                    'match_score' => $match['score'],
                ];
                $decision['action'] = 'suggest_reply';
                $decision['suggested_reply'] = $item->answer;
                $decision['source'] = 'knowledge';
                $decision['confidence'] = min(98, $classified['confidence'] + 10);
                $trace['P3_ground'] = 'knowledge_hit';
                $trace['P7_judge'] = 'pass_evidence';
            } else {
                $gap = true;
                $decision['action'] = 'needs_human';
                $decision['suggested_reply'] = null;
                $decision['source'] = 'pattern';
                $decision['gap'] = true;
                $trace['P3_ground'] = 'knowledge_miss';
                $trace['P7_judge'] = 'fail_no_evidence';
            }
        }

        $decision['gap'] = $gap;
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        // P8 Deliver — persist turn (logging is mandatory)
        $record = WiseTurn::create([
            'wise_api_key_id' => $apiKey->id,
            'channel' => $turn->channel,
            'conversation_id' => $turn->conversationId,
            'text' => $turn->text,
            'payload' => [
                'text' => $turn->text,
                'channel' => $turn->channel,
                'conversation_id' => $turn->conversationId,
                'context' => $turn->context,
            ],
            'config_snapshot' => $configSnapshot,
            'decision' => $decision,
            'evidence' => $evidence ?: null,
            'trace' => $trace,
            'status' => 'ok',
            'gap' => $gap,
            'latency_ms' => $latencyMs,
        ]);

        $apiKey->recordUsage();

        return [
            'turn' => $record,
            'decision' => $decision,
            'latency_ms' => $latencyMs,
        ];
    }
}
