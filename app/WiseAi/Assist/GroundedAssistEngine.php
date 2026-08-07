<?php

namespace App\WiseAi\Assist;

use App\WiseAi\Language\LlmLanguageConfig;
use App\WiseAi\Language\LlmReplyGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Grounded reason → plan → human reply → score, with retries.
 * Never invents fees; digits must appear in evidence pack.
 */
final class GroundedAssistEngine
{
    public function __construct(
        private LlmLanguageConfig $llm,
        private LlmReplyGuard $guard = new LlmReplyGuard,
        private ContradictionDetector $contradictions = new ContradictionDetector,
        private TrainingPackComposer $training = new TrainingPackComposer,
    ) {}

    /**
     * @param  array<string, mixed>  $pack  ContextPackBuilder output
     * @return array<string, mixed>  GroundedAssistSchema::emptyResult shape + applied fields
     */
    public function run(array $pack): array
    {
        $meta = GroundedAssistSchema::emptyResult('skipped');
        $meta['prompt_version'] = (string) config(
            'wise_ai.grounded_assist.prompt_version',
            GroundedAssistSchema::PROMPT_VERSION,
        );

        if (! $this->llm->enabled()) {
            $meta['reason'] = 'platform_off';

            return $meta;
        }
        $apiKey = $this->llm->apiKey();
        if ($apiKey === null) {
            $meta['reason'] = 'no_key';

            return $meta;
        }

        $contradictions = $this->contradictions->find(is_array($pack['evidence_pack'] ?? null) ? $pack['evidence_pack'] : []);
        if ($contradictions !== []) {
            $pack['contradictions'] = $contradictions;
            $pack['rules_slice'][] = 'Evidence has contradictory price-like values — clarify or avoid asserting a price.';
        }

        $allowedIds = [];
        foreach ($pack['evidence_pack'] ?? [] as $chunk) {
            if (is_array($chunk) && isset($chunk['id'])) {
                $allowedIds[] = (int) $chunk['id'];
            }
        }

        $evidenceForGuard = [
            'chunks' => $pack['evidence_pack'] ?? [],
            'tool_facts' => $pack['tool_facts'] ?? [],
            'product_subject' => $pack['product_subject'] ?? null,
        ];

        $maxAttempts = max(1, min(5, (int) config('wise_ai.grounded_assist.max_attempts', 3)));
        $minScore = (float) config('wise_ai.grounded_assist.min_score', 9.0);
        $minConfidence = (int) config('wise_ai.grounded_assist.min_confidence', 95);
        $model = $this->llm->model();
        $timeout = max(10, (int) config('wise_ai.grounded_assist.timeout_seconds', 45));

        $best = null;
        $attempts = [];
        $started = microtime(true);
        $priorFeedback = null;

        for ($i = 1; $i <= $maxAttempts; $i++) {
            try {
                $raw = $this->callOpenAi($apiKey, $model, $pack, $priorFeedback, $timeout);
            } catch (Throwable $e) {
                $attempts[] = ['n' => $i, 'error' => mb_substr($e->getMessage(), 0, 120)];
                $meta['reason'] = 'http_error';
                break;
            }

            $candidate = GroundedAssistSchema::normalizeCandidate($raw, $allowedIds);
            $candidate['attempt'] = $i;

            if ($candidate['reply'] === '') {
                $attempts[] = ['n' => $i, 'reject' => 'empty_reply'];
                $priorFeedback = 'Previous reply was empty. Provide a short human clarify or grounded answer.';
                continue;
            }

            if (! $this->guard->accepts('', $candidate['reply'], $evidenceForGuard)) {
                $attempts[] = ['n' => $i, 'reject' => 'fact_guard', 'score' => $candidate['score']];
                $priorFeedback = 'Reply invented digits/facts not in evidence_pack. Remove invented numbers or clarify without fees.';
                continue;
            }

            if ($contradictions !== [] && $this->assertsPrice($candidate['reply']) && ! $candidate['need_clarify']) {
                $attempts[] = ['n' => $i, 'reject' => 'contradiction', 'score' => $candidate['score']];
                $priorFeedback = 'Price evidence conflicts across knowledge rows. Ask which offer or avoid stating a price.';
                continue;
            }

            $attempts[] = [
                'n' => $i,
                'score' => $candidate['score'],
                'confidence' => $candidate['confidence'],
                'need_clarify' => $candidate['need_clarify'],
            ];

            if ($best === null
                || $candidate['score'] > (float) $best['score']
                || ($candidate['score'] === (float) $best['score'] && $candidate['confidence'] > (int) $best['confidence'])
            ) {
                $best = $candidate;
            }

            if ($candidate['score'] >= $minScore && $candidate['confidence'] >= $minConfidence) {
                break;
            }

            $priorFeedback = sprintf(
                'Score %.1f / confidence %d below bar (need >= %.1f and >= %d). Improve grounding and human tone.',
                $candidate['score'],
                $candidate['confidence'],
                $minScore,
                $minConfidence,
            );
        }

        $meta['latency_ms'] = (int) round((microtime(true) - $started) * 1000);
        $meta['model'] = $model;
        $meta['attempts'] = count($attempts);
        $meta['attempt_log'] = $attempts;
        $meta['contradictions'] = $contradictions;

        if ($best === null) {
            $meta['reason'] = $meta['reason'] === 'skipped' ? 'no_safe_candidate' : $meta['reason'];

            return $meta;
        }

        $meta['applied'] = true;
        $meta['reason'] = 'ok';
        $meta['reply'] = $best['reply'];
        $meta['plan'] = $best['plan'];
        $meta['reasoning'] = $best['reasoning'];
        $meta['need_clarify'] = $best['need_clarify'];
        $meta['confidence'] = $best['confidence'];
        $meta['score'] = $best['score'];
        $meta['used_knowledge_ids'] = $best['used_knowledge_ids'];
        $meta['intent_refined'] = $best['intent_refined'];
        $meta['passed_bar'] = $best['score'] >= $minScore && $best['confidence'] >= $minConfidence;

        return $meta;
    }

