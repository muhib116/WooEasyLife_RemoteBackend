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
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}, model: string}
     */
    public function chatJson(array $messages, float $temperature = 0.7, ?string $model = null): array
    {
        return $this->requestChat($messages, $temperature, $model);
    }

    /**
     * Multimodal chat with JSON object response (vision review).
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}, model: string}
     */
    public function chatJsonVision(array $messages, float $temperature = 0.2, ?string $model = null): array
    {
        return $this->requestChat(
            $messages,
            $temperature,
            $model ?: $this->landingSettings->openaiBlogPlanningModel(),
        );
    }

    /**
     * Light model: keyword suggest + title hooks (default openai_blog_model).
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}, model: string}
     */
    public function chatJsonLight(array $messages, float $temperature = 0.7): array
    {
        return $this->requestChat(
            $messages,
            $temperature,
            $this->landingSettings->openaiBlogModel(),
        );
    }

    /**
     * Mid-tier model: research, outline, competitor, step review, vision.
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}, model: string}
     */
    public function chatJsonPlanning(array $messages, float $temperature = 0.4): array
    {
        return $this->requestChat(
            $messages,
            $temperature,
            $this->landingSettings->openaiBlogPlanningModel(),
        );
    }

    /**
     * Prefer dedicated writing model when set (article draft / SEO body expand).
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}, model: string}
     */
    public function chatJsonWriting(array $messages, float $temperature = 0.55): array
    {
        return $this->requestChat(
            $messages,
            $temperature,
            $this->landingSettings->openaiBlogWritingModel(),
        );
    }

    /**
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array{content: string, usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}, model: string}
     */
    private function requestChat(array $messages, float $temperature, ?string $model = null): array
    {
        $apiKey = $this->landingSettings->openaiApiKey();
        if (! filled($apiKey)) {
            throw ValidationException::withMessages([
                'ai' => 'OpenAI API key is not configured. Set it in Landing Settings.',
            ]);
        }

        $resolved = $this->resolveChatModel($model);

        $response = Http::withToken($apiKey)
            ->timeout(180)
            ->connectTimeout(20)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $resolved,
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
            'model' => $resolved,
            'usage' => [
                'prompt_tokens' => (int) data_get($response->json(), 'usage.prompt_tokens', 0),
                'completion_tokens' => (int) data_get($response->json(), 'usage.completion_tokens', 0),
                'total_tokens' => (int) data_get($response->json(), 'usage.total_tokens', 0),
            ],
        ];
    }

    private function resolveChatModel(?string $model): string
    {
        $candidate = trim((string) ($model ?: ''));
        if ($candidate !== '' && in_array($candidate, LandingSettingsService::BLOG_MODELS, true)) {
            return $candidate;
        }

        return $this->landingSettings->openaiBlogModel() ?: 'gpt-4o-mini';
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
