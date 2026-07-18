<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBlogAiStep;
use App\Jobs\ProcessBlogAutoPipeline;
use App\Models\BlogAiRun;
use App\Models\BlogAiSession;
use App\Services\BlogAi\BlogContentAgent;
use App\Services\BlogAi\BlogImagePipeline;
use App\Services\BlogAi\BlogLearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BlogAiController extends Controller
{
    public function options(Request $request, BlogLearningService $learning): JsonResponse
    {
        $activeRunId = null;
        if ($request->user()) {
            $activeRunId = BlogAiRun::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('status', BlogAiRun::ACTIVE_STATUSES)
                ->orderByDesc('id')
                ->value('id');
        }

        return response()->json([
            'enabled' => (bool) config('blog_ai.enabled', true),
            'queue' => $this->shouldQueue(),
            'image_enabled' => (bool) config('blog_ai.image_enabled', true),
            'clusters' => config('blog_ai.clusters', []),
            'default_locale' => config('blog_ai.default_locale', 'bn'),
            'author_name' => config('blog_ai.author_name'),
            'hooks_count' => (int) config('blog_ai.hooks_count', 10),
            'require_pasted_keywords' => (bool) config('blog_ai.require_pasted_keywords', true),
            'min_pasted_keywords' => (int) config('blog_ai.min_pasted_keywords', 1),
            'learning' => $learning->promptLearningBlock(),
            'auto' => [
                'enabled' => (bool) config('blog_ai.auto.enabled', true),
                'create_post' => (bool) config('blog_ai.auto.create_post', true),
                'pass_score' => (int) config('blog_ai.auto.pass_score', 70),
                'soft_pass_score_cap' => (int) config('blog_ai.auto.soft_pass_score_cap', 59),
                'require_queue' => $this->autoRequiresQueue(),
                'one_active_run_per_user' => (bool) config('blog_ai.auto.one_active_run_per_user', true),
                'generate_image' => (bool) config('blog_ai.auto.generate_image', false),
                'auto_approve_image_on_fail' => (bool) config('blog_ai.auto.auto_approve_image_on_fail', true),
                'active_run_id' => $activeRunId ? (int) $activeRunId : null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $this->enforceDailyCaps($request, creatingSession: true);

        $validated = $request->validate([
            'cluster' => ['nullable', 'string', Rule::in(array_keys(config('blog_ai.clusters', [])))],
            'seed_topic' => ['nullable', 'string', 'max:255'],
            'keywords_text' => ['required', 'string', 'max:2000'],
        ]);

        $pasted = $this->parseKeywordsText($validated['keywords_text']);
        $this->assertPastedKeywords($pasted);

        $session = BlogAiSession::query()->create([
            'user_id' => $request->user()->id,
            'status' => 'started',
            'locale' => config('blog_ai.default_locale', 'bn'),
            'cluster' => $validated['cluster'] ?? 'general',
            'seed_topic' => $validated['seed_topic'] ?? null,
            'keywords_json' => [
                'pasted' => $pasted,
                'primary' => $pasted[0] ?? null,
                'secondary' => array_slice($pasted, 1),
            ],
        ]);

        return response()->json([
            'session' => $session->toAdminArray(),
        ], 201);
    }

    /**
     * Prefill keyword box from cluster + seed (Google Suggest BD + OpenAI). No session required.
     */
    public function suggestKeywords(Request $request, BlogContentAgent $agent): JsonResponse
    {
        $this->ensureEnabled();
        $this->enforceDailyCaps($request);

        $validated = $request->validate([
            'cluster' => ['required', 'string', Rule::in(array_keys(config('blog_ai.clusters', [])))],
            'seed_topic' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $agent->suggestSeedKeywords(
                (string) ($validated['seed_topic'] ?? ''),
                (string) $validated['cluster'],
            );
        } catch (Throwable $e) {
            $message = $e instanceof ValidationException
                ? (collect($e->errors())->flatten()->first() ?: $e->getMessage())
                : $e->getMessage();

            throw ValidationException::withMessages([
                'ai' => is_string($message) && $message !== '' ? $message : 'Keyword generation failed.',
            ]);
        }

        $tokens = (int) ($result['usage']['total_tokens'] ?? 0);
        BlogAiSession::recordUserDailyUsage((int) $request->user()->id, 1, $tokens);

        return response()->json([
            'keywords' => $result['keywords'],
            'keywords_text' => implode("\n", $result['keywords']),
            'live_suggestions' => $result['live_suggestions'],
            'usage' => $result['usage'],
        ]);
    }

    public function regenerateSeoChecklist(Request $request, \App\Services\BlogAi\BlogSeoChecklistRegenerator $regenerator): JsonResponse
    {
        // Shared hosting often defaults to 30–60s; OpenAI SEO edits need more headroom.
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $this->ensureEnabled();
        $this->enforceDailyCaps($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'focus_keyword' => ['required', 'string', 'max:190'],
            'body_html' => ['required', 'string', 'max:200000'],
            'slug' => ['nullable', 'string', 'max:190'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'faqs_json' => ['nullable', 'array', 'max:20'],
            'faqs_json.*.q' => ['nullable', 'string', 'max:500'],
            'faqs_json.*.a' => ['nullable', 'string', 'max:2000'],
            'secondary_keywords' => ['nullable', 'array', 'max:20'],
            'secondary_keywords.*' => ['nullable', 'string', 'max:190'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:10'],
            'cluster' => ['nullable', 'string', 'max:80'],
            'ignore_post_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (($validated['cluster'] ?? null) === '') {
            $validated['cluster'] = null;
        }

        try {
            $result = $regenerator->regenerate($validated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('SEO checklist regenerate failed', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            $message = $e->getMessage();
            throw ValidationException::withMessages([
                'ai' => is_string($message) && $message !== '' ? $message : 'SEO regenerate failed.',
            ]);
        }

        $tokens = (int) ($result['usage']['total_tokens'] ?? 0);
        // Only count against the daily AI cap when OpenAI was actually called.
        if ($tokens > 0 || ! empty($result['usage'])) {
            BlogAiSession::recordUserDailyUsage((int) $request->user()->id, 1, $tokens);
        }

        return response()->json($result);
    }

    public function show(BlogAiSession $blogAiSession): JsonResponse
    {
        $this->authorizeSession($blogAiSession);
        $blogAiSession->recoverIfStale();

        return response()->json([
            'session' => $blogAiSession->fresh()->toAdminArray(),
        ]);
    }

    public function recover(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->authorizeSession($blogAiSession);
        $recovered = $blogAiSession->recoverIfStale();

        if (! $recovered && $blogAiSession->isBusy()) {
            // Invalidate token so any in-flight job cannot overwrite after unlock.
            $blogAiSession->invalidateJobToken();
            $blogAiSession->last_error = 'Manually unlocked. Previous job writes will be ignored.';
            $blogAiSession->status = 'failed';
            $blogAiSession->save();
        }

        return response()->json([
            'session' => $blogAiSession->fresh()->toAdminArray(),
        ]);
    }

    public function draft(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeSession($blogAiSession);
        $this->enforceDailyCaps($request);

        if (! is_array($blogAiSession->outline_json) || $blogAiSession->outline_json === []) {
            throw ValidationException::withMessages([
                'outline' => 'Generate an outline first.',
            ]);
        }

        return $this->beginStep($blogAiSession, 'generating_draft', 'outline_ready', 'draft', []);
    }

    public function image(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeSession($blogAiSession);
        $this->enforceDailyCaps($request);

        if (! config('blog_ai.image_enabled', true)) {
            throw ValidationException::withMessages([
                'ai' => 'Blog AI image generation is disabled.',
            ]);
        }

        if (! is_array($blogAiSession->draft_json) || $blogAiSession->draft_json === []) {
            throw ValidationException::withMessages([
                'draft' => 'Generate a draft first.',
            ]);
        }

        return $this->beginStep($blogAiSession, 'generating_image', 'draft_ready', 'image', []);
    }

    public function regenerateImage(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeSession($blogAiSession);
        $this->enforceDailyCaps($request);

        if (! config('blog_ai.image_enabled', true)) {
            throw ValidationException::withMessages([
                'ai' => 'Blog AI image generation is disabled.',
            ]);
        }

        if (! is_array($blogAiSession->draft_json) || $blogAiSession->draft_json === []) {
            throw ValidationException::withMessages([
                'draft' => 'Generate a draft first.',
            ]);
        }

        if (! in_array($blogAiSession->status, ['image_ready', 'image_needs_fix', 'draft_ready', 'failed'], true)) {
            throw ValidationException::withMessages([
                'ai' => 'Generate or finish the draft before regenerating an image.',
            ]);
        }

        $resume = in_array($blogAiSession->status, ['image_ready', 'image_needs_fix'], true)
            ? $blogAiSession->status
            : 'draft_ready';

        return $this->beginStep($blogAiSession, 'generating_image', $resume, 'image', []);
    }

    public function approveImage(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeSession($blogAiSession);

        if ($blogAiSession->status !== 'image_needs_fix') {
            throw ValidationException::withMessages([
                'ai' => 'Only images marked as needing fix can be force-approved.',
            ]);
        }

        try {
            app(BlogImagePipeline::class)->approve($blogAiSession);
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'session' => $blogAiSession->fresh()->toAdminArray(),
        ]);
    }

    public function research(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeSession($blogAiSession);
        $this->enforceDailyCaps($request);

        $validated = $request->validate([
            'seed_topic' => ['nullable', 'string', 'max:255'],
            'cluster' => ['nullable', 'string', Rule::in(array_keys(config('blog_ai.clusters', [])))],
            'keywords_text' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('seed_topic', $validated)) {
            $blogAiSession->seed_topic = $validated['seed_topic'];
        }
        if (! empty($validated['cluster'])) {
            $blogAiSession->cluster = $validated['cluster'];
        }

        $pasted = $this->parseKeywordsText($validated['keywords_text'] ?? '');
        if ($pasted === [] && is_array($blogAiSession->keywords_json)) {
            $pasted = $blogAiSession->keywords_json['pasted'] ?? [];
        }
        $this->assertPastedKeywords($pasted);

        $blogAiSession->keywords_json = array_merge($blogAiSession->keywords_json ?? [], [
            'pasted' => $pasted,
        ]);

        return $this->beginStep($blogAiSession, 'researching', 'started', 'research', [
            'pasted' => $pasted,
        ]);
    }

    public function hooks(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeSession($blogAiSession);
        $this->enforceDailyCaps($request);

        if (! in_array($blogAiSession->status, ['keywords_ready', 'hooks_ready', 'failed', 'outline_ready', 'draft_ready', 'image_ready', 'image_needs_fix'], true)
            && empty($blogAiSession->keywords_json['primary'] ?? null)
            && empty($blogAiSession->keywords_json['pasted'] ?? null)) {
            throw ValidationException::withMessages([
                'ai' => 'Run keyword research first.',
            ]);
        }

        return $this->beginStep($blogAiSession, 'generating_hooks', 'keywords_ready', 'hooks', []);
    }

    public function outline(Request $request, BlogAiSession $blogAiSession): JsonResponse
    {
        $this->ensureEnabled();
        $this->authorizeSession($blogAiSession);
        $this->enforceDailyCaps($request);

        $validated = $request->validate([
            'selected_hook_ids' => ['required', 'array', 'min:1', 'max:3'],
            'selected_hook_ids.*' => ['required', 'string', 'max:32'],
        ]);

        $blogAiSession->selected_hook_ids = $validated['selected_hook_ids'];

        return $this->beginStep($blogAiSession, 'generating_outline', 'hooks_ready', 'outline', [
            'selected_hook_ids' => $validated['selected_hook_ids'],
        ]);
    }

    /**
     * One-click auto pipeline: research → review → … → draft (+ optional CMS draft post).
     */
    public function startAuto(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        if (! config('blog_ai.auto.enabled', true)) {
            throw ValidationException::withMessages([
                'ai' => 'Blog AI auto mode is disabled.',
            ]);
        }

        $this->enforceDailyCaps($request, creatingSession: true);
        $this->assertAutoQueueReady();
        $this->assertNoActiveAutoRun((int) $request->user()->id);

        $validated = $request->validate([
            'cluster' => ['nullable', 'string', Rule::in(array_keys(config('blog_ai.clusters', [])))],
            'seed_topic' => ['nullable', 'string', 'max:255'],
            'keywords_text' => ['nullable', 'string', 'max:2000'],
            'create_post' => ['nullable', 'boolean'],
        ]);

        $pasted = $this->parseKeywordsText($validated['keywords_text'] ?? '');
        // Auto mode may start with empty keywords (intake generates them). Manual store still requires paste.

        $lock = Cache::lock('blog-ai-auto-start-'.$request->user()->id, 20);
        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'ai' => 'Another auto create is starting. Wait a moment.',
            ]);
        }

        try {
            $token = (string) Str::uuid();

            $result = DB::transaction(function () use ($request, $validated, $pasted, $token) {
                $session = BlogAiSession::query()->create([
                    'user_id' => $request->user()->id,
                    'status' => 'auto_running',
                    'locale' => config('blog_ai.default_locale', 'bn'),
                    'cluster' => $validated['cluster'] ?? null,
                    'seed_topic' => $validated['seed_topic'] ?? null,
                    'job_token' => $token,
                    'keywords_json' => [
                        'pasted' => $pasted,
                        'primary' => $pasted[0] ?? null,
                        'secondary' => array_slice($pasted, 1),
                    ],
                ]);

                $createPost = array_key_exists('create_post', $validated)
                    ? (bool) $validated['create_post']
                    : (bool) config('blog_ai.auto.create_post', true);

                $run = BlogAiRun::query()->create([
                    'blog_ai_session_id' => $session->id,
                    'user_id' => $request->user()->id,
                    'mode' => 'auto',
                    'status' => 'pending',
                    'current_step' => 'queued',
                    'progress_pct' => 0,
                    'live_score' => 0,
                    'step_log' => [[
                        'at' => now()->toIso8601String(),
                        'step' => 'queued',
                        'event' => 'created',
                        'message' => 'Auto run queued.',
                    ]],
                    'revision_counts' => [],
                    'input_json' => [
                        'cluster' => $validated['cluster'] ?? null,
                        'seed_topic' => $validated['seed_topic'] ?? null,
                        'keywords_pasted' => $pasted,
                        'create_post' => $createPost,
                    ],
                ]);

                return compact('session', 'run', 'createPost');
            });

            /** @var BlogAiSession $session */
            $session = $result['session'];
            /** @var BlogAiRun $run */
            $run = $result['run'];

            if ($request->hasSession()) {
                $request->session()->save();
            }

            if ($this->shouldQueue()) {
                ProcessBlogAutoPipeline::dispatch($run->id, $token);

                return response()->json([
                    'queued' => true,
                    'run' => $run->fresh()->toAdminArray(),
                    'session' => $session->fresh()->toAdminArray(),
                ], 202);
            }

            try {
                ProcessBlogAutoPipeline::dispatchSync($run->id, $token);
            } catch (Throwable $e) {
                $message = $e instanceof ValidationException
                    ? (collect($e->errors())->flatten()->first() ?: $e->getMessage())
                    : $e->getMessage();

                throw ValidationException::withMessages([
                    'ai' => is_string($message) && $message !== '' ? $message : 'Auto pipeline failed.',
                ]);
            }

            $run = $run->fresh();
            $session = $session->fresh();

            if ($run->status === 'failed') {
                throw ValidationException::withMessages([
                    'ai' => $run->last_error ?: 'Auto pipeline failed.',
                ]);
            }

            return response()->json([
                'queued' => false,
                'run' => $run->toAdminArray(),
                'session' => $session->toAdminArray(),
                'post_id' => $run->blog_post_id,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function showRun(Request $request, BlogAiRun $blogAiRun): JsonResponse
    {
        if ((int) $blogAiRun->user_id !== (int) $request->user()?->id) {
            abort(403);
        }

        // Release session lock early so frequent polls don't pile up behind each other.
        if ($request->hasSession()) {
            $request->session()->save();
        }
        if (function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $session = $blogAiRun->session;
        if ($session) {
            $session->recoverIfStale();
            if ($session->status === 'failed' && $blogAiRun->status === 'running') {
                $blogAiRun->status = 'failed';
                $blogAiRun->last_error = $session->last_error ?: 'Session failed.';
                $blogAiRun->finished_at = now();
                $blogAiRun->save();
            }
        }

        $run = $blogAiRun->fresh();
        $queueHint = null;
        if ($run && $run->status === 'pending') {
            $ageSeconds = $run->created_at ? $run->created_at->diffInSeconds(now()) : 0;
            if ($ageSeconds >= 45) {
                $pendingJobs = null;
                try {
                    if (config('queue.default') === 'database') {
                        $pendingJobs = (int) DB::table(config('queue.connections.database.table', 'jobs'))->count();
                    }
                } catch (Throwable) {
                    $pendingJobs = null;
                }

                $queueHint = 'Still queued after '.$ageSeconds.'s. '
                    .'Ensure cPanel cron runs: php artisan queue:work database --stop-when-empty --max-jobs=1 --timeout=900 '
                    .'and .env has QUEUE_CONNECTION=database, BLOG_AI_QUEUE=true, QUEUE_RETRY_AFTER=1000.';
                if ($pendingJobs !== null) {
                    $queueHint .= ' Jobs in queue table: '.$pendingJobs.'.';
                }
            }
        }

        return response()->json([
            'run' => $run->toAdminArray(),
            'session' => $session?->fresh()?->toAdminArray(),
            'queue_hint' => $queueHint,
        ]);
    }

    public function cancelRun(Request $request, BlogAiRun $blogAiRun): JsonResponse
    {
        if ((int) $blogAiRun->user_id !== (int) $request->user()?->id) {
            abort(403);
        }

        if ($blogAiRun->isTerminal()) {
            return response()->json([
                'run' => $blogAiRun->toAdminArray(),
                'session' => $blogAiRun->session?->toAdminArray(),
            ]);
        }

        $session = $blogAiRun->session;
        if ($session) {
            $session->invalidateJobToken();
            $session->status = 'failed';
            $session->last_error = 'Cancelled by admin.';
            $session->save();
        }

        $blogAiRun->status = 'cancelled';
        $blogAiRun->last_error = 'Cancelled by admin.';
        $blogAiRun->finished_at = now();
        $blogAiRun->appendLog([
            'step' => $blogAiRun->current_step ?: 'pipeline',
            'event' => 'cancelled',
            'message' => 'Cancelled by admin.',
        ]);
        $blogAiRun->save();

        $this->purgeQueuedAutoJobs((int) $blogAiRun->id);

        return response()->json([
            'run' => $blogAiRun->fresh()->toAdminArray(),
            'session' => $session?->fresh()?->toAdminArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function beginStep(
        BlogAiSession $session,
        string $busyStatus,
        string $resumeFallback,
        string $step,
        array $payload,
    ): JsonResponse {
        $lock = Cache::lock('blog-ai-dispatch-'.$session->id, 15);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'ai' => 'Another AI request is starting for this session. Wait a moment.',
            ]);
        }

        try {
            $pending = $session->getDirty();
            $token = null;

            /** @var BlogAiSession $locked */
            $locked = DB::transaction(function () use ($session, $busyStatus, $resumeFallback, $pending, &$token) {
                $locked = BlogAiSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

                if ($pending !== []) {
                    $locked->fill($pending);
                }

                $locked->recoverIfStale();
                $locked->refresh();

                if ($pending !== []) {
                    $locked->fill($pending);
                }

                if ($locked->isBusy()) {
                    throw ValidationException::withMessages([
                        'ai' => 'This session already has an AI job running. Wait for it to finish, or unlock it.',
                    ]);
                }

                $token = (string) Str::uuid();
                $locked->resume_status = $this->goodStatusBefore($locked, $resumeFallback);
                $locked->job_token = $token;
                $locked->status = $busyStatus;
                $locked->last_error = null;
                $locked->save();

                return $locked;
            });

            return $this->dispatchOrRun($locked, $step, $payload, (string) $token);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchOrRun(
        BlogAiSession $session,
        string $step,
        array $payload,
        string $jobToken,
    ): JsonResponse {
        if ($this->shouldQueue()) {
            ProcessBlogAiStep::dispatch($session->id, $step, $payload, $jobToken);

            return response()->json([
                'queued' => true,
                'session' => $session->fresh()->toAdminArray(),
            ]);
        }

        try {
            ProcessBlogAiStep::dispatchSync($session->id, $step, $payload, $jobToken);
        } catch (Throwable $e) {
            $session->refresh();
            $message = $e instanceof ValidationException
                ? (collect($e->errors())->flatten()->first() ?: $e->getMessage())
                : $e->getMessage();

            throw ValidationException::withMessages([
                'ai' => is_string($message) ? $message : 'AI request failed.',
            ]);
        }

        $session = $session->fresh();
        if ($session->status === 'failed') {
            throw ValidationException::withMessages([
                'ai' => $session->last_error ?: 'AI request failed.',
            ]);
        }

        return response()->json([
            'queued' => false,
            'session' => $session->toAdminArray(),
        ]);
    }

    private function shouldQueue(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if (! config('blog_ai.queue', false)) {
            return false;
        }

        // sync driver already runs inline; use dispatchSync path for clearer errors.
        if (config('queue.default') === 'sync') {
            return false;
        }

        return true;
    }

    private function ensureEnabled(): void
    {
        if (! config('blog_ai.enabled', true)) {
            throw ValidationException::withMessages([
                'ai' => 'Blog AI is disabled.',
            ]);
        }
    }

    private function authorizeSession(BlogAiSession $session): void
    {
        if ((int) $session->user_id !== (int) request()->user()?->id) {
            abort(403);
        }
    }

    private function enforceDailyCaps(Request $request, bool $creatingSession = false): void
    {
        $userId = (int) $request->user()->id;
        $today = now()->toDateString();

        $sessions = (int) BlogAiSession::query()
            ->where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->count();

        // Cache ledger counts today's spend across any session (including older ones).
        $calls = BlogAiSession::dailyCalls($userId);
        $tokens = BlogAiSession::dailyTokens($userId);

        $sessionCap = (int) config('blog_ai.daily_session_cap', 20);
        $callsCap = (int) config('blog_ai.daily_ai_calls_cap', 80);
        $tokenCap = (int) config('blog_ai.daily_token_cap', 400000);

        if ($creatingSession && $sessions >= $sessionCap) {
            throw ValidationException::withMessages([
                'ai' => "Daily AI session limit reached ({$sessionCap}).",
            ]);
        }

        if ($calls >= $callsCap) {
            throw ValidationException::withMessages([
                'ai' => "Daily AI call limit reached ({$callsCap}).",
            ]);
        }

        if ($tokens >= $tokenCap) {
            throw ValidationException::withMessages([
                'ai' => "Daily AI token limit reached ({$tokenCap}).",
            ]);
        }
    }

    /**
     * @param  list<string>  $pasted
     */
    private function assertPastedKeywords(array $pasted): void
    {
        if (! config('blog_ai.require_pasted_keywords', true)) {
            return;
        }

        $min = (int) config('blog_ai.min_pasted_keywords', 1);
        if (count($pasted) < $min) {
            throw ValidationException::withMessages([
                'keywords_text' => "Paste at least {$min} BD keyword(s) from Google Keyword Planner (Bangladesh).",
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function parseKeywordsText(?string $text): array
    {
        if (! filled($text)) {
            return [];
        }

        return collect(preg_split('/[\n,]+/u', $text) ?: [])
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function goodStatusBefore(BlogAiSession $session, string $fallback): string
    {
        if ($session->status === 'failed' && filled($session->resume_status)) {
            return (string) $session->resume_status;
        }

        if (in_array($session->status, BlogAiSession::READY_STATUSES, true)) {
            return $session->status;
        }

        return $fallback;
    }

    private function autoRequiresQueue(): bool
    {
        $raw = config('blog_ai.auto.require_queue');
        if ($raw === null || $raw === '') {
            return app()->environment('production');
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    private function assertAutoQueueReady(): void
    {
        if (! $this->autoRequiresQueue()) {
            return;
        }

        if ($this->shouldQueue()) {
            return;
        }

        throw ValidationException::withMessages([
            'ai' => 'Auto create requires a queue worker in this environment. Set BLOG_AI_QUEUE=true and run `php artisan queue:work`, or set BLOG_AI_AUTO_REQUIRE_QUEUE=false for local sync only.',
        ]);
    }

    private function assertNoActiveAutoRun(int $userId): void
    {
        if (! config('blog_ai.auto.one_active_run_per_user', true)) {
            return;
        }

        $active = BlogAiRun::query()
            ->where('user_id', $userId)
            ->whereIn('status', BlogAiRun::ACTIVE_STATUSES)
            ->latest('id')
            ->first();

        if (! $active) {
            return;
        }

        // Auto-clear stale active runs when the session was unlocked / failed.
        $session = $active->session;
        if ($session) {
            $session->recoverIfStale();
            $session->refresh();
            if (! $session->isBusy() && (
                $session->status === 'failed'
                || in_array($session->status, BlogAiSession::READY_STATUSES, true)
            )) {
                $active->status = $session->status === 'failed' ? 'failed' : 'cancelled';
                $active->last_error = $active->last_error ?: 'Cleared stale auto run.';
                $active->finished_at = now();
                $active->save();
                $this->purgeQueuedAutoJobs((int) $active->id);

                return;
            }
        }

        // Pending (never started) clears sooner than in-progress running jobs.
        $pendingStale = max(5, (int) config('blog_ai.auto.pending_stale_minutes', 10));
        if ($active->status === 'pending'
            && $active->created_at
            && $active->created_at->lt(now()->subMinutes($pendingStale))
        ) {
            $active->status = 'failed';
            $active->last_error = 'Cleared stale queued auto run (worker did not start within '.$pendingStale.' minutes). Check cPanel queue cron.';
            $active->finished_at = now();
            $active->save();
            $this->purgeQueuedAutoJobs((int) $active->id);
            if ($session && $session->isBusy()) {
                $session->invalidateJobToken();
                $session->status = 'failed';
                $session->last_error = $active->last_error;
                $session->save();
            }

            return;
        }

        $staleMinutes = max(25, (int) config('blog_ai.auto.busy_stale_minutes', 25));
        $anchor = $active->updated_at ?? $active->started_at;
        if ($anchor && $anchor->lt(now()->subMinutes($staleMinutes))) {
            $active->status = 'failed';
            $active->last_error = 'Cleared stale auto run (no progress for '.$staleMinutes.' minutes).';
            $active->finished_at = now();
            $active->save();
            $this->purgeQueuedAutoJobs((int) $active->id);
            if ($session && $session->isBusy()) {
                $session->invalidateJobToken();
                $session->status = 'failed';
                $session->last_error = $active->last_error;
                $session->save();
            }

            return;
        }

        throw ValidationException::withMessages([
            'ai' => 'You already have an auto create running (#'.$active->id.'). Resume it from the dialog or cancel it first.',
            'active_run_id' => [(string) $active->id],
        ]);
    }

    /**
     * Remove pending database-queue rows for a cancelled/stale Auto run so cron cannot revive them.
     */
    private function purgeQueuedAutoJobs(int $runId): void
    {
        if ($runId < 1 || config('queue.default') !== 'database') {
            return;
        }

        try {
            $table = config('queue.connections.database.table', 'jobs');
            DB::table($table)
                ->where('payload', 'like', '%ProcessBlogAutoPipeline%')
                ->where(function ($q) use ($runId) {
                    $q->where('payload', 'like', '%s:5:"runId";i:'.$runId.';%')
                        ->orWhere('payload', 'like', '%"runId":'.$runId.'%')
                        ->orWhere('payload', 'like', '%"runId";i:'.$runId.';%');
                })
                ->delete();
        } catch (Throwable $e) {
            // Best-effort on shared hosting — cancel still marks the run terminal.
        }
    }
}
