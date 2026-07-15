<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiRun;
use App\Models\BlogAiSession;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * One-click auto blog orchestrator: generate → review → decide → next.
 * Does not change the manual wizard API; calls agents directly under a job_token lock.
 */
class BlogAutoPipeline
{
    public function __construct(
        private BlogContentAgent $content,
        private BlogImagePipeline $imagePipeline,
        private BlogStepReviewAgent $reviewer,
        private BlogReadinessScorer $scorer,
        private BlogLearningService $learning,
        private BlogLandingContextService $landingContext,
    ) {}

    public function run(BlogAiRun $run): BlogAiRun
    {
        $session = BlogAiSession::query()->find($run->blog_ai_session_id);
        if (! $session) {
            return $this->failRun($run, 'AI session missing.');
        }

        $run->status = 'running';
        $run->started_at = $run->started_at ?? now();
        $run->current_step = 'intake';
        $run->progress_pct = $this->progressThrough('intake');
        $run->appendLog([
            'step' => 'intake',
            'event' => 'started',
            'message' => 'Auto pipeline started.',
        ]);
        $run->save();

        $scoreParts = [
            'opportunity' => null,
            'outline' => null,
            'seo' => null,
            'content' => null,
            'image' => null,
        ];

        try {
            $this->assertNotCancelled($run);
            $this->resolveIntake($session, $run);
            $this->touchSessionBusy($session);

            $this->assertNotCancelled($run);
            $this->runResearchLoop($session, $run, $scoreParts);
            $this->touchSessionBusy($session);

            $this->assertNotCancelled($run);
            $this->runHooksLoop($session, $run, $scoreParts);
            $this->touchSessionBusy($session);

            $this->assertNotCancelled($run);
            $this->runOutlineLoop($session, $run, $scoreParts);
            $this->touchSessionBusy($session);

            $this->assertNotCancelled($run);
            $this->runDraftLoop($session, $run, $scoreParts);
            $this->touchSessionBusy($session);

            if (config('blog_ai.image_enabled', true)) {
                $this->assertNotCancelled($run);
                $this->runImageStep($session, $run, $scoreParts);
            } else {
                $scoreParts['image'] = 70;
                $this->syncScore($run, $scoreParts);
            }

            $this->assertNotCancelled($run);

            return $this->finalize($session, $run, $scoreParts);
        } catch (Throwable $e) {
            $run->refresh();
            if ($run->status === 'cancelled') {
                return $run;
            }

            $message = $e instanceof ValidationException
                ? (string) (collect($e->errors())->flatten()->first() ?: $e->getMessage())
                : $e->getMessage();

            if (str_contains(mb_strtolower($message), 'cancelled')) {
                return $run->fresh() ?? $run;
            }

            Log::warning('Blog AI auto pipeline failed', [
                'run_id' => $run->id,
                'session_id' => $session->id,
                'message' => $message,
            ]);

            $session->refresh();
            if ($session->status !== 'failed') {
                $session->last_error = $message !== '' ? $message : 'Auto pipeline failed.';
                $session->status = 'failed';
                $session->saveIfJobCurrent();
            }

            return $this->failRun($run, $message !== '' ? $message : 'Auto pipeline failed.');
        }
    }

    /**
     * When the queue worker is killed mid-image (or after retry_after), save the draft anyway.
     */
    public function finalizeInterrupted(BlogAiSession $session, BlogAiRun $run, ?string $reason = null): BlogAiRun
    {
        $run->refresh();
        $session->refresh();

        if ($run->isTerminal()) {
            return $run;
        }

        $draft = is_array($session->draft_json) ? $session->draft_json : [];
        if (empty($draft['title']) || empty($draft['body_html'])) {
            throw ValidationException::withMessages([
                'ai' => 'Cannot recover — no draft body yet. '.$reason,
            ]);
        }

        $flags = is_array($run->input_json) ? $run->input_json : [];
        $flags['image_skipped'] = true;
        $flags['interrupted_recovery'] = true;
        $flags['soft_pass'] = true;
        if (filled($reason)) {
            $flags['interrupt_reason'] = Str::limit($reason, 400, '');
        }
        $run->input_json = $flags;
        $run->appendLog([
            'step' => $run->current_step ?: 'image',
            'event' => 'recovered',
            'message' => 'Worker interrupted — keeping draft and skipping cover. '
                .($reason ? Str::limit($reason, 180, '') : ''),
        ]);
        $run->save();

        $breakdown = is_array($run->score_breakdown) ? $run->score_breakdown : [];
        $scoreParts = [
            'opportunity' => isset($breakdown['opportunity']) ? (int) $breakdown['opportunity'] : 70,
            'outline' => isset($breakdown['outline']) ? (int) $breakdown['outline'] : 70,
            'seo' => isset($breakdown['seo']) ? (int) $breakdown['seo'] : 55,
            'content' => isset($breakdown['content']) ? (int) $breakdown['content'] : 55,
            'image' => 40,
        ];

        return $this->finalize($session, $run, $scoreParts);
    }

