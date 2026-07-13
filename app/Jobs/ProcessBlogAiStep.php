<?php

namespace App\Jobs;

use App\Models\BlogAiSession;
use App\Services\BlogAi\BlogContentAgent;
use App\Services\BlogAi\BlogImageAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessBlogAiStep implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Image + download can exceed 3 minutes. */
    public int $timeout = 300;

    public int $uniqueFor = 600;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $sessionId,
        public string $step,
        public array $payload = [],
        public ?string $jobToken = null,
    ) {}

    public function uniqueId(): string
    {
        return 'blog-ai-'.$this->sessionId;
    }

    public function handle(BlogContentAgent $agent): void
    {
        $session = BlogAiSession::query()->find($this->sessionId);
        if (! $session) {
            return;
        }

        if (! $this->ownsJob($session)) {
            Log::info('Blog AI job skipped — token mismatch', [
                'session_id' => $this->sessionId,
                'step' => $this->step,
            ]);

            return;
        }

        try {
            match ($this->step) {
                'research' => $this->runResearch($agent, $session),
                'hooks' => $agent->generateHooks($session),
                'outline' => $agent->generateOutline(
                    $session,
                    array_values($this->payload['selected_hook_ids'] ?? []),
                ),
                'draft' => $agent->generateDraft($session),
                'image' => app(BlogImageAgent::class)->generate($session),
                default => throw ValidationException::withMessages([
                    'ai' => 'Unknown AI step.',
                ]),
            };

            $session->refresh();
            if (! $this->ownsJob($session)) {
                return;
            }

            $session->last_error = null;
            $session->resume_status = null;
            $session->saveIfJobCurrent();
        } catch (Throwable $e) {
            $message = $e instanceof ValidationException
                ? (string) (collect($e->errors())->flatten()->first() ?: $e->getMessage())
                : $e->getMessage();

            Log::warning('Blog AI step failed', [
                'session_id' => $this->sessionId,
                'step' => $this->step,
                'message' => $message,
            ]);

            $this->markFailed($message !== '' ? $message : 'AI request failed.');

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $message = $e instanceof ValidationException
            ? (string) (collect($e->errors())->flatten()->first() ?: $e->getMessage())
            : ($e?->getMessage() ?: 'AI job failed (timeout or worker error).');

        $this->markFailed($message !== '' ? $message : 'AI job failed (timeout or worker error).');
    }

    private function markFailed(string $message): void
    {
        $session = BlogAiSession::query()->find($this->sessionId);
        if (! $session || ! $this->ownsJob($session)) {
            return;
        }

        if (! $session->isBusy() && $session->status === 'failed') {
            return;
        }

        $session->last_error = $message;
        $session->status = 'failed';
        $session->saveIfJobCurrent();
    }

    private function ownsJob(BlogAiSession $session): bool
    {
        if ($this->jobToken === null || $this->jobToken === '') {
            return true;
        }

        return hash_equals((string) $session->job_token, $this->jobToken);
    }

    private function runResearch(BlogContentAgent $agent, BlogAiSession $session): void
    {
        $pasted = $this->payload['pasted'] ?? ($session->keywords_json['pasted'] ?? []);
        if (! is_array($pasted)) {
            $pasted = [];
        }

        $research = $agent->researchKeywords(
            (string) ($session->seed_topic ?? ''),
            (string) ($session->cluster ?? 'general'),
            $pasted,
        );

        $session->keywords_json = [
            'pasted' => $pasted,
            'primary' => $research['primary'],
            'secondary' => $research['secondary'],
            'suggestions' => $research['suggestions'],
            'live_suggestions' => $research['live_suggestions'] ?? [],
            'cannibalization' => $research['cannibalization'],
        ];
        $session->addUsage($research['usage']);
        $session->status = 'keywords_ready';
        $session->saveIfJobCurrent();
    }
}
