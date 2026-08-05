<?php

namespace App\WiseAi\Knowledge;

use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use App\WiseAi\Language\LlmLanguageConfig;
use App\WiseAi\Language\LlmReplyGuard;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Propose a safer/natural Bangla rewrite of a knowledge draft answer.
 * Never persists — caller must Apply via updateKnowledge (stays draft) then Publish.
 */
final class KnowledgeAnswerRegenerator
{
    public function __construct(
        private LlmLanguageConfig $llm,
        private KnowledgeSeedValidator $validator = new KnowledgeSeedValidator,
        private LlmReplyGuard $guard = new LlmReplyGuard,
    ) {}

    /**
     * @return array{proposed_answer: string, original_answer: string, model: string, latency_ms: int}
     */
    public function propose(WiseKnowledgeItem $item): array
    {
        $apiKey = $this->llm->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('Wise LLM key missing. Save a key in Config → LLM Language, or set WISE_OPENAI_API_KEY.');
        }
        if (! $this->llm->enabled()) {
            throw new RuntimeException('LLM Language is disabled in Config. Enable it to regenerate answers.');
        }

        $original = trim((string) $item->answer);
        if ($original === '') {
            throw new RuntimeException('This knowledge item has no answer to regenerate.');
        }

        $scope = (string) ($item->scope ?: KnowledgeSchema::SCOPE_MERCHANT);
        $seededFrom = is_string($item->meta['seeded_from'] ?? null) ? (string) $item->meta['seeded_from'] : '';
        $region = is_string($item->meta['region'] ?? null) ? (string) $item->meta['region'] : '';

        $system = <<<'PROMPT'
You rewrite customer-facing Bangla knowledge answers for Wise AI (BD ecommerce).
Rules:
- Rewrite ONLY the answer text. Keep the same meaning and intent.
- Natural conversational Bangla. End with । ! or ?
- Include a clear next step (বলুন / পাঠান / জানাই / দেখে …).
- NEVER invent prices, fees, phone numbers, discounts, stock, or delivery days.
- NEVER claim you are connecting a human live or sending images if that is not in the original.
- Prefer clarify / handoff / verify wording over invented store facts.
- Return JSON only: {"answer":"..."}
PROMPT;

        $user = json_encode([
            'title' => $item->title,
            'question' => $item->question,
            'current_answer' => $original,
            'scope' => $scope,
            'region' => $region !== '' ? $region : null,
            'seeded_from' => $seededFrom !== '' ? $seededFrom : null,
            'type' => $item->type,
        ], JSON_UNESCAPED_UNICODE);

        $model = $this->llm->model();
        $started = microtime(true);
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.3,
                'max_tokens' => 800,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => (string) $user],
                ],
            ]);

        $latency = (int) round((microtime(true) - $started) * 1000);
        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200)
            );
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Empty model response.');
        }

        $decoded = json_decode($text, true);
        $proposed = is_array($decoded) ? trim((string) ($decoded['answer'] ?? '')) : '';
        if ($proposed === '') {
            throw new RuntimeException('Model did not return an answer field.');
        }
        if (mb_strlen($proposed) > 5000) {
            throw new RuntimeException('Proposed answer is too long.');
        }

        $factErrors = $this->validator->answerFactGuards($proposed, 'proposed');
        if ($factErrors !== []) {
            throw new RuntimeException('Proposed answer failed Trust-First fact guards: '.implode('; ', $factErrors));
        }

        if (! $this->guard->accepts($original, $proposed, [
            'title' => $item->title,
            'question' => $item->question,
            'answer' => $original,
        ])) {
            throw new RuntimeException('Proposed answer introduced new numeric facts — blocked by guard.');
        }

        return [
            'proposed_answer' => $proposed,
            'original_answer' => $original,
            'model' => $model,
            'latency_ms' => $latency,
        ];
    }
}
