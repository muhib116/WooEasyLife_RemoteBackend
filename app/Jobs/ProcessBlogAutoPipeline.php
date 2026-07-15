<?php

namespace App\Jobs;

use App\Models\BlogAiRun;
use App\Models\BlogAiSession;
use App\Services\BlogAi\BlogAutoPipeline;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBlogAutoPipeline implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Never auto-retry the full pipeline — it is long and non-idempotent mid-image. */
    public int $tries = 1;

    public int $maxExceptions = 1;

    /** Full auto pipeline can include image retries. Must stay < QUEUE_RETRY_AFTER. */
    public int $timeout = 900;

    public int $uniqueFor = 1000;

    /** Prefer fail() over a second ghost attempt after a worker kill. */
    public bool $failOnTimeout = true;

    public function __construct(
        public int $runId,
        public ?string $jobToken = null,
    ) {}

    public function uniqueId(): string
    {
        $run = BlogAiRun::query()->find($this->runId);

        return 'blog-ai-'.($run?->blog_ai_session_id ?? $this->runId);
    }

    public function handle(BlogAutoPipeline $pipeline): void
    {
        $run = BlogAiRun::query()->find($this->runId);
        if (! $run || $run->isTerminal()) {
            return;
        }

        $session = BlogAiSession::query()->find($run->blog_ai_session_id);
        if (! $session) {
            $run->status = 'failed';
            $run->last_error = 'AI session missing.';
            $run->finished_at = now();
            $run->save();

            return;
        }

        if ($this->jobToken !== null && $this->jobToken !== '') {
            if (! hash_equals((string) $session->job_token, $this->jobToken)) {
                Log::info('Blog AI auto job skipped — token mismatch', [
                    'run_id' => $this->runId,
                ]);

                return;
            }
        }

        $pipeline->run($run);
    }

    public function failed(?Throwable $e): void
    {
        $run = BlogAiRun::query()->find($this->runId);
        if (! $run || $run->isTerminal()) {
            return;
        }

        $session = BlogAiSession::query()->find($run->blog_ai_session_id);
        $friendly = $this->friendlyFailureMessage($e);

        // Soft recover: draft already exists (common when cover image exceeds queue retry window).
        if ($session && $this->tokenAllows($session) && $this->hasUsableDraft($session)) {
            try {
                app(BlogAutoPipeline::class)->finalizeInterrupted($session, $run, $friendly);

                Log::info('Blog AI auto job recovered draft after interrupt', [
                    'run_id' => $this->runId,
                    'reason' => $friendly,
                ]);

                return;
            } catch (Throwable $recoverError) {
                Log::warning('Blog AI auto draft recovery failed', [
                    'run_id' => $this->runId,
                    'message' => $recoverError->getMessage(),
                ]);
            }
        }

        $run->status = 'failed';
        $run->last_error = $friendly;
        $run->finished_at = now();
        $run->appendLog([
            'step' => $run->current_step ?: 'pipeline',
            'event' => 'failed',
            'message' => $friendly,
        ]);
        $run->save();

        if ($session && $this->tokenAllows($session)) {
            $session->status = 'failed';
            $session->last_error = $friendly;
            $session->save();
        }
    }

    private function tokenAllows(BlogAiSession $session): bool
    {
        return $this->jobToken === null
            || $this->jobToken === ''
            || hash_equals((string) $session->job_token, (string) $this->jobToken);
    }

    private function hasUsableDraft(BlogAiSession $session): bool
    {
        $draft = is_array($session->draft_json) ? $session->draft_json : [];

        return filled($draft['title'] ?? null) && filled($draft['body_html'] ?? null);
    }

    private function friendlyFailureMessage(?Throwable $e): string
    {
        $raw = $e?->getMessage() ?: 'Auto pipeline job failed.';

        if ($e instanceof MaxAttemptsExceededException
            || str_contains(mb_strtolower($raw), 'attempted too many times')
            || str_contains(mb_strtolower($raw), 'timed out')
        ) {
            return 'Auto create was interrupted (queue worker timeout or premature retry). '
                .'Ensure QUEUE_RETRY_AFTER is at least 1000 and cron uses '
                .'`queue:work --stop-when-empty --max-jobs=1 --timeout=900` (not --max-time=55). '
                .'If a draft was already written, retry Auto Create or apply the draft from the session.';
        }

        return $raw;
    }
}
