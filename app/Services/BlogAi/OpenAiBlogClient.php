<?php

namespace App\Services\BlogAi;

use App\Services\LandingSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OpenAiBlogClient
{
    public function __construct(
        private LandingSettingsService $landingSettings,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}}
     */
    public function chatJson(array $messages, float $temperature = 0.7): array
    {
        $apiKey = $this->landingSettings->openaiApiKey();
        if (! filled($apiKey)) {
            throw ValidationException::withMessages([
                'ai' => 'OpenAI API key is not configured. Set it in Landing Settings.',
            ]);
        }

        $model = $this->landingSettings->openaiBlogModel() ?: 'gpt-4o-mini';

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => $temperature,
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            $detail = $response->json('error.message') ?: $response->body();
            throw ValidationException::withMessages([
                'ai' => 'OpenAI request failed: '.Str::limit((string) $detail, 240),
            ]);
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        if ($content === '') {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => (int) data_get($response->json(), 'usage.prompt_tokens', 0),
                'completion_tokens' => (int) data_get($response->json(), 'usage.completion_tokens', 0),
                'total_tokens' => (int) data_get($response->json(), 'usage.total_tokens', 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJsonObject(string $content): array
    {
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'ai' => 'AI returned invalid JSON.',
            ]);
        }

        return $decoded;
    }
}
