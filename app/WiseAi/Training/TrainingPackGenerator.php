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
     * @return array{pack: array<string, mixed>, model: string, latency_ms: int}
     */
    public function generate(string $merchantBrief, int $targetItems = 16): array
    {
        $apiKey = $this->llm->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('Wise LLM key missing. Save a key in Config → LLM Language, or set WISE_OPENAI_API_KEY.');
        }
        if (! $this->llm->enabled()) {
            throw new RuntimeException('LLM Language is disabled in Config. Enable it to generate training packs.');
        }

        $targetItems = max(8, min(30, $targetItems));
        $model = $this->llm->model();
        $user = "Merchant brief:\n".trim($merchantBrief)."\n\n"
            ."Generate a Wise training pack JSON with about {$targetItems} items.\n"
            .'Schema version must be '.TrainingSchema::VERSION.'.';

        $started = microtime(true);
        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.4,
                'max_tokens' => 3500,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => TrainingPrompt::generatorSystem()],
                    ['role' => 'user', 'content' => TrainingPrompt::professional()."\n\n".$user],
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

        return [
            'pack' => $decoded,
            'model' => $model,
            'latency_ms' => $latency,
        ];
    }
}