    /**
     * Test helper — normalize + guard without HTTP.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $pack
     * @return array<string, mixed>|null
     */
    public function acceptCandidate(array $raw, array $pack): ?array
    {
        $allowedIds = [];
        foreach ($pack['evidence_pack'] ?? [] as $chunk) {
            if (is_array($chunk) && isset($chunk['id'])) {
                $allowedIds[] = (int) $chunk['id'];
            }
        }
        $candidate = GroundedAssistSchema::normalizeCandidate($raw, $allowedIds);
        $evidenceForGuard = [
            'chunks' => $pack['evidence_pack'] ?? [],
            'tool_facts' => $pack['tool_facts'] ?? [],
        ];
        if ($candidate['reply'] === '' || ! $this->guard->accepts('', $candidate['reply'], $evidenceForGuard)) {
            return null;
        }

        return $candidate;
    }

    private function assertsPrice(string $reply): bool
    {
        return (bool) preg_match('/\d{2,}/u', $reply);
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array<string, mixed>
     */
    private function callOpenAi(
        string $apiKey,
        string $model,
        array $pack,
        ?string $priorFeedback,
        int $timeout,
    ): array {
        $userPayload = [
            'training' => $this->training->compose($pack),
            'context_pack' => $pack,
            'retry_feedback' => $priorFeedback,
        ];

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.35,
                'max_tokens' => 700,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => GroundedAssistSchema::jsonInstruction()],
                    ['role' => 'user', 'content' => (string) json_encode($userPayload, JSON_UNESCAPED_UNICODE)],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenAI HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 160)
            );
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Empty grounded assist response.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Grounded assist response was not JSON.');
        }

        return $decoded;
    }
}