    private function resolveIntake(BlogAiSession $session, BlogAiRun $run): void
    {
        $learning = $this->learning->promptLearningBlock();
        $input = is_array($run->input_json) ? $run->input_json : [];

        $clusterInput = trim((string) ($input['cluster'] ?? ''));
        $explicitCluster = trim((string) ($session->cluster ?: $clusterInput));
        $seed = trim((string) ($session->seed_topic ?: ($input['seed_topic'] ?? '')));
        $pasted = $session->keywords_json['pasted'] ?? [];
        if (! is_array($pasted)) {
            $pasted = [];
        }

        $resolved = $this->landingContext->resolveCluster(
            $explicitCluster !== '' ? $explicitCluster : null,
            $seed,
            array_map(fn ($k) => (string) $k, $pasted),
            is_array($learning) ? $learning : [],
        );

        $cluster = (string) ($resolved['cluster'] ?? 'fake_order');
        if ($seed === '' && filled($resolved['seed_topic'] ?? null)) {
            $seed = trim((string) $resolved['seed_topic']);
        }

        // Fill seed from learning idea matching locked cluster when still empty.
        if ($seed === '') {
            $ideas = $learning['next_post_ideas'] ?? [];
            if (is_array($ideas)) {
                foreach ($ideas as $idea) {
                    if (! is_array($idea)) {
                        continue;
                    }
                    if (($idea['cluster'] ?? null) === $cluster) {
                        $seed = (string) ($idea['seed_topic'] ?? $idea['suggested_title'] ?? '');
                        break;
                    }
                }
            }
        }

        // Prefer landing angle as seed when Auto still has no topic.
        if ($seed === '') {
            $seed = (string) ($resolved['landing']['angle_hint'] ?? config('blog_ai.clusters.'.$cluster, $cluster));
        }

        if ($pasted === []) {
            $suggested = $this->content->suggestSeedKeywords($seed, $cluster);
            $session->addUsage($suggested['usage']);
            $pasted = $suggested['keywords'];
            $run->appendLog([
                'step' => 'intake',
                'event' => 'keywords_generated',
                'message' => 'Seed keywords generated from market suggest + learning.',
                'count' => count($pasted),
            ]);
        }

        // Refine from keywords only when admin left cluster blank (Auto mode).
        // Explicit picks (including "general") stay locked; still refresh landing payload.
        if ($explicitCluster === '') {
            $refined = $this->landingContext->resolveCluster(
                null,
                $seed,
                array_map(fn ($k) => (string) $k, $pasted),
                is_array($learning) ? $learning : [],
            );
            $cluster = (string) ($refined['cluster'] ?? $cluster);
            $landing = is_array($refined['landing'] ?? null) ? $refined['landing'] : ($resolved['landing'] ?? []);
        } else {
            $refined = $resolved;
            $landing = is_array($resolved['landing'] ?? null)
                ? $resolved['landing']
                : $this->landingContext->forCluster($cluster);
        }

        $session->cluster = $cluster;
        $session->seed_topic = $seed !== '' ? $seed : $session->seed_topic;
        $session->keywords_json = [
            'pasted' => $pasted,
            'primary' => $pasted[0] ?? null,
            'secondary' => array_slice($pasted, 1),
        ];
        $session->saveIfJobCurrent();

        $input['cluster'] = $cluster;
        $input['cluster_source'] = (string) ($refined['source'] ?? $resolved['source'] ?? 'auto');
        $input['cluster_detected'] = (string) ($refined['detected'] ?? $resolved['detected'] ?? 'general');
        $input['cluster_primary_path'] = $landing['primary_path'] ?? null;
        $run->input_json = $input;

        $run->appendLog([
            'step' => 'intake',
            'event' => 'resolved',
            'message' => 'Topic locked: '.$session->cluster
                .($session->seed_topic ? ' — '.$session->seed_topic : '')
                .(isset($landing['primary_path']) ? ' → '.$landing['primary_path'] : ''),
            'cluster_source' => $input['cluster_source'],
            'cluster_detected' => $input['cluster_detected'],
            'primary_path' => $landing['primary_path'] ?? null,
            'learning_status' => $learning['status'] ?? null,
        ]);
        $run->save();
    }

