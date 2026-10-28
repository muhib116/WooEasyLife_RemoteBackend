<?php

namespace App\Services\ReturnAi;

use App\Services\LandingAi\OpenAiModelCatalog;
use App\Services\LandingSettingsService;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * One strict-schema chat call per review (and one per judge check), with the
 * hub OpenAI key from Settings → AI. Returns the model answer as-is;
 * ReturnAiEngine applies the guards.
 */
class ReturnAiClient
{
    private const API = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private LandingSettingsService $landing,
        private ReturnAiSettings $settings,
        private ReturnAiPrompt $prompt,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->landing->openaiApiKey());
    }

    /**
     * @param  array<string, mixed>  $input  context, flags, promise, memory
     * @return array{data: array<string, mixed>, model: string, latency_ms: int, usage: array<string, int>}
     */
    public function decide(array $input): array
    {
        return $this->call(
            $this->prompt->system(),
            $this->prompt->user($input),
            $this->prompt->caseSchema(),
            'return_desk_case',
            (int) config('return_ai.max_tokens', 1400),
            (int) config('return_ai.timeout', 40),
        );
    }

    /**
     * @param  array<string, mixed>  $input  context, memory, proposal
     * @return array{data: array<string, mixed>, model: string, latency_ms: int, usage: array<string, int>}
     */
    public function judge(array $input): array
    {
        return $this->call(
            $this->prompt->judgeSystem(),
            $this->prompt->judgeUser($input),
            $this->prompt->judgeSchema(),
            'return_desk_judge',
            (int) config('return_ai.judge_max_tokens', 300),
            (int) config('return_ai.judge_timeout', 30),
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{data: array<string, mixed>, model: string, latency_ms: int, usage: array<string, int>}
     */
    private function call(string $system, string $user, array $schema, string $name, int $maxTokens, int $timeout): array
    {
        $key = (string) $this->landing->openaiApiKey();
        if ($key === '') {
            throw new ReturnAiException('AI is not set up on the hub yet.', 503);
        }

        $model = $this->settings->model();
        $effort = $name === 'return_desk_judge'
            ? (string) config('return_ai.judge_reasoning_effort', 'low')
            : (string) config('return_ai.reasoning_effort', 'medium');
        $started = microtime(true);
        $body = $this->payload($model, $maxTokens, $schema, $name, $system, $user, $effort);

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(max(5, $timeout))
                ->connectTimeout(8)
                ->post(self::API, $body);

            if ($response->status() === 400 && $this->shouldRetryWithoutReasoning($response->json('error.param'))) {
                $response = Http::withToken($key)
                    ->acceptJson()
                    ->timeout(max(5, $timeout))
                    ->connectTimeout(8)
                    ->post(self::API, $this->payload($model, $maxTokens, $schema, $name, $system, $user, null));
            }
        } catch (ConnectionException|TransferException) {
            throw new ReturnAiException('Could not reach OpenAI. Please try again.', 504);
        }

        $latency = (int) round((microtime(true) - $started) * 1000);

        if (! $response->successful()) {
            $status = $response->status();
            $message = match (true) {
                $status === 401 => 'OpenAI rejected the hub key.',
                $status === 429 => 'OpenAI is busy or out of credit. Please try again later.',
                default => 'OpenAI answered HTTP '.$status.'.',
            };
            throw new ReturnAiException($message, in_array($status, [401, 429], true) ? $status : 502);
        }

        $choice = (array) $response->json('choices.0', []);
        $message = (array) ($choice['message'] ?? []);
        if (! empty($message['refusal'])) {
            throw new ReturnAiException('The model refused this case.', 422);
        }

        $data = json_decode((string) ($message['content'] ?? ''), true);
        if (! is_array($data)) {
            throw new ReturnAiException(
                ($choice['finish_reason'] ?? '') === 'length' ? 'The answer was cut off.' : 'The model did not return JSON.',
                502,
            );
        }

        return [
            'data' => $data,
            'model' => (string) ($response->json('model') ?: $model),
            'latency_ms' => $latency,
            'usage' => [
                'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
                'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
            ],
        ];
    }

    /**
     * Reasoning models (gpt-5 and o-series) reject temperature and max_tokens.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function payload(string $model, int $maxTokens, array $schema, string $name, string $system, string $user, ?string $effort): array
    {
        $body = [
            'model' => $model,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => $name, 'strict' => true, 'schema' => $schema],
            ],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];

        if (OpenAiModelCatalog::isReasoning($model)) {
            $body['max_completion_tokens'] = $maxTokens;
            if ($effort !== null && $effort !== '') {
                $body['reasoning_effort'] = $effort;
            }
        } else {
            $body['temperature'] = 0;
            $body['max_tokens'] = $maxTokens;
        }

        return $body;
    }

    private function shouldRetryWithoutReasoning(mixed $param): bool
    {
        $param = (string) $param;

        return $param !== '' && (str_contains($param, 'reasoning') || $param === 'temperature' || $param === 'max_tokens' || $param === 'max_completion_tokens');
    }
}
