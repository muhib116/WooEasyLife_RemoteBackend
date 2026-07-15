<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiSession;
use App\Services\BlogSeoQuality;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Validation\ValidationException;

class BlogImagePipeline
{
    public function __construct(
        private BlogImageAgent $imageAgent,
        private BlogImageReviewAgent $reviewAgent,
        private BlogSeoQuality $seoQuality,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(BlogAiSession $session): array
    {
        $maxAttempts = max(1, (int) config('blog_ai.image.max_generate_attempts', 3));
        $fixPrompt = null;
        $lastImage = null;
        $lastReview = null;
        $aiCallsThisStep = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->assertDailyCapsAllow($session);
            } catch (ValidationException $e) {
                if (is_array($lastImage)) {
                    return $this->finishNeedsFix($session, $lastImage, $lastReview, $e->getMessage());
                }
                throw $e;
            }

            $generated = $this->imageAgent->generate($session, $fixPrompt);
            $session->ai_calls = (int) $session->ai_calls + 1;
            $session->bumpDailyUsage(1, 0);
            $aiCallsThisStep++;

            try {
                $this->assertDailyCapsAllow($session);
            } catch (ValidationException $e) {
                $image = [
                    'media_id' => $generated['media_id'],
                    'url' => $generated['url'],
                    'path' => $generated['path'],
                    'recipe' => $generated['recipe'],
                    'prompt_excerpt' => $generated['prompt_excerpt'],
                    'attempts' => $attempt,
                    'ai_calls_this_step' => $aiCallsThisStep,
                    'review' => null,
                ];
                $this->applyImageToDraft($session, $image);

                return $this->finishNeedsFix($session, $image, null, $e->getMessage());
            }

            $review = $this->reviewAgent->review($session, $generated);
            $session->addUsage($review['usage'] ?? []);
            $aiCallsThisStep++;

            $image = [
                'media_id' => $generated['media_id'],
                'url' => $generated['url'],
                'path' => $generated['path'],
                'recipe' => $generated['recipe'],
                'prompt_excerpt' => $generated['prompt_excerpt'],
                'attempts' => $attempt,
                'ai_calls_this_step' => $aiCallsThisStep,
                'review' => [
                    'pass' => $review['pass'],
                    'score' => $review['score'],
                    'alignment' => $review['alignment'],
                    'consistency' => $review['consistency'],
                    'brand' => $review['brand'],
                    'typography' => $review['typography'],
                    'issues' => $review['issues'],
                    'fix_prompt' => $review['fix_prompt'],
                ],
            ];

            $this->applyImageToDraft($session, $image);
            $lastImage = $image;
            $lastReview = $review;

            if ($review['pass']) {
                $session->image_json = $image;
                $session->status = 'image_ready';
                $session->last_error = null;
                $session->saveIfJobCurrent();

                return $image;
            }

            $fixPrompt = $review['fix_prompt'] ?: 'Improve identity match to the author reference and fix Bangla typography.';
            $session->image_json = $image;
            $session->saveIfJobCurrent();
        }

        return $this->finishNeedsFix($session, $lastImage, $lastReview, null);
    }

    /**
     * @param  array<string, mixed>|null  $lastImage
     * @param  array<string, mixed>|null  $lastReview
     * @return array<string, mixed>
     */
    private function finishNeedsFix(
        BlogAiSession $session,
        ?array $lastImage,
        ?array $lastReview,
        ?string $capMessage,
    ): array {
        if (! is_array($lastImage)) {
            throw ValidationException::withMessages([
                'ai' => $capMessage ?: 'Image generation produced no usable result.',
            ]);
        }

        $session->image_json = $lastImage;
        $session->status = 'image_needs_fix';
        $issues = is_array($lastReview['issues'] ?? null) ? $lastReview['issues'] : [];
        if ($capMessage) {
            $session->last_error = $capMessage;
        } elseif ($issues !== []) {
            $session->last_error = 'Image needs review: '.implode('; ', array_slice($issues, 0, 3));
        } else {
            $session->last_error = 'Image needs manual review before publish.';
        }
        $session->saveIfJobCurrent();

        return $lastImage;
    }

    /**
     * @param  array<string, mixed>  $image
     */
    public function applyImageToDraft(BlogAiSession $session, array $image): void
    {
        if (! is_array($session->draft_json)) {
            return;
        }

        $nextDraft = $session->draft_json;
        $title = (string) ($nextDraft['title'] ?? $session->seed_topic ?? 'WooEasyLife blog');
        $keyword = (string) ($nextDraft['focus_keyword'] ?? '');
        $url = (string) ($image['url'] ?? '');

        if ($url === '') {
            return;
        }

        $nextDraft['og_image'] = $url;
        $alt = $keyword !== '' ? $keyword : $title;
        $body = (string) ($nextDraft['body_html'] ?? '');
        $body = $this->seoQuality->injectContentImage($body, $url, $alt, $title);
        $nextDraft['body_html'] = BlogHtmlSanitizer::sanitize($body);

        $secondary = collect($session->keywords_json['secondary'] ?? [])
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->values()
            ->all();

        $nextDraft['quality'] = $this->seoQuality->analyze(
            title: (string) ($nextDraft['title'] ?? ''),
            focusKeyword: (string) ($nextDraft['focus_keyword'] ?? ''),
            bodyHtml: (string) ($nextDraft['body_html'] ?? ''),
            metaDescription: (string) ($nextDraft['meta_description'] ?? ''),
            faqs: is_array($nextDraft['faqs'] ?? null) ? $nextDraft['faqs'] : [],
            secondaryKeywords: $secondary,
            slug: (string) ($nextDraft['slug'] ?? ''),
            locale: (string) ($nextDraft['locale'] ?? 'bn'),
        );

        $session->draft_json = $nextDraft;
    }

    public function approve(BlogAiSession $session): array
    {
        $image = is_array($session->image_json) ? $session->image_json : [];
        if ($image === [] || empty($image['url'])) {
            throw ValidationException::withMessages([
                'ai' => 'No generated image to approve.',
            ]);
        }

        if (isset($image['review']) && is_array($image['review'])) {
            $image['review']['pass'] = true;
            $image['review']['approved_manually'] = true;
        }

        $this->applyImageToDraft($session, $image);
        $session->image_json = $image;
        $session->status = 'image_ready';
        $session->last_error = null;
        $session->saveIfJobCurrent();

        return $image;
    }

    private function assertDailyCapsAllow(BlogAiSession $session): void
    {
        $userId = (int) $session->user_id;
        $callsCap = (int) config('blog_ai.daily_ai_calls_cap', 80);
        $tokenCap = (int) config('blog_ai.daily_token_cap', 400000);
        $calls = BlogAiSession::dailyCalls($userId);
        $tokens = BlogAiSession::dailyTokens($userId);

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
}