    /**
     * @param  array<string, int|null>  $scoreParts
     */
    private function runResearchLoop(BlogAiSession $session, BlogAiRun $run, array &$scoreParts): void
    {
        $this->beginStep($run, 'research', 'Researching BD keywords + cannibalization…');
        $max = $this->maxRevisions();
        $fix = null;
        /** @var list<string> $avoidPrimaries */
        $avoidPrimaries = [];

        for ($attempt = 0; $attempt <= $max; $attempt++) {
            $this->bumpRevision($run, 'research', $attempt);
            $pasted = $session->keywords_json['pasted'] ?? [];
            if (! is_array($pasted)) {
                $pasted = [];
            }

            if ($fix) {
                $pasted = $this->reseedKeywordsAfterFix(
                    $session,
                    $pasted,
                    $fix.' Avoid these primaries: '.implode(', ', $avoidPrimaries),
                );
            }

            $research = $this->content->researchKeywords(
                (string) ($session->seed_topic ?? ''),
                (string) ($session->cluster ?? 'general'),
                $pasted,
                $avoidPrimaries,
            );

            if (is_array($research['auto_pivot'] ?? null)) {
                $run->appendLog([
                    'step' => 'research',
                    'event' => 'auto_pivot',
                    'message' => 'Primary auto-switched to avoid cannibalization: '
                        .($research['auto_pivot']['from'] ?? '?')
                        .' → '
                        .($research['auto_pivot']['to'] ?? '?'),
                    'attempt' => $attempt,
                ]);
                $run->save();
                $from = trim((string) ($research['auto_pivot']['from'] ?? ''));
                if ($from !== '' && $from !== '(empty)') {
                    $avoidPrimaries[] = $from;
                }
            }

            $session->keywords_json = [
                'pasted' => $pasted,
                'primary' => $research['primary'],
                'secondary' => $research['secondary'],
                'suggestions' => $research['suggestions'],
                'live_suggestions' => $research['live_suggestions'] ?? [],
                'cannibalization' => $research['cannibalization'],
                'auto_pivot' => $research['auto_pivot'] ?? null,
            ];
            $session->addUsage($research['usage']);
            $session->status = 'keywords_ready';
            $session->saveIfJobCurrent();

            $review = $this->reviewAndLog($session, $run, 'research');
            $scoreParts['opportunity'] = $review['score'];
            $this->syncScore($run, $scoreParts);

            if ($review['decision'] === 'abort') {
                throw ValidationException::withMessages(['ai' => $review['fix_instructions'] ?: 'Research aborted by reviewer.']);
            }
            if ($review['pass']) {
                $this->markStepPassed($run, 'research', $review);

                return;
            }

            $failedPrimary = trim((string) ($research['primary'] ?? ''));
            if ($failedPrimary !== '') {
                $avoidPrimaries[] = $failedPrimary;
                $avoidPrimaries = array_values(array_unique($avoidPrimaries));
            }

            $fix = $review['fix_instructions'] ?: 'Pick a stronger non-colliding BD long-tail primary keyword.';
            if ($attempt >= $max) {
                // Keep moving with a differentiated primary rather than hard-failing the whole Auto run.
                if (filled($research['primary'])) {
                    $this->softPassStep(
                        $run,
                        $scoreParts,
                        step: 'research',
                        scoreKey: 'opportunity',
                        review: $review,
                        message: 'Research soft-passed after auto pivots — continue with differentiation. Primary: '.$research['primary'],
                        flagKey: 'research_soft_pass',
                    );
                    $this->markStepPassed($run, 'research', array_merge($review, [
                        'pass' => true,
                        'decision' => 'advance',
                        'notes' => 'Soft-pass: '.$fix,
                    ]));

                    return;
                }

                throw ValidationException::withMessages(['ai' => 'Keyword research failed review after revisions: '.$fix]);
            }

            $run->appendLog([
                'step' => 'research',
                'event' => 'revising',
                'message' => $fix,
                'attempt' => $attempt + 1,
                'avoided_primaries' => $avoidPrimaries,
            ]);
            $run->save();
        }
    }

