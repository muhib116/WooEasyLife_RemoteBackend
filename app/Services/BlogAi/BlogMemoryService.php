<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiMemory;
use App\Models\BlogCompetitorAnalysis;
use App\Models\BlogLearningInsight;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Standing memory for Blog AI — keywords, topics, instructions that compound over time.
 */
class BlogMemoryService
{
    /**
     * Compact block injected into every product brief / learning prompt.
     *
     * @return array<string, mixed>
     */
    public function promptBlock(?string $cluster = null): array
    {
        if (! config('blog_ai.memory.enabled', true) || ! Schema::hasTable('blog_ai_memories')) {
            return ['status' => 'disabled', 'items' => []];
        }

        $query = BlogAiMemory::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('hits')
            ->orderByDesc('id');

        if (filled($cluster)) {
            $query->where(function ($q) use ($cluster) {
                $q->whereNull('cluster')->orWhere('cluster', $cluster);
            });
        }

        $rows = $query->limit((int) config('blog_ai.memory.prompt_limit', 40))->get();
        if ($rows->isEmpty()) {
            return [
                'status' => 'empty',
                'note' => 'No standing memory yet — add keywords/topics/instructions in Blog Memory.',
                'items' => [],
            ];
        }

        $grouped = [
            'prefer_keywords' => [],
            'avoid_keywords' => [],
            'topics' => [],
            'instructions' => [],
            'brand_notes' => [],
            'lessons' => [],
        ];

        foreach ($rows as $row) {
            $entry = [
                'id' => $row->id,
                'content' => $row->content,
                'cluster' => $row->cluster,
                'source' => $row->source,
                'priority' => $row->priority,
            ];

            match ($row->type) {
                BlogAiMemory::TYPE_KEYWORD_PREFER => $grouped['prefer_keywords'][] = $entry,
                BlogAiMemory::TYPE_KEYWORD_AVOID => $grouped['avoid_keywords'][] = $entry,
                BlogAiMemory::TYPE_TOPIC => $grouped['topics'][] = $entry,
                BlogAiMemory::TYPE_INSTRUCTION => $grouped['instructions'][] = $entry,
                BlogAiMemory::TYPE_BRAND_NOTE => $grouped['brand_notes'][] = $entry,
                BlogAiMemory::TYPE_LESSON => $grouped['lessons'][] = $entry,
                default => null,
            };
        }

        return [
            'status' => 'ready',
            'count' => $rows->count(),
            'rules' => [
                'Obey standing_memory instructions and brand_notes on every draft.',
                'Prefer prefer_keywords when choosing focus/title angles.',
                'Never target avoid_keywords as primary focus.',
                'Use topics and lessons as durable editorial memory, not one-off tips.',
            ],
            ...$grouped,
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    public function markUsed(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === [] || ! Schema::hasTable('blog_ai_memories')) {
            return;
        }

        BlogAiMemory::query()
            ->whereIn('id', $ids)
            ->update([
                'hits' => \Illuminate\Support\Facades\DB::raw('hits + 1'),
                'last_used_at' => now(),
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(?string $type = null, int $limit = 100): array
    {
        if (! Schema::hasTable('blog_ai_memories')) {
            return [];
        }

        $query = BlogAiMemory::query()->orderByDesc('is_active')->orderByDesc('priority')->orderByDesc('id');
        if (filled($type)) {
            $query->where('type', $type);
        }

        return $query->limit($limit)->get()->map(fn (BlogAiMemory $row) => $this->toAdminRow($row))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminRow(BlogAiMemory $row): array
    {
        return [
            'id' => $row->id,
            'type' => $row->type,
            'content' => $row->content,
            'cluster' => $row->cluster,
            'source' => $row->source,
            'priority' => $row->priority,
            'hits' => $row->hits,
            'is_active' => $row->is_active,
            'meta' => $row->meta_json,
            'last_used_at' => optional($row->last_used_at)?->toIso8601String(),
            'created_at' => optional($row->created_at)?->toIso8601String(),
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array{type: string, content: string, cluster?: string|null, priority?: int, source?: string, meta?: array|null}  $data
     */
    public function upsert(array $data, ?int $userId = null): BlogAiMemory
    {
        if (! Schema::hasTable('blog_ai_memories')) {
            throw ValidationException::withMessages([
                'memory' => 'Memory table missing — run migrations.',
            ]);
        }

        $type = (string) ($data['type'] ?? '');
        if (! in_array($type, BlogAiMemory::TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => 'Invalid memory type.',
            ]);
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 500) {
            throw ValidationException::withMessages([
                'content' => 'Memory content is required (max 500 chars).',
            ]);
        }

        $key = BlogAiMemory::normalizeKey($content);
        $priority = isset($data['priority']) ? max(1, min(100, (int) $data['priority'])) : 50;
        $source = (string) ($data['source'] ?? BlogAiMemory::SOURCE_MANUAL);
        $cluster = filled($data['cluster'] ?? null) ? (string) $data['cluster'] : null;

        $row = BlogAiMemory::query()->firstOrNew([
            'type' => $type,
            'normalized_key' => $key,
        ]);

        $row->content = $content;
        $row->cluster = $cluster;
        $row->source = $source;
        $row->priority = max($row->priority ?: 0, $priority);
        $row->is_active = true;
        $row->user_id = $row->user_id ?: $userId;
        $row->meta_json = array_merge(
            is_array($row->meta_json) ? $row->meta_json : [],
            is_array($data['meta'] ?? null) ? $data['meta'] : [],
            ['last_reinforced_at' => now()->toIso8601String()],
        );
        $row->save();

        return $row;
    }

    public function setActive(int $id, bool $active): ?BlogAiMemory
    {
        $row = BlogAiMemory::query()->find($id);
        if (! $row) {
            return null;
        }
        $row->is_active = $active;
        $row->save();

        return $row;
    }

    public function delete(int $id): bool
    {
        return BlogAiMemory::query()->where('id', $id)->delete() > 0;
    }

    /**
     * Day-by-day absorption from learning snapshot → durable memory.
     *
     * @return array{created: int, reinforced: int}
     */
    public function absorbFromInsight(?BlogLearningInsight $insight = null): array
    {
        if (! config('blog_ai.memory.enabled', true)
            || ! config('blog_ai.memory.auto_absorb_learning', true)
            || ! Schema::hasTable('blog_ai_memories')) {
            return ['created' => 0, 'reinforced' => 0];
        }

        $insight ??= BlogLearningInsight::latestGlobal();
        if (! $insight) {
            return ['created' => 0, 'reinforced' => 0];
        }

        $payload = is_array($insight->payload_json) ? $insight->payload_json : [];
        $created = 0;
        $reinforced = 0;

        foreach (array_slice($payload['winning_keywords'] ?? [], 0, 12) as $kw) {
            $text = is_string($kw) ? $kw : (string) ($kw['keyword'] ?? $kw['focus_keyword'] ?? '');
            if ($text === '') {
                continue;
            }
            $before = BlogAiMemory::query()
                ->where('type', BlogAiMemory::TYPE_KEYWORD_PREFER)
                ->where('normalized_key', BlogAiMemory::normalizeKey($text))
                ->exists();
            $this->upsert([
                'type' => BlogAiMemory::TYPE_KEYWORD_PREFER,
                'content' => Str::limit($text, 190, ''),
                'priority' => 70,
                'source' => BlogAiMemory::SOURCE_LEARNING,
                'meta' => ['from' => 'winning_keywords'],
            ]);
            $before ? $reinforced++ : $created++;
        }

        foreach (array_slice($payload['underperforming_topics'] ?? [], 0, 8) as $row) {
            $text = is_array($row)
                ? (string) ($row['focus_keyword'] ?? $row['title'] ?? $row['hint'] ?? '')
                : (string) $row;
            if ($text === '') {
                continue;
            }
            $before = BlogAiMemory::query()
                ->where('type', BlogAiMemory::TYPE_KEYWORD_AVOID)
                ->where('normalized_key', BlogAiMemory::normalizeKey($text))
                ->exists();
            $this->upsert([
                'type' => BlogAiMemory::TYPE_KEYWORD_AVOID,
                'content' => Str::limit($text, 190, ''),
                'cluster' => is_array($row) ? ($row['cluster'] ?? null) : null,
                'priority' => 65,
                'source' => BlogAiMemory::SOURCE_LEARNING,
                'meta' => ['from' => 'underperforming_topics'],
            ]);
            $before ? $reinforced++ : $created++;
        }

        foreach (array_slice($payload['next_post_ideas'] ?? [], 0, 8) as $idea) {
            if (! is_array($idea)) {
                continue;
            }
            $text = trim((string) ($idea['seed_topic'] ?? $idea['suggested_title'] ?? ''));
            if ($text === '') {
                continue;
            }
            $before = BlogAiMemory::query()
                ->where('type', BlogAiMemory::TYPE_TOPIC)
                ->where('normalized_key', BlogAiMemory::normalizeKey($text))
                ->exists();
            $this->upsert([
                'type' => BlogAiMemory::TYPE_TOPIC,
                'content' => Str::limit($text, 190, ''),
                'cluster' => $idea['cluster'] ?? null,
                'priority' => 60,
                'source' => BlogAiMemory::SOURCE_LEARNING,
                'meta' => ['reason' => $idea['reason'] ?? null],
            ]);
            $before ? $reinforced++ : $created++;
        }

        foreach (array_slice($payload['writing_guidance'] ?? [], 0, 6) as $guide) {
            $text = trim((string) $guide);
            if ($text === '') {
                continue;
            }
            $before = BlogAiMemory::query()
                ->where('type', BlogAiMemory::TYPE_LESSON)
                ->where('normalized_key', BlogAiMemory::normalizeKey($text))
                ->exists();
            $this->upsert([
                'type' => BlogAiMemory::TYPE_LESSON,
                'content' => Str::limit($text, 500, ''),
                'priority' => 55,
                'source' => BlogAiMemory::SOURCE_LEARNING,
                'meta' => ['from' => 'writing_guidance'],
            ]);
            $before ? $reinforced++ : $created++;
        }

        foreach (array_slice($payload['gsc_keyword_seeds'] ?? [], 0, 10) as $seed) {
            $text = is_array($seed) ? (string) ($seed['query'] ?? '') : (string) $seed;
            if ($text === '') {
                continue;
            }
            $before = BlogAiMemory::query()
                ->where('type', BlogAiMemory::TYPE_KEYWORD_PREFER)
                ->where('normalized_key', BlogAiMemory::normalizeKey($text))
                ->exists();
            $this->upsert([
                'type' => BlogAiMemory::TYPE_KEYWORD_PREFER,
                'content' => Str::limit($text, 190, ''),
                'priority' => 75,
                'source' => BlogAiMemory::SOURCE_LEARNING,
                'meta' => [
                    'from' => 'gsc_keyword_seeds',
                    'bucket' => is_array($seed) ? ($seed['bucket'] ?? null) : null,
                ],
            ]);
            $before ? $reinforced++ : $created++;
        }

        return ['created' => $created, 'reinforced' => $reinforced];
    }

    /**
     * Absorb durable lessons from a competitor analysis.
     *
     * @return array{created: int, reinforced: int}
     */
    public function absorbFromCompetitor(BlogCompetitorAnalysis $analysis): array
    {
        if (! config('blog_ai.memory.enabled', true)
            || ! config('blog_ai.memory.auto_absorb_competitor', true)
            || ! Schema::hasTable('blog_ai_memories')) {
            return ['created' => 0, 'reinforced' => 0];
        }

        $insight = is_array($analysis->insight_json) ? $analysis->insight_json : [];
        $created = 0;
        $reinforced = 0;
        $cluster = $analysis->cluster;

        // Prefer open gap checklist items; fall back to classic must_cover_angles.
        $angles = [];
        foreach (array_slice($insight['gap_checklist'] ?? [], 0, 10) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (strtolower((string) ($row['status'] ?? 'open')) === 'covered') {
                continue;
            }
            $gap = trim((string) ($row['gap'] ?? ''));
            if ($gap !== '') {
                $angles[] = $gap;
            }
        }
        if ($angles === []) {
            $angles = array_slice($insight['must_cover_angles'] ?? [], 0, 6);
        }

        foreach (array_slice($angles, 0, 6) as $angle) {
            $text = trim((string) $angle);
            if ($text === '') {
                continue;
            }
            $before = BlogAiMemory::query()
                ->where('type', BlogAiMemory::TYPE_TOPIC)
                ->where('normalized_key', BlogAiMemory::normalizeKey($text))
                ->exists();
            $this->upsert([
                'type' => BlogAiMemory::TYPE_TOPIC,
                'content' => Str::limit($text, 190, ''),
                'cluster' => $cluster,
                'priority' => 68,
                'source' => BlogAiMemory::SOURCE_COMPETITOR,
                'meta' => ['keyword' => $analysis->keyword],
            ]);
            $before ? $reinforced++ : $created++;
        }

        foreach (array_slice($insight['writing_guidance'] ?? [], 0, 4) as $guide) {
            $text = trim((string) $guide);
            if ($text === '') {
                continue;
            }
            $before = BlogAiMemory::query()
                ->where('type', BlogAiMemory::TYPE_LESSON)
                ->where('normalized_key', BlogAiMemory::normalizeKey($text))
                ->exists();
            $this->upsert([
                'type' => BlogAiMemory::TYPE_LESSON,
                'content' => Str::limit($text, 500, ''),
                'cluster' => $cluster,
                'priority' => 62,
                'source' => BlogAiMemory::SOURCE_COMPETITOR,
                'meta' => ['keyword' => $analysis->keyword],
            ]);
            $before ? $reinforced++ : $created++;
        }

        if (filled($analysis->keyword)) {
            $this->upsert([
                'type' => BlogAiMemory::TYPE_KEYWORD_PREFER,
                'content' => Str::limit((string) $analysis->keyword, 190, ''),
                'cluster' => $cluster,
                'priority' => 72,
                'source' => BlogAiMemory::SOURCE_COMPETITOR,
            ]);
        }

        return ['created' => $created, 'reinforced' => $reinforced];
    }

    /**
     * @return array{active: int, total: int, by_type: array<string, int>}
     */
    public function stats(): array
    {
        if (! Schema::hasTable('blog_ai_memories')) {
            return ['active' => 0, 'total' => 0, 'by_type' => []];
        }

        $total = BlogAiMemory::query()->count();
        $active = BlogAiMemory::query()->where('is_active', true)->count();
        $byType = BlogAiMemory::query()
            ->where('is_active', true)
            ->selectRaw('type, COUNT(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type')
            ->map(fn ($c) => (int) $c)
            ->all();

        return [
            'active' => $active,
            'total' => $total,
            'by_type' => $byType,
        ];
    }
}
