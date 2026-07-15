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
            $this->assertNotCancelled($run);
            $this->runHooksLoop($session, $run, $scoreParts);
            $this->assertNotCancelled($run);
            $this->runOutlineLoop($session, $run, $scoreParts);
            $this->assertNotCancelled($run);
            $this->runDraftLoop($session, $run, $scoreParts);

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

    private function resolveIntake(BlogAiSession $session, BlogAiRun $run): void
    {
        $learning = $this->learning->promptLearningBlock();
        $input = is_array($run->input_json) ? $run->input_json : [];

        $clusterInput = trim((string) ($input['cluster'] ?? ''));
        $cluster = trim((string) ($session->cluster ?: $clusterInput));
        $seed = trim((string) ($session->seed_topic ?: ($input['seed_topic'] ?? '')));
        $pasted = $session->keywords_json['pasted'] ?? [];
        if (! is_array($pasted)) {
            $pasted = [];
        }

        // Only auto-pick cluster when admin left it blank — respect explicit "general".
        if ($cluster === '') {
            $ideas = $learning['next_post_ideas'] ?? [];
            $recommended = $learning['recommended_clusters'] ?? ['fake_order', 'fraud_checker', 'courier'];
            if (is_array($ideas) && $ideas !== [] && is_array($ideas[0] ?? null)) {
                $idea = $ideas[0];
                $cluster = (string) ($idea['cluster'] ?? ($recommended[0] ?? 'fake_order'));
                if ($seed === '') {
                    $seed = (string) ($idea['seed_topic'] ?? $idea['suggested_title'] ?? '');
                }
            } else {
                $cluster = (string) ($recommended[0] ?? 'fake_order');
            }
        } elseif ($seed === '') {
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

        if ($pasted === []) {
            $suggested = $this->content->suggestSeedKeywords($seed, $cluster !== '' ? $cluster : 'general');
            $session->addUsage($suggested['usage']);
            $pasted = $suggested['keywords'];
            $run->appendLog([
                'step' => 'intake',
                'event' => 'keywords_generated',
                'message' => 'Seed keywords generated from market suggest + learning.',
                'count' => count($pasted),
            ]);
        }

        $session->cluster = $cluster !== '' ? $cluster : 'general';
        $session->seed_topic = $seed !== '' ? $seed : $session->seed_topic;
        $session->keywords_json = [
            'pasted' => $pasted,
            'primary' => $pasted[0] ?? null,
            'secondary' => array_slice($pasted, 1),
        ];
        $session->saveIfJobCurrent();

        $run->appendLog([
            'step' => 'intake',
            'event' => 'resolved',
            'message' => 'Topic locked: '.$session->cluster.($session->seed_topic ? ' — '.$session->seed_topic : ''),
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

        for ($attempt = 0; $attempt <= $max; $attempt++) {
            $this->bumpRevision($run, 'research', $attempt);
            $pasted = $session->keywords_json['pasted'] ?? [];
            if (! is_array($pasted)) {
                $pasted = [];
            }

            if ($fix) {
                $pasted = $this->reseedKeywordsAfterFix($session, $pasted, $fix);
            }

            $research = $this->content->researchKeywords(
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

            $fix = $review['fix_instructions'] ?: 'Pick a stronger non-colliding BD long-tail primary keyword.';
            if ($attempt >= $max) {
                throw ValidationException::withMessages(['ai' => 'Keyword research failed review after revisions: '.$fix]);
            }

            $run->appendLog([
                'step' => 'research',
                'event' => 'revising',
                'message' => $fix,
                'attempt' => $attempt + 1,
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

        for ($attempt = 0; $attempt <= $max; $attempt++) {
            $this->bumpRevision($run, 'hooks', $attempt);
            $this->content->generateHooks($session);
            $session->refresh();

            $review = $this->reviewAndLog($session, $run, 'hooks');
            // Hooks contribute lightly into opportunity (selection quality).
            $scoreParts['opportunity'] = (int) round(((int) ($scoreParts['opportunity'] ?? 70) + $review['score']) / 2);
            $this->syncScore($run, $scoreParts);

            if ($review['decision'] === 'abort') {
                throw ValidationException::withMessages(['ai' => $review['fix_instructions'] ?: 'Hooks aborted.']);
            }
            if ($review['pass']) {
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

                return;
            }

            if ($attempt >= $max) {
                throw ValidationException::withMessages([
                    'ai' => 'Hooks failed review: '.($review['fix_instructions'] ?: 'weak hooks'),
                ]);
            }

            $run->appendLog([
                'step' => 'hooks',
                'event' => 'revising',
                'message' => $review['fix_instructions'] ?: 'Regenerating hooks',
                'attempt' => $attempt + 1,
            ]);
            $run->save();
        }
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
                    $cap = max(0, min(100, (int) config('blog_ai.auto.soft_pass_score_cap', 59)));
                    $scoreParts['seo'] = min((int) ($scoreParts['seo'] ?? $cap), $cap);
                    $scoreParts['content'] = min((int) ($review['score'] ?? $cap), $cap);
                    $this->syncScore($run, $scoreParts);

                    $flags = is_array($run->input_json) ? $run->input_json : [];
                    $flags['soft_pass'] = true;
                    $flags['soft_pass_failures'] = $review['failures'];
                    $run->input_json = $flags;
                    $run->appendLog([
                        'step' => 'draft',
                        'event' => 'soft_pass',
                        'message' => 'Draft kept after max revisions — needs human SEO polish before publish. Score capped at '.$cap.'.',
                        'failures' => $review['failures'],
                        'score_cap' => $cap,
                    ]);
                    $run->save();

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
        $this->imagePipeline->run($session);
        $session->refresh();

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
        $softPass = ! empty($flags['soft_pass']);
        $imageAutoApproved = ! empty($flags['image_auto_approved']);
        $cap = max(0, min(100, (int) config('blog_ai.auto.soft_pass_score_cap', 59)));

        if ($softPass) {
            $computed['score'] = min($computed['score'], $cap);
            $run->live_score = $computed['score'];
            $run->score_breakdown = $computed['breakdown'];
        }

        $draft = is_array($session->draft_json) ? $session->draft_json : [];
        $draft['ai_quality_score'] = $computed['score'];
        $draft['ai_quality_breakdown'] = $computed['breakdown'];
        $draft['ai_run_id'] = $run->id;
        $draft['needs_review'] = $softPass || $imageAutoApproved;
        $session->draft_json = $draft;

        if (! in_array($session->status, ['image_ready', 'image_needs_fix', 'draft_ready'], true)) {
            $session->status = config('blog_ai.image_enabled', true) ? 'image_ready' : 'draft_ready';
        }
        $session->last_error = null;
        $session->saveIfJobCurrent();

        $postId = null;
        $createPost = (bool) data_get($run->input_json, 'create_post', config('blog_ai.auto.create_post', true));
        if ($createPost && ! empty($draft['title']) && ! empty($draft['body_html'])) {
            $post = $this->createDraftPost($session, $run, $draft, $computed);
            $postId = $post->id;
            $run->blog_post_id = $postId;
            $post->ai_run_id = $run->id;
            $post->save();
        }

        $needsReview = $softPass || $imageAutoApproved;
        $run->status = $needsReview ? 'completed_needs_review' : 'completed';
        $run->current_step = 'done';
        $run->progress_pct = 100;
        $run->finished_at = now();
        $run->live_score = $computed['score'];
        $run->appendLog([
            'step' => 'finalize',
            'event' => $needsReview ? 'completed_needs_review' : 'completed',
            'message' => $this->finalizeMessage($postId, $computed['score'], $softPass, $imageAutoApproved),
            'blog_post_id' => $postId,
            'live_score' => $computed['score'],
            'soft_pass' => $softPass,
            'image_auto_approved' => $imageAutoApproved,
        ]);
        $run->save();

        return $run->fresh();
    }

    private function finalizeMessage(?int $postId, int $score, bool $softPass, bool $imageAutoApproved): string
    {
        $parts = [];
        if ($postId) {
            $parts[] = "Draft post #{$postId} created";
        } else {
            $parts[] = 'Pipeline complete';
        }
        $parts[] = "AI score {$score}";
        if ($softPass) {
            $parts[] = 'SEO soft-pass — human polish required before publish';
        }
        if ($imageAutoApproved) {
            $parts[] = 'cover auto-approved after QA fail';
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
