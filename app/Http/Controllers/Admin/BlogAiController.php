<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBlogAiStep;
use App\Models\BlogAiSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BlogAiController extends Controller
{
    public function options(): JsonResponse
    {
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
    public function suggestKeywords(Request $request, \App\Services\BlogAi\BlogContentAgent $agent): JsonResponse
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

        if (! in_array($blogAiSession->status, ['keywords_ready', 'hooks_ready', 'failed', 'outline_ready', 'draft_ready', 'image_ready'], true)
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
}