    /**
     * @param  list<string>  $pasted
     * @return list<string>
     */
    private function reseedKeywordsAfterFix(BlogAiSession $session, array $pasted, string $fix): array
    {
        $suggested = $this->content->suggestSeedKeywords(
            trim($fix.' '.(string) ($session->seed_topic ?? '')),
            (string) ($session->cluster ?? 'general'),
        );
        $session->addUsage($suggested['usage']);
        $merged = collect(array_merge($suggested['keywords'], $pasted))
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();

        return $merged !== [] ? $merged : $pasted;
    }

    /**
     * @param  array<string, int|null>  $scoreParts
     */
    private function runHooksLoop(BlogAiSession $session, BlogAiRun $run, array &$scoreParts): void
    {
        $this->beginStep($run, 'hooks', 'Generating hooks…');
        $max = $this->maxRevisions();
        $fix = null;
        /** @var list<string> $avoidTitles */
        $avoidTitles = [];

        for ($attempt = 0; $attempt <= $max; $attempt++) {
            $this->bumpRevision($run, 'hooks', $attempt);
            $this->content->generateHooks($session, $fix, $avoidTitles);
            $session->refresh();

            $avoidTitles = array_values(array_unique(array_merge(
                $avoidTitles,
                collect($session->hooks_json ?? [])
                    ->map(fn ($h) => is_array($h) ? trim((string) ($h['title'] ?? '')) : '')
                    ->filter()
                    ->all(),
            )));

            $review = $this->reviewAndLog($session, $run, 'hooks');
            // Hooks contribute lightly into opportunity (selection quality).
            $scoreParts['opportunity'] = (int) round(((int) ($scoreParts['opportunity'] ?? 70) + $review['score']) / 2);
            $this->syncScore($run, $scoreParts);

            if ($review['decision'] === 'abort') {
                throw ValidationException::withMessages(['ai' => $review['fix_instructions'] ?: 'Hooks aborted.']);
            }
            if ($review['pass']) {
                $this->finishHooksStep($session, $run, $review);

                return;
            }

            $fix = $review['fix_instructions']
                ?: 'Regenerate with clearer unique angles vs existing posts; mix pain/howto/checklist/comparison.';

            if ($attempt >= $max) {
                $hooks = collect($session->hooks_json ?? [])->filter(fn ($h) => is_array($h) && filled($h['title'] ?? null));
                if ($hooks->count() >= 3) {
                    $this->softPassStep(
                        $run,
                        $scoreParts,
                        step: 'hooks',
                        scoreKey: 'opportunity',
                        review: $review,
                        message: 'Hooks soft-passed after differentiation retries — continue.',
                        flagKey: 'hooks_soft_pass',
                    );
                    $this->finishHooksStep($session, $run, array_merge($review, [
                        'pass' => true,
                        'decision' => 'advance',
                        'notes' => 'Soft-pass: '.$fix,
                    ]));

                    return;
                }

                throw ValidationException::withMessages([
                    'ai' => 'Hooks failed review: '.$fix,
                ]);
            }

            $run->appendLog([
                'step' => 'hooks',
                'event' => 'revising',
                'message' => $fix,
                'attempt' => $attempt + 1,
            ]);
            $run->save();
        }
    }

    /**
     * @param  array<string, mixed>  $review
     */
    private function finishHooksStep(BlogAiSession $session, BlogAiRun $run, array $review): void
    {
        $selected = $this->autoSelectHooks($session);
        $session->selected_hook_ids = $selected;
        $session->saveIfJobCurrent();
        $run->appendLog([
            'step' => 'hooks',
            'event' => 'selected',
            'message' => 'Auto-selected hooks: '.implode(', ', $selected),
            'selected_hook_ids' => $selected,
        ]);
        $this->markStepPassed($run, 'hooks', $review);
    }

