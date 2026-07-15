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
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBlogAutoPipeline implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Full auto pipeline can include image retries. */
    public int $timeout = 900;

    public int $uniqueFor = 900;

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

        $run->status = 'failed';
        $run->last_error = $e?->getMessage() ?: 'Auto pipeline job failed.';
        $run->finished_at = now();
        $run->save();

        $session = BlogAiSession::query()->find($run->blog_ai_session_id);
        if ($session && ($this->jobToken === null || $this->jobToken === '' || hash_equals((string) $session->job_token, (string) $this->jobToken))) {
            $session->status = 'failed';
            $session->last_error = $run->last_error;
            $session->save();
        }
    }
}
