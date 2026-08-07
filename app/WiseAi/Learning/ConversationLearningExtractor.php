<?php

namespace App\WiseAi\Learning;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use Illuminate\Support\Facades\DB;

/**
 * Wave 3 — extract FAQ/objection candidates from high-quality assist turns.
 * Always draft; never publishes.
 */
final class ConversationLearningExtractor
{
    public const META_SOURCE = 'continuous_learning';

    public function __construct(
        private KnowledgeSeedValidator $validator = new KnowledgeSeedValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $pack
     */
    public function maybeDraft(WiseTurn $turn, WiseApiKey $apiKey, array $decision, array $pack): ?WiseKnowledgeItem
    {
        if (! config('wise_ai.continuous_learning.enabled', true)) {
            return null;
        }

        $assist = is_array($decision['grounded_assist'] ?? null) ? $decision['grounded_assist'] : [];
        if (empty($assist['applied'])) {
            return null;
        }

        $minScore = (float) config('wise_ai.continuous_learning.min_assist_score', 9.0);
        $score = isset($assist['score']) ? (float) $assist['score'] : 0.0;
        if ($score < $minScore) {
            return null;
        }

        if (! empty($assist['need_clarify'])) {
            return null;
        }

        if (empty($assist['passed_bar'])) {
            return null;
        }

        $question = trim((string) ($turn->text ?? ''));
        $answer = trim((string) ($decision['suggested_reply'] ?? ''));
        if ($question === '' || $answer === '' || mb_strlen($question) < 3) {
            return null;
        }

        $errors = $this->validator->answerFactGuards($answer, 'cl');
        if ($errors !== []) {
            return null;
        }

        if (preg_match('/\d+\s*(?:tk|taka|bdt|টাকা)/iu', $answer)) {
            return null;
        }

        return DB::transaction(function () use ($turn, $apiKey, $question, $answer, $score, $assist, $pack) {
            $existing = WiseKnowledgeItem::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('status', 'draft')
                ->where('type', KnowledgeSchema::KIND_FAQ)
                ->where('meta->source', self::META_SOURCE)
                ->where('question', $question)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            return WiseKnowledgeItem::create([
                'wise_api_key_id' => $apiKey->id,
                'type' => KnowledgeSchema::KIND_FAQ,
                'scope' => KnowledgeSchema::SCOPE_MERCHANT,
                'title' => mb_substr($question, 0, 120),
                'question' => $question,
                'answer' => $answer,
                'keywords' => [],
                'status' => 'draft',
                'version' => 1,
                'meta' => [
                    'source' => self::META_SOURCE,
                    'from_turn_id' => (int) $turn->id,
                    'assist_score' => $score,
                    'used_knowledge_ids' => $assist['used_knowledge_ids'] ?? [],
                    'chunk_count' => count($pack['evidence_pack'] ?? []),
                ],
            ]);
        });
    }
}
