<?php

namespace App\Services\LandingAi;

use App\Models\LandingAiJob;
use App\Services\LandingSettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Runs one landing page generation as an OpenAI background response.
 * The plugin polls the job; each poll asks OpenAI for the response status.
 */
class LandingAiGenerator
{
    private const API = 'https://api.openai.com/v1/responses';

    public function __construct(
        private LandingSettingsService $settings,
        private OpenAiModelCatalog $models,
        private ReferencePageFetcher $fetcher,
        private LandingAiSchema $schema,
        private LandingAiPrompt $prompt,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->settings->openaiApiKey());
    }

    /**
     * @param  array{store: array{brand: string, site_name: string, domain: string, layout: string}, products: list<array<string, mixed>>, context: string, reference_urls: list<string>, images: list<string>}  $input
     *
     * @throws LandingAiException
     */
    public function start(LandingAiJob $job, array $input): LandingAiJob
    {
        $key = $this->apiKey();

        $pages = $input['reference_urls'] !== [] ? $this->fetcher->fetchMany($input['reference_urls']) : [];
        $warnings = $job->warnings ?? [];
        foreach ($pages as $page) {
            if (! $page['ok']) {
                $warnings[] = 'Could not read '.$this->host($page['url']).': '.$page['error'].'.';
            }
        }

        $brief = $this->prompt->buildCopyBrief($input['store'], $input['products'], $input['context'], $pages);

        $content = [[
            'type' => 'input_text',
            'text' => $this->prompt->userMessage(
                $input['store'],
                $input['products'],
                $input['context'],
                $pages,
                count($input['images']),
                $brief,
            ),
        ]];
        foreach ($input['images'] as $dataUrl) {
            $content[] = ['type' => 'input_image', 'image_url' => $dataUrl, 'detail' => 'low'];
        }

        $instructions = $this->prompt->instructions($input['store']['brand'], $input['store']['layout']);
        $schema = $this->schema->jsonSchema($job->spec ?? ['fields' => [], 'lists' => []]);

        $model = $this->models->effectiveModel();
        $useReasoning = OpenAiModelCatalog::isReasoning($model);
        $response = $this->create($key, $this->body($job, $model, $instructions, $content, $schema, $useReasoning));

        if ($this->errorCode($response) === 'model_not_found') {
            $best = $this->models->bestModel();
            $model = $best !== $model ? $best : OpenAiModelCatalog::FALLBACK_MODELS[0];
            $useReasoning = OpenAiModelCatalog::isReasoning($model);
            $response = $this->create($key, $this->body($job, $model, $instructions, $content, $schema, $useReasoning));
        }

        if ($useReasoning && $response->status() === 400 && str_starts_with((string) $response->json('error.param', ''), 'reasoning')) {
            $response = $this->create($key, $this->body($job, $model, $instructions, $content, $schema, false));
        }

        if (! $response->successful()) {
            throw $this->errorFor($response);
        }

        $data = (array) $response->json();
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new LandingAiException('OpenAI did not accept the job. Please try again.', 502);
        }

        $job->fill([
            'status' => LandingAiJob::STATUS_RUNNING,
            'model' => $model,
            'openai_response_id' => $id,
            'warnings' => array_values(array_unique($warnings)),
            'request_meta' => array_merge($job->request_meta ?? [], [
                'pages_read' => count(array_filter($pages, fn ($page) => $page['ok'])),
                'pages_failed' => count(array_filter($pages, fn ($page) => ! $page['ok'])),
                'allowed_prices' => $brief['allowed_price_literals'] ?? [],
                'copy_brief' => [
                    'chosen_angle' => $brief['chosen_angle'] ?? '',
                    'review_mode' => $brief['review_mode'] ?? 'live',
                ],
            ]),
        ])->save();

        return $this->apply($job, $data);
    }

    /**
     * Ask OpenAI for the current state of an active job.
     */
    public function refresh(LandingAiJob $job): LandingAiJob
    {
        if (! $job->isActive()) {
            return $job;
        }

        $age = now()->getTimestamp() - ($job->created_at?->getTimestamp() ?? now()->getTimestamp());

        if (! $job->openai_response_id) {
            // start() runs inside the generate request; a job stuck here lost that request.
            return $age > 180 ? $this->fail($job, 'The AI did not start. Please try again.') : $job;
        }

        if ($age > (int) config('landing_ai.job_timeout_seconds', 900)) {
            $this->cancelRemote($job);

            return $this->fail($job, 'The AI took too long. Please try again.');
        }

        $key = $this->settings->openaiApiKey();
        if (! filled($key)) {
            return $this->fail($job, 'AI is not set up on the hub. Please contact WooEasyLife support.');
        }

        // Several editor tabs may poll the same job; ask OpenAI at most once per window.
        $window = max(1, (int) config('landing_ai.poll_cache_seconds', 2));
        if (! Cache::lock('landing_ai:poll:'.$job->id, $window)->get()) {
            return $job;
        }

        try {
            $response = Http::withToken((string) $key)
                ->acceptJson()
                ->timeout(20)
                ->connectTimeout(8)
                ->get(self::API.'/'.rawurlencode($job->openai_response_id));
        } catch (\Throwable) {
            return $job;
        }

        if ($response->status() === 404) {
            return $this->fail($job, 'The AI job was lost. Please try again.');
        }

        if (! $response->successful()) {
            return $job;
        }

        return $this->apply($job, (array) $response->json());
    }

    /**
     * @param  array<string, mixed>  $data  OpenAI response object
     */
    private function apply(LandingAiJob $job, array $data): LandingAiJob
    {
        $status = (string) ($data['status'] ?? '');

        if ($status === 'queued' || $status === 'in_progress' || $status === '') {
            return $job;
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $job->input_tokens = isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : $job->input_tokens;
        $job->output_tokens = isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : $job->output_tokens;

        if ($status === 'completed') {
            [$text, $refusal] = $this->outputText($data);

            if ($refusal !== '') {
                Log::info('landing_ai.refusal', ['job' => $job->uuid, 'refusal' => mb_substr($refusal, 0, 300)]);

                return $this->fail($job, 'The AI declined to write this page. Try different notes or reference pages.');
            }

            $decoded = json_decode($text, true);
            if (! is_array($decoded)) {
                return $this->fail($job, 'The AI answer could not be read. Please try again.');
            }

            $allowed = is_array($job->request_meta['allowed_prices'] ?? null) ? $job->request_meta['allowed_prices'] : [];
            $clean = $this->schema->sanitize(
                $job->spec ?? ['fields' => [], 'lists' => []],
                $decoded,
                ['allowed_price_literals' => $allowed],
            );
            if ($clean['settings'] === []) {
                return $this->fail($job, 'The AI returned no usable text. Please try again.');
            }

            $job->fill([
                'status' => LandingAiJob::STATUS_COMPLETED,
                'result' => ['settings' => $clean['settings'], 'notes' => $clean['notes']],
                'warnings' => array_values(array_unique([...($job->warnings ?? []), ...$clean['warnings']])),
                'error' => null,
                'finished_at' => now(),
            ])->save();

            return $job;
        }

        if ($status === 'incomplete') {
            $reason = (string) ($data['incomplete_details']['reason'] ?? '');

            return $this->fail($job, $reason === 'max_output_tokens'
                ? 'The AI answer was cut off. Try fewer reference pages or shorter notes.'
                : 'The AI stopped early. Please try again.');
        }

        if ($status === 'cancelled') {
            return $this->fail($job, 'The AI job was cancelled.', LandingAiJob::STATUS_CANCELLED);
        }

        Log::warning('landing_ai.response_failed', [
            'job' => $job->uuid,
            'code' => $data['error']['code'] ?? null,
            'message' => $this->scrub((string) ($data['error']['message'] ?? '')),
        ]);

        return $this->fail($job, 'The AI could not finish this page. Please try again.');
    }

    private function fail(LandingAiJob $job, string $message, string $status = LandingAiJob::STATUS_FAILED): LandingAiJob
    {
        $job->fill([
            'status' => $status,
            'error' => mb_substr($message, 0, 500),
            'finished_at' => now(),
        ])->save();

        return $job;
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function body(LandingAiJob $job, string $model, string $instructions, array $content, array $schema, bool $reasoning): array
    {
        $body = [
            'model' => $model,
            'instructions' => $instructions,
            'input' => [['role' => 'user', 'content' => $content]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'landing_page_copy',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
            'max_output_tokens' => OpenAiModelCatalog::maxOutputTokens($model),
            'background' => true,
            'store' => true,
            'metadata' => ['feature' => 'wel_landing', 'job' => (string) $job->uuid],
        ];

        if ($reasoning) {
            $body['reasoning'] = ['effort' => (string) config('landing_ai.reasoning_effort', 'low')];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws LandingAiException
     */
    private function create(string $key, array $body): Response
    {
        try {
            return Http::withToken($key)
                ->acceptJson()
                ->asJson()
                ->timeout(60)
                ->connectTimeout(10)
                ->post(self::API, $body);
        } catch (\Throwable $e) {
            Log::warning('landing_ai.openai_unreachable', ['message' => $this->scrub($e->getMessage())]);

            throw new LandingAiException('Could not reach OpenAI. Please try again in a minute.', 502);
        }
    }

    private function cancelRemote(LandingAiJob $job): void
    {
        $key = $this->settings->openaiApiKey();
        if (! filled($key) || ! $job->openai_response_id) {
            return;
        }

        try {
            Http::withToken((string) $key)
                ->acceptJson()
                ->timeout(10)
                ->post(self::API.'/'.rawurlencode($job->openai_response_id).'/cancel');
        } catch (\Throwable) {
            // The job is marked failed either way.
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string} [output text, refusal]
     */
    private function outputText(array $data): array
    {
        $text = '';
        $refusal = '';

        foreach ((array) ($data['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (! is_array($part)) {
                    continue;
                }
                if (($part['type'] ?? '') === 'output_text') {
                    $text .= (string) ($part['text'] ?? '');
                } elseif (($part['type'] ?? '') === 'refusal') {
                    $refusal .= (string) ($part['refusal'] ?? '');
                }
            }
        }

        return [$text, trim($refusal)];
    }

    private function errorCode(Response $response): string
    {
        return (string) ($response->json('error.code') ?? '');
    }

    private function errorFor(Response $response): LandingAiException
    {
        $status = $response->status();
        $code = $this->errorCode($response);

        Log::warning('landing_ai.openai_error', [
            'status' => $status,
            'code' => $code,
            'message' => $this->scrub((string) ($response->json('error.message') ?? '')),
        ]);

        if ($status === 401 || $status === 403) {
            return new LandingAiException('AI is not available right now. Please contact WooEasyLife support.', 503);
        }
        if ($code === 'insufficient_quota') {
            return new LandingAiException('AI credit on the hub is used up. Please contact WooEasyLife support.', 503);
        }
        if ($status === 429) {
            return new LandingAiException('AI is busy right now. Please try again in a minute.', 429);
        }
        if ($status >= 500) {
            return new LandingAiException('OpenAI is having trouble. Please try again in a minute.', 502);
        }

        return new LandingAiException('The AI could not accept this request. Try fewer photos or shorter notes.', 422);
    }

    /**
     * @throws LandingAiException
     */
    private function apiKey(): string
    {
        $key = $this->settings->openaiApiKey();
        if (! filled($key)) {
            throw new LandingAiException('AI is not set up on the hub yet. Please contact WooEasyLife support.', 503);
        }

        return (string) $key;
    }

    private function host(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
    }

    private function scrub(string $message): string
    {
        return mb_substr(preg_replace('/sk-[A-Za-z0-9_\-]{6,}/', 'sk-…', $message) ?? '', 0, 400);
    }
}
