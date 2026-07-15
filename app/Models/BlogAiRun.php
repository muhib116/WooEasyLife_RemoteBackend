<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogAiRun extends Model
{
    public const STATUSES = [
        'pending',
        'running',
        'completed',
        'completed_needs_review',
        'failed',
        'cancelled',
    ];

    public const ACTIVE_STATUSES = [
        'pending',
        'running',
    ];

    public const SUCCESS_STATUSES = [
        'completed',
        'completed_needs_review',
    ];

    protected $fillable = [
        'blog_ai_session_id',
        'user_id',
        'blog_post_id',
        'mode',
        'status',
        'current_step',
        'progress_pct',
        'live_score',
        'score_breakdown',
        'step_log',
        'revision_counts',
        'input_json',
        'last_error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'score_breakdown' => 'array',
            'step_log' => 'array',
            'revision_counts' => 'array',
            'input_json' => 'array',
            'progress_pct' => 'integer',
            'live_score' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BlogAiSession::class, 'blog_ai_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'completed_needs_review', 'failed', 'cancelled'], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function needsReview(): bool
    {
        return $this->status === 'completed_needs_review'
            || ! empty(($this->input_json['soft_pass'] ?? false))
            || ! empty(($this->input_json['image_auto_approved'] ?? false));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public function appendLog(array $entry): void
    {
        $log = is_array($this->step_log) ? $this->step_log : [];
        $log[] = array_merge([
            'at' => now()->toIso8601String(),
        ], $entry);
        $this->step_log = $log;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'blog_ai_session_id' => $this->blog_ai_session_id,
            'blog_post_id' => $this->blog_post_id,
            'mode' => $this->mode,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'progress_pct' => $this->progress_pct,
            'live_score' => $this->live_score,
            'score_breakdown' => $this->score_breakdown,
            'step_log' => $this->step_log ?? [],
            'revision_counts' => $this->revision_counts ?? [],
            'input' => $this->input_json,
            'needs_review' => $this->needsReview(),
            'soft_pass' => (bool) ($this->input_json['soft_pass'] ?? false),
            'image_auto_approved' => (bool) ($this->input_json['image_auto_approved'] ?? false),
            'last_error' => $this->last_error,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'finished_at' => optional($this->finished_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