    /**
     * @return list<string>
     */
    private function autoSelectHooks(BlogAiSession $session): array
    {
        $take = max(1, min(3, (int) config('blog_ai.auto.hooks_to_select', 1)));
        $preferred = ['howto', 'checklist', 'comparison', 'pain', 'myth', 'story'];
        $hooks = collect($session->hooks_json ?? [])->filter(fn ($h) => is_array($h));

        $ranked = $hooks->sortBy(function (array $hook) use ($preferred) {
            $angle = strtolower((string) ($hook['angle'] ?? 'howto'));
            $idx = array_search($angle, $preferred, true);

            return $idx === false ? 99 : $idx;
        })->values();

        return $ranked->take($take)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * @param  array<string, int|null>  $scoreParts
     */
    private function runOutlineLoop(BlogAiSession $session, BlogAiRun $run, array &$scoreParts): void
    {
        $this->beginStep($run, 'outline', 'Building SEO outline…');
        $max = $this->maxRevisions();
        $selected = array_values($session->selected_hook_ids ?? []);
        if ($selected === []) {
            $selected = $this->autoSelectHooks($session);
        }
        $fix = null;

        for ($attempt = 0; $attempt <= $max; $attempt++) {
            $this->bumpRevision($run, 'outline', $attempt);
            $this->content->generateOutline($session, $selected, $fix);
            $session->refresh();

            $review = $this->reviewAndLog($session, $run, 'outline');
            $scoreParts['outline'] = $review['score'];
            $this->syncScore($run, $scoreParts);

            if ($review['decision'] === 'abort') {
                throw ValidationException::withMessages(['ai' => $review['fix_instructions'] ?: 'Outline aborted.']);
            }
            if ($review['pass']) {
                $this->markStepPassed($run, 'outline', $review);

                return;
            }

            $fix = $review['fix_instructions'] ?: 'Expand outline with differentiation H2, 4+ sections, 3+ FAQs, 2+ internal links.';
            if ($attempt >= $max) {
                $outline = is_array($session->outline_json) ? $session->outline_json : [];
                $sections = $outline['sections'] ?? [];
                if (is_array($sections) && count($sections) >= 3) {
                    $this->softPassStep(
                        $run,
                        $scoreParts,
                        step: 'outline',
                        scoreKey: 'outline',
                        review: $review,
                        message: 'Outline soft-passed with usable sections — draft will deepen FAQs/links.',
                        flagKey: 'outline_soft_pass',
                    );
                    $this->markStepPassed($run, 'outline', array_merge($review, [
                        'pass' => true,
                        'decision' => 'advance',
                        'notes' => 'Soft-pass: '.$fix,
                    ]));

                    return;
                }

                throw ValidationException::withMessages(['ai' => 'Outline failed review: '.$fix]);
            }

            $run->appendLog([
                'step' => 'outline',
                'event' => 'revising',
                'message' => $fix,
                'attempt' => $attempt + 1,
            ]);
            $run->save();
        }
    }

    /**
     * @param  array<string, int|null>  $scoreParts
     */
    private function runDraftLoop(BlogAiSession $session, BlogAiRun $run, array &$scoreParts): void
    {
        $this->beginStep($run, 'draft', 'Writing full SEO draft…');
        $max = $this->maxRevisions();
        $fix = null;

        for ($attempt = 0; $attempt <= $max; $attempt++) {
            $this->bumpRevision($run, 'draft', $attempt);
            $this->content->generateDraft($session, $fix);
            $session->refresh();

            $quality = $session->draft_json['quality'] ?? [];
            $scoreParts['seo'] = $this->scorer->scoreFromSeoQuality(is_array($quality) ? $quality : []);
            $this->syncScore($run, $scoreParts);

            $review = $this->reviewAndLog($session, $run, 'draft');
            $scoreParts['content'] = $review['score'];
            $scoreParts['seo'] = $this->scorer->scoreFromSeoQuality(
                is_array($session->draft_json['quality'] ?? null) ? $session->draft_json['quality'] : []
            );
            $this->syncScore($run, $scoreParts);

            if ($review['decision'] === 'abort') {
                throw ValidationException::withMessages(['ai' => $review['fix_instructions'] ?: 'Draft aborted.']);
            }
            if ($review['pass']) {
                $this->markStepPassed($run, 'draft', $review);

                return;
            }

            $fix = $review['fix_instructions'] ?: 'Rewrite to pass SEO gates: keyword placement, FAQs, internal links, depth.';
            if ($attempt >= $max) {
                // Soft complete for human editing — not a clean pass.
                if (! empty($session->draft_json['title']) && ! empty($session->draft_json['body_html'])) {
                    $this->softPassStep(
                        $run,
                        $scoreParts,
                        step: 'draft',
                        scoreKey: 'content',
                        review: $review,
                        message: 'Draft kept after max revisions — needs human SEO polish before publish.',
                        flagKey: 'soft_pass',
                        extraScoreKeys: ['seo'],
                    );
                    $this->markStepPassed($run, 'draft', array_merge($review, [
                        'pass' => true,
                        'decision' => 'advance',
                        'notes' => 'Soft-pass: '.$fix,
                    ]));

                    return;
                }

                throw ValidationException::withMessages(['ai' => 'Draft failed review: '.$fix]);
            }

            $run->appendLog([
                'step' => 'draft',
                'event' => 'revising',
                'message' => $fix,
                'attempt' => $attempt + 1,
            ]);
            $run->save();
        }
    }

    /**
     * @param  array<string, int|null>  $scoreParts
     */
    private function runImageStep(BlogAiSession $session, BlogAiRun $run, array &$scoreParts): void
    {
        $this->beginStep($run, 'image', 'Generating + reviewing cover image…');

        // Keep Auto cover attempts short so the job finishes inside QUEUE_RETRY_AFTER / worker lifetime.
        $previousAttempts = config('blog_ai.image.max_generate_attempts');
        config(['blog_ai.image.max_generate_attempts' => max(1, min(2, (int) config('blog_ai.auto.image_max_attempts', 1)))]);

        try {
            $this->imagePipeline->run($session);
            $session->refresh();
        } catch (Throwable $e) {
            Log::warning('Blog AI auto image step failed — continuing without cover', [
                'run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);
            $flags = is_array($run->input_json) ? $run->input_json : [];
            $flags['image_skipped'] = true;
            $flags['image_skip_reason'] = Str::limit($e->getMessage(), 300, '');
            $run->input_json = $flags;
            $scoreParts['image'] = 40;
            $this->syncScore($run, $scoreParts);
            $run->appendLog([
                'step' => 'image',
                'event' => 'skipped',
                'message' => 'Cover generation failed; draft will still be created. Add an OG image before publish. ('.$e->getMessage().')',
            ]);
            $run->save();

            return;
        } finally {
            config(['blog_ai.image.max_generate_attempts' => $previousAttempts]);
        }

        $imageAutoApproved = false;
        if ($session->status === 'image_needs_fix' && config('blog_ai.auto.auto_approve_image_on_fail', true)) {
            $this->imagePipeline->approve($session);
            $session->refresh();
            $imageAutoApproved = true;
            $flags = is_array($run->input_json) ? $run->input_json : [];
            $flags['image_auto_approved'] = true;
            $run->input_json = $flags;
            $run->appendLog([
                'step' => 'image',
                'event' => 'auto_approved',
                'message' => 'Image failed vision QA; auto-approved for draft with score penalty. Replace before publish if weak.',
            ]);
            $run->save();
        }

        $review = $this->reviewAndLog($session, $run, 'image', [
            'image_auto_approved' => $imageAutoApproved,
        ]);
        $scoreParts['image'] = $review['score'];
        $this->syncScore($run, $scoreParts);
        if ($review['pass']) {
            $this->markStepPassed($run, 'image', $review);
        }
    }

    /**
     * @param  array<string, int|null>  $scoreParts
     */
    private function finalize(BlogAiSession $session, BlogAiRun $run, array $scoreParts): BlogAiRun
    {
        $this->beginStep($run, 'finalize', 'Finalizing score + draft…');
        $computed = $this->syncScore($run, $scoreParts);

        $flags = is_array($run->input_json) ? $run->input_json : [];
        $softPass = $this->hasAnySoftPass($flags);
        $imageAutoApproved = ! empty($flags['image_auto_approved']);
        $imageSkipped = ! empty($flags['image_skipped']);
        $cap = max(0, min(100, (int) config('blog_ai.auto.soft_pass_score_cap', 59)));

        if ($softPass || $imageSkipped) {
            $computed['score'] = min($computed['score'], $cap);
            $run->live_score = $computed['score'];
            $run->score_breakdown = $computed['breakdown'];
        }

        $draft = is_array($session->draft_json) ? $session->draft_json : [];
        if (empty($draft['title']) || empty($draft['body_html'])) {
            throw ValidationException::withMessages([
                'ai' => 'Auto pipeline finished without a usable draft body. Retry Auto Create.',
            ]);
        }

        $draft['ai_quality_score'] = $computed['score'];
        $draft['ai_quality_breakdown'] = $computed['breakdown'];
        $draft['ai_run_id'] = $run->id;
        $needsReview = $softPass || $imageAutoApproved || $imageSkipped;
        $draft['needs_review'] = $needsReview;
        $session->draft_json = $draft;

        if (! in_array($session->status, ['image_ready', 'image_needs_fix', 'draft_ready'], true)) {
            $session->status = ! empty($draft['og_image']) ? 'image_ready' : 'draft_ready';
        }
        $session->last_error = null;
        $session->saveIfJobCurrent();

        $postId = null;
        $createPost = (bool) data_get($run->input_json, 'create_post', config('blog_ai.auto.create_post', true));
        if ($createPost) {
            try {
                $post = $this->createDraftPost($session, $run, $draft, $computed);
                $postId = $post->id;
                $run->blog_post_id = $postId;
                $post->ai_run_id = $run->id;
                $post->save();
            } catch (Throwable $e) {
                Log::warning('Blog AI auto createDraftPost failed', [
                    'run_id' => $run->id,
                    'message' => $e->getMessage(),
                ]);
                $run->appendLog([
                    'step' => 'finalize',
                    'event' => 'post_create_failed',
                    'message' => 'Draft is ready in the wizard, but CMS post create failed: '.$e->getMessage(),
                ]);
            }
        }

        $run->status = $needsReview ? 'completed_needs_review' : 'completed';
        $run->current_step = 'done';
        $run->progress_pct = 100;
        $run->finished_at = now();
        $run->live_score = $computed['score'];
        $run->appendLog([
            'step' => 'finalize',
            'event' => $needsReview ? 'completed_needs_review' : 'completed',
            'message' => $this->finalizeMessage($postId, $computed['score'], $flags),
            'blog_post_id' => $postId,
            'live_score' => $computed['score'],
            'soft_pass' => $softPass,
            'image_auto_approved' => $imageAutoApproved,
            'image_skipped' => $imageSkipped,
        ]);
        $run->save();

        return $run->fresh();
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private function hasAnySoftPass(array $flags): bool
    {
        foreach (['soft_pass', 'research_soft_pass', 'hooks_soft_pass', 'outline_soft_pass'] as $key) {
            if (! empty($flags[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int|null>  $scoreParts
     * @param  array<string, mixed>  $review
     * @param  list<string>  $extraScoreKeys
     */
    private function softPassStep(
        BlogAiRun $run,
        array &$scoreParts,
        string $step,
        string $scoreKey,
        array $review,
        string $message,
        string $flagKey,
        array $extraScoreKeys = [],
    ): void {
        $cap = max(0, min(100, (int) config('blog_ai.auto.soft_pass_score_cap', 59)));
        $scoreParts[$scoreKey] = min((int) ($scoreParts[$scoreKey] ?? $review['score'] ?? $cap), $cap);
        foreach ($extraScoreKeys as $key) {
            $scoreParts[$key] = min((int) ($scoreParts[$key] ?? $cap), $cap);
        }
        $this->syncScore($run, $scoreParts);

        $flags = is_array($run->input_json) ? $run->input_json : [];
        $flags[$flagKey] = true;
        $flags[$flagKey.'_failures'] = $review['failures'] ?? [];
        // Unified flag so finalize / UI always treat as needs-review.
        $flags['soft_pass'] = true;
        $run->input_json = $flags;
        $run->appendLog([
            'step' => $step,
            'event' => 'soft_pass',
            'message' => $message.' Score capped at '.$cap.'.',
            'failures' => $review['failures'] ?? [],
            'score_cap' => $cap,
        ]);
        $run->save();
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    private function finalizeMessage(?int $postId, int $score, array $flags): string
    {
        $parts = [];
        if ($postId) {
            $parts[] = "Draft post #{$postId} created";
        } else {
            $parts[] = 'Pipeline complete';
        }
        $parts[] = "AI score {$score}";
        if ($this->hasAnySoftPass($flags)) {
            $parts[] = 'soft-pass — human polish required before publish';
        }
        if (! empty($flags['interrupted_recovery'])) {
            $parts[] = 'recovered after worker interrupt — add cover before publish';
        }
        if (! empty($flags['image_auto_approved'])) {
            $parts[] = 'cover auto-approved after QA fail';
        }
        if (! empty($flags['image_skipped'])) {
            $parts[] = 'cover skipped — add OG image before publish';
        }

        return implode('. ', $parts).'.';
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array{score: int, breakdown: array<string, int|null>}  $computed
     */
    private function createDraftPost(BlogAiSession $session, BlogAiRun $run, array $draft, array $computed): BlogPost
    {
        $slug = (string) ($draft['slug'] ?? '');
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $slug = BlogPost::makeSlug((string) ($draft['focus_keyword'] ?? $draft['title'] ?? 'woo-easylife-guide'));
        } else {
            $slug = BlogPost::makeSlug($slug);
        }

        return BlogPost::query()->create([
            'title' => (string) $draft['title'],
            'slug' => $slug,
            'locale' => (string) ($draft['locale'] ?? 'bn'),
            'cluster' => $session->cluster ?: ($draft['cluster'] ?? null),
            'status' => 'draft',
            'excerpt' => $draft['excerpt'] ?? null,
            'meta_title' => $draft['meta_title'] ?? null,
            'meta_description' => $draft['meta_description'] ?? null,
            'focus_keyword' => $draft['focus_keyword'] ?? null,
            'og_image' => $draft['og_image'] ?? null,
            'robots' => $draft['robots'] ?? 'index,follow',
            'author_name' => $draft['author_name'] ?? config('blog_ai.author_name'),
            'faqs_json' => $draft['faqs'] ?? null,
            'body_html' => (string) $draft['body_html'],
            'ai_quality_score' => $computed['score'],
            'ai_quality_breakdown' => $computed['breakdown'],
            'created_by' => $run->user_id,
            'updated_by' => $run->user_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function reviewAndLog(BlogAiSession $session, BlogAiRun $run, string $step, array $context = []): array
    {
        $this->assertNotCancelled($run);

        $run->appendLog([
            'step' => $step,
            'event' => 'reviewing',
            'message' => 'AI agent reviewing '.$step.'…',
        ]);
        $run->save();

        $review = $this->reviewer->review($step, $session, $context);
        if (($review['usage']['total_tokens'] ?? 0) > 0) {
            $session->addUsage($review['usage']);
            $session->saveIfJobCurrent();
        }

        $run->appendLog([
            'step' => $step,
            'event' => 'reviewed',
            'message' => $review['notes']
                ?: ('decision='.$review['decision'].' score='.$review['score'].($review['pass'] ? ' pass' : ' fail')),
            'pass' => $review['pass'],
            'score' => $review['score'],
            'decision' => $review['decision'],
            'failures' => $review['failures'],
        ]);
        $run->save();

        return $review;
    }

    /**
     * @param  array<string, mixed>  $review
     */
    private function markStepPassed(BlogAiRun $run, string $step, array $review): void
    {
        $run->appendLog([
            'step' => $step,
            'event' => 'passed',
            'message' => 'Step passed (score '.$review['score'].').',
            'score' => $review['score'],
        ]);
        $run->progress_pct = $this->progressThrough($step);
        $run->save();
    }

    private function beginStep(BlogAiRun $run, string $step, string $message): void
    {
        $run->current_step = $step;
        $run->progress_pct = max((int) $run->progress_pct, $this->progressThrough($step) - 5);
        $run->appendLog([
            'step' => $step,
            'event' => 'started',
            'message' => $message,
        ]);
        $run->save();
    }

    private function progressThrough(string $step): int
    {
        $weights = config('blog_ai.auto.progress', []);
        $order = ['intake', 'research', 'hooks', 'outline', 'draft', 'image', 'finalize'];
        $sum = 0;
        foreach ($order as $key) {
            $sum += (int) ($weights[$key] ?? 0);
            if ($key === $step) {
                return min(99, $sum);
            }
        }

        return min(99, $sum);
    }

    /**
     * @param  array<string, int|null>  $parts
     * @return array{score: int, breakdown: array<string, int|null>}
     */
    private function syncScore(BlogAiRun $run, array $parts): array
    {
        $computed = $this->scorer->compute($parts);
        $run->live_score = $computed['score'];
        $run->score_breakdown = $computed['breakdown'];
        $run->save();

        return $computed;
    }

    private function bumpRevision(BlogAiRun $run, string $step, int $attempt): void
    {
        $counts = is_array($run->revision_counts) ? $run->revision_counts : [];
        $counts[$step] = $attempt;
        $run->revision_counts = $counts;
        $run->save();
    }

    private function maxRevisions(): int
    {
        return max(0, (int) config('blog_ai.auto.max_revisions_per_step', 2));
    }

    private function assertNotCancelled(BlogAiRun $run): void
    {
        $run->refresh();
        if ($run->status === 'cancelled') {
            throw ValidationException::withMessages([
                'ai' => 'Auto run was cancelled.',
            ]);
        }
    }

    private function touchSessionBusy(BlogAiSession $session): void
    {
        // Keep stale recovery longer for the full auto run while still using concrete step statuses.
        $session->status = 'auto_running';
        $session->saveIfJobCurrent();
    }

    private function failRun(BlogAiRun $run, string $message): BlogAiRun
    {
        $run->status = 'failed';
        $run->last_error = Str::limit($message, 1000, '');
        $run->finished_at = now();
        $run->appendLog([
            'step' => $run->current_step ?: 'pipeline',
            'event' => 'failed',
            'message' => $run->last_error,
        ]);
        $run->save();

        return $run->fresh();
    }
}
