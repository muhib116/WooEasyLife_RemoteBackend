<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BlogAiSession extends Model
{
    public const BUSY_STATUSES = [
        'researching',
        'generating_hooks',
        'generating_outline',
        'generating_draft',
        'generating_image',
    ];

    public const READY_STATUSES = [
        'keywords_ready',
        'hooks_ready',
        'outline_ready',
        'draft_ready',
        'image_ready',
    ];

    protected $fillable = [
        'user_id',
        'status',
        'resume_status',
        'job_token',
        'locale',
        'cluster',
        'seed_topic',
        'keywords_json',
        'hooks_json',
        'selected_hook_ids',
        'outline_json',
        'link_plan_json',
        'draft_json',
        'image_json',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'ai_calls',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'hooks_json' => 'array',
            'selected_hook_ids' => 'array',
            'outline_json' => 'array',
            'link_plan_json' => 'array',
            'draft_json' => 'array',
            'image_json' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'ai_calls' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isBusy(): bool
    {
        return in_array($this->status, self::BUSY_STATUSES, true);
    }

    /**
     * If a queue worker died mid-job, unlock the session after a stale window.
     */
    public function recoverIfStale(): bool
    {
        if (! $this->isBusy()) {
            return false;
        }

        $minutes = max(3, (int) config('blog_ai.busy_stale_minutes', 5));
        if ($this->updated_at && $this->updated_at->gt(now()->subMinutes($minutes))) {
            return false;
        }

        $this->invalidateJobToken();
        $this->last_error = 'Previous AI job timed out or the queue worker stopped. You can retry.';
        $this->status = 'failed';
        $this->save();

        return true;
    }

    public function invalidateJobToken(): void
    {
        $this->job_token = bin2hex(random_bytes(16));
    }

    /**
     * Persist only if this in-memory job_token still matches the DB (blocks stale jobs after unlock).
     */
    public function saveIfJobCurrent(): bool
    {
        $expected = $this->getOriginal('job_token') ?? $this->job_token;
        if (filled($expected)) {
            $current = static::query()->whereKey($this->id)->value('job_token');
            if ((string) $current !== (string) $expected) {
                Log::info('Blog AI write discarded — job token mismatch', [
                    'session_id' => $this->id,
                ]);

                return false;
            }
        }

        return $this->save();
    }

    public static function dailyCallsKey(int $userId): string
    {
        return 'blog_ai_daily:'.$userId.':'.now()->toDateString().':calls';
    }

    public static function dailyTokensKey(int $userId): string
    {
        return 'blog_ai_daily:'.$userId.':'.now()->toDateString().':tokens';
    }

    public static function dailyCalls(int $userId): int
    {
        return (int) Cache::get(self::dailyCallsKey($userId), 0);
    }

    public static function dailyTokens(int $userId): int
    {
        return (int) Cache::get(self::dailyTokensKey($userId), 0);
    }

    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}  $usage
     */
    public function addUsage(array $usage): void
    {
        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($prompt + $completion));

        $this->prompt_tokens = (int) $this->prompt_tokens + $prompt;
        $this->completion_tokens = (int) $this->completion_tokens + $completion;
        $this->total_tokens = (int) $this->total_tokens + $total;
        $this->ai_calls = (int) $this->ai_calls + 1;

        $this->bumpDailyUsage(1, $total);
    }

    public function bumpDailyUsage(int $calls = 1, int $tokens = 0): void
    {
        self::recordUserDailyUsage((int) $this->user_id, $calls, $tokens);
    }

    public static function recordUserDailyUsage(int $userId, int $calls = 1, int $tokens = 0): void
    {
        if ($userId < 1) {
            return;
        }

        $ttl = now()->endOfDay()->addHour();
        $callsKey = self::dailyCallsKey($userId);
        $tokensKey = self::dailyTokensKey($userId);

        Cache::add($callsKey, 0, $ttl);
        Cache::add($tokensKey, 0, $ttl);

        if ($calls > 0) {
            Cache::increment($callsKey, $calls);
        }
        if ($tokens > 0) {
            Cache::increment($tokensKey, $tokens);
        }
    }

    public function estimatedCostUsd(): float
    {
        $model = app(\App\Services\LandingSettingsService::class)->openaiBlogModel() ?: 'gpt-4o-mini';
        $rates = config('blog_ai.model_rates.'.$model, []);
        $promptRate = (float) ($rates['prompt'] ?? config('blog_ai.usd_per_1k_prompt_tokens', 0.00015));
        $completionRate = (float) ($rates['completion'] ?? config('blog_ai.usd_per_1k_completion_tokens', 0.0006));
        $imageFlat = (float) config('blog_ai.usd_per_image', 0.04);
        $imageCalls = is_array($this->image_json) && ! empty($this->image_json['media_id']) ? 1 : 0;

        return round(
            (($this->prompt_tokens / 1000) * $promptRate)
            + (($this->completion_tokens / 1000) * $completionRate)
            + ($imageCalls * $imageFlat),
            5
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'resume_status' => $this->resume_status,
            'busy' => $this->isBusy(),
            'locale' => $this->locale,
            'cluster' => $this->cluster,
            'seed_topic' => $this->seed_topic,
            'keywords' => $this->keywords_json,
            'hooks' => $this->hooks_json,
            'selected_hook_ids' => $this->selected_hook_ids,
            'outline' => $this->outline_json,
            'link_plan' => $this->link_plan_json,
            'draft' => $this->draft_json,
            'image' => $this->image_json,
            'usage' => [
                'prompt_tokens' => $this->prompt_tokens,
                'completion_tokens' => $this->completion_tokens,
                'total_tokens' => $this->total_tokens,
                'ai_calls' => $this->ai_calls,
                'estimated_usd' => $this->estimatedCostUsd(),
            ],
            'last_error' => $this->last_error,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
