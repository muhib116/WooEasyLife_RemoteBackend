<?php

namespace App\WiseAi\Training;

use App\WiseAi\Language\LlmLanguageConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Optional AI assist to draft a training JSON pack (still imported as drafts only).
 */
class TrainingPackGenerator
{
    public function __construct(
        private LlmLanguageConfig $llm,
    ) {}

    /**
     * @return array{pack: array<string, mixed>, model: string, latency_ms: int, prompt_type: string, lanes_dropped: int}
     */
    public function generate(string $merchantBrief, int $targetItems = 0, string $promptType = TrainingPrompt::TYPE_MERCHANT): array
    {
        $apiKey = $this->llm->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('Wise LLM key missing. Save a key in Config → LLM Language, or set WISE_OPENAI_API_KEY.');
        }
        if (! $this->llm->enabled()) {
            throw new RuntimeException('LLM Language is disabled in Config. Enable it to generate training packs.');
        }

        $promptType = TrainingPrompt::normalizeType($promptType);
        // 0 / omitted → type TARGET. Never rewrite an explicit count (e.g. experience strong=16).
        if ($targetItems <= 0) {
            $targetItems = TrainingPrompt::recommendedTargetItems($promptType);
        }
        $targetItems = max(8, min(50, $targetItems));
        $model = $this->llm->model();
        $vol = TrainingPrompt::volumeFor($promptType);
        $briefLabel = in_array($promptType, [TrainingPrompt::TYPE_PLATFORM, TrainingPrompt::TYPE_LANGUAGE], true)
            ? 'Training brief'
            : 'Merchant brief';
        $user = "{$briefLabel}:\n".trim($merchantBrief)."\n\n"
            ."Generate a Wise training pack JSON with about {$targetItems} items "
            ."(minimum {$vol['min']} for usable training; target {$vol['target']} for proper training).\n"
            .'Prompt type: '.$promptType.".\n"
            .'Schema version must be '.TrainingSchema::VERSION.'.';

        // ~180 tokens/item headroom so TARGET/STRONG packs do not truncate mid-JSON.
        $maxTokens = max(3500, min(12000, $targetItems * 180));

        $started = microtime(true);
        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => TrainingPrompt::generatorSystem($promptType)],
                    ['role' => 'user', 'content' => TrainingPrompt::for($promptType)."\n\n".$user],
                ],
            ]);

        $latency = (int) round((microtime(true) - $started) * 1000);
        if (! $response->successful()) {
            throw new RuntimeException('OpenAI HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200));
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Empty model response.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Model did not return valid JSON.');
        }

        if (empty($decoded['version'])) {
            $decoded['version'] = TrainingSchema::VERSION;
        }
        if (! isset($decoded['items']) || ! is_array($decoded['items'])) {
            throw new RuntimeException('JSON missing items array.');
        }

        $filtered = TrainingPrompt::filterPack($decoded, $promptType);
        $decoded = $filtered['pack'];
        if ($decoded['items'] === []) {
            throw new RuntimeException(
                'Model returned no items for prompt type “'.$promptType.'” after lane filtering. Try again or paste JSON manually.'
            );
        }

        return [
            'pack' => $decoded,
            'model' => $model,
            'latency_ms' => $latency,
            'prompt_type' => $promptType,
            'lanes_dropped' => $filtered['dropped'],
        ];
    }
}
