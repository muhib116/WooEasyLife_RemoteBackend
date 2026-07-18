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
        return $this->requestChat($messages, $temperature);
    }

    /**
     * Multimodal chat with JSON object response (vision review).
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}}
     */
    public function chatJsonVision(array $messages, float $temperature = 0.2): array
    {
        return $this->requestChat($messages, $temperature);
    }

    /**
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}}
     */
    private function requestChat(array $messages, float $temperature): array
    {
        $apiKey = $this->landingSettings->openaiApiKey();
        if (! filled($apiKey)) {
            throw ValidationException::withMessages([
                'ai' => 'OpenAI API key is not configured. Set it in Landing Settings.',
            ]);
        }

        $model = $this->landingSettings->openaiBlogModel() ?: 'gpt-4o-mini';

        $response = Http::withToken($apiKey)
            ->timeout(150)
            ->connectTimeout(20)
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

    /**
     * Build a data-URL for vision messages from a local file or remote URL binary.
     */
    public function imageDataUrlFromBinary(string $binary, string $mime = 'image/png'): string
    {
        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    public function imageDataUrlFromPath(string $path): string
    {
        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'ai' => 'Could not read image for vision review.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary) ?: 'image/jpeg';
        if (! str_starts_with((string) $mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        return $this->imageDataUrlFromBinary($binary, (string) $mime);
    }
}
