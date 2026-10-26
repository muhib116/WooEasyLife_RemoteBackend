<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One store landing page generation (WEL Elementor widget → Config → AI content).
 */
class LandingAiJob extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [self::STATUS_QUEUED, self::STATUS_RUNNING];

    protected $fillable = [
        'uuid',
        'personal_access_token_id',
        'user_id',
        'domain',
        'status',
        'model',
        'openai_response_id',
        'spec',
        'request_meta',
        'result',
        'warnings',
        'error',
        'input_tokens',
        'output_tokens',
        'finished_at',
    ];

    protected $hidden = [
        'spec',
    ];

    protected function casts(): array
    {
        return [
            'spec' => 'array',
            'request_meta' => 'array',
            'result' => 'array',
            'warnings' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LandingAiJob $job): void {
            if (! $job->uuid) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
