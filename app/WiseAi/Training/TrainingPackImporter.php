<?php

namespace App\WiseAi\Training;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseExperienceSignal;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseLanguageEntry;
use App\Models\WiseAi\WiseLanguageReview;
use App\WiseAi\Experience\ExperienceRecorder;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Language\PlatformLexicon;
use App\WiseAi\Language\RegionCode;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Import training JSON → knowledge drafts + experience signals + language Discovery reviews.
 * Never auto-publishes Knowledge or Language.
 *
 * Target: merchant key (scoped) or null = platform (all keys).
 * Experience is merchant-only — skipped on platform target.
 */
class TrainingPackImporter
{
    /** @var list<string> */
    private const LANGUAGE_CATEGORIES = [
        'abbrev',
        'sms',
        'banglish',
        'phonetic',
        'commerce',
        'messenger',
        'filler',
    ];

    /** @var list<string> */
    private const LANES = ['knowledge', 'language', 'experience'];

    private const MAX_ERRORS = 25;

    public function __construct(
        private ExperienceRecorder $experience,
    ) {}

    /**
     * @param  array<string, mixed>  $pack
     * @return array{
     *     knowledge_created: int,
     *     knowledge_updated: int,
     *     experience_created: int,
     *     experience_reused: int,
     *     language_created: int,
     *     language_updated: int,
     *     language_reused: int,
     *     skipped: int,
     *     applied: int,
     *     errors: list<string>,
     *     knowledge_ids: list<int>,
     *     next_steps: list<string>,
     *     target: string
     * }
     */
    public function import(?WiseApiKey $apiKey, array $pack, bool $importExperience = true): array
    {
        $version = (string) ($pack['version'] ?? '');
        if ($version !== '' && $version !== TrainingSchema::VERSION) {
            throw new InvalidArgumentException(
                'Unsupported training pack version. Expected '.TrainingSchema::VERSION.'.'
            );
        }

        $items = $pack['items'] ?? null;
        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('Pack must include a non-empty items array.');
        }

        $platform = $apiKey === null;
        $stats = [
            'knowledge_created' => 0,
            'knowledge_updated' => 0,
            'experience_created' => 0,
            'experience_reused' => 0,
            'language_created' => 0,
            'language_updated' => 0,
            'language_reused' => 0,
            'skipped' => 0,
            'errors' => [],
            'knowledge_ids' => [],
            'next_steps' => [],
            'target' => $platform ? 'platform' : 'merchant',
        ];

        foreach (array_values($items) as $index => $raw) {
            if (! is_array($raw)) {
                $this->skip($stats, "items[{$index}]: not an object");

                continue;
            }

            $lane = strtolower(trim((string) ($raw['lane'] ?? 'knowledge')));
            if (! in_array($lane, self::LANES, true)) {
                $this->skip($stats, "items[{$index}]: unknown lane “{$lane}” (use knowledge|language|experience)");

                continue;
            }

            try {
                if ($lane === 'experience') {
                    if ($platform) {
                        $this->skip($stats, "items[{$index}]: experience requires a merchant API key — skipped on platform target");

                        continue;
                    }
                    if (! $importExperience) {
                        $stats['skipped']++;

                        continue;
                    }
                    $result = $this->importExperience($apiKey, $raw, $index);
                    if ($result['created']) {
                        $stats['experience_created']++;
                    } else {
                        $stats['experience_reused']++;
                    }
                } elseif ($lane === 'language') {
                    $result = $this->importLanguage($apiKey, $raw);
                    if ($result['status'] === 'created') {
                        $stats['language_created']++;
                    } elseif ($result['status'] === 'updated') {
                        $stats['language_updated']++;
                    } else {
                        $stats['language_reused']++;
                    }
                } else {
                    $result = $this->importKnowledge($apiKey, $raw);
                    if ($result['created']) {
                        $stats['knowledge_created']++;
                    } else {
                        $stats['knowledge_updated']++;
                    }
                    $stats['knowledge_ids'][] = $result['item']->id;
                }
            } catch (InvalidArgumentException $e) {
                $this->skip($stats, "items[{$index}]: ".$e->getMessage());
            }
        }

        $applied = $stats['knowledge_created'] + $stats['knowledge_updated']
            + $stats['language_created'] + $stats['language_updated'] + $stats['language_reused']
            + $stats['experience_created'] + $stats['experience_reused'];

        $stats['next_steps'] = $this->nextSteps($stats, $platform);
        $stats['applied'] = $applied;

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function skip(array &$stats, string $error): void
    {
        $stats['skipped']++;
        if (count($stats['errors']) < self::MAX_ERRORS) {
            $stats['errors'][] = $error;
        }
    }

    /**
     * @param  array<string, int|list<mixed>|string>  $stats
     * @return list<string>
     */
    private function nextSteps(array $stats, bool $platform): array
    {
        $steps = [];
        if (($stats['knowledge_created'] + $stats['knowledge_updated']) > 0) {
            $steps[] = $platform
                ? 'Open Knowledge → filter Platform drafts → Publish (shared facts for all keys).'
                : 'Open Knowledge → review drafts → Publish (facts stay draft until you publish).';
        }
        if (($stats['language_created'] + $stats['language_updated']) > 0) {
            $steps[] = $platform
                ? 'Open Language → Train queue → Approve with Platform scope (BCLC surface + recompile).'
                : 'Open Language → filter Open → Approve Train rows (abbrev/Banglish). Prefills are ready.';
        }
        if (($stats['experience_created']) > 0) {
            $steps[] = 'Experience signals are live soft-hints (not facts). No publish step.';
        }
        if ($stats['skipped'] > 0) {
            $steps[] = 'Some rows were skipped — see errors and fix JSON, then re-import (safe upsert).';
        }
        if ($steps === []) {
            $steps[] = 'Nothing new applied (reused existing). Adjust pack or promote/publish what is already queued.';
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{item: WiseKnowledgeItem, created: bool}
     */
    private function importKnowledge(?WiseApiKey $apiKey, array $raw): array
    {
        $platform = $apiKey === null;
        $title = trim((string) ($raw['title'] ?? ''));
        $answer = trim((string) ($raw['answer'] ?? ''));
        if ($title === '' || $answer === '') {
            throw new InvalidArgumentException('knowledge items need title and answer.');
        }

        $type = strtolower(trim((string) ($raw['type'] ?? KnowledgeSchema::KIND_FAQ)));
        if ($type === 'product' || $type === 'offer') {
            $type = KnowledgeSchema::KIND_OFFER;
        }
        if (! in_array($type, KnowledgeSchema::kinds(), true)) {
            $type = KnowledgeSchema::KIND_FAQ;
        }
        if ($type === KnowledgeSchema::KIND_OTHER) {
            $type = KnowledgeSchema::KIND_FACT;
        }

        if ($platform) {
            $scope = KnowledgeSchema::SCOPE_PLATFORM;
        } else {
            $scope = strtolower(trim((string) ($raw['scope'] ?? KnowledgeSchema::SCOPE_MERCHANT)));
            if (! in_array($scope, KnowledgeSchema::scopes(), true)) {
                $scope = KnowledgeSchema::SCOPE_MERCHANT;
            }
            // Merchant Train cannot silently create platform knowledge.
            if ($scope === KnowledgeSchema::SCOPE_PLATFORM) {
                $scope = KnowledgeSchema::SCOPE_MERCHANT;
            }
        }

        $keywords = [];
        if (is_array($raw['keywords'] ?? null)) {
            foreach ($raw['keywords'] as $kw) {
                $k = trim((string) $kw);
                if ($k !== '') {
                    $keywords[] = mb_substr($k, 0, 60);
                }
            }
        }

        $meta = [
            'via' => 'training_pack',
            'training_version' => TrainingSchema::VERSION,
            'train_target' => $platform ? 'platform' : 'merchant',
        ];
        foreach (['platform', 'offer_kind', 'sku', 'region'] as $field) {
            if (! empty($raw[$field])) {
                $meta[$field] = mb_substr(trim((string) $raw[$field]), 0, 60);
            }
        }
        if (! empty($raw['pricing_menu'])) {
            $meta['pricing_menu'] = true;
        }

        $externalId = trim((string) ($raw['external_id'] ?? ''));
        if ($type === KnowledgeSchema::KIND_OFFER && $externalId === '') {
            throw new InvalidArgumentException('type=product requires external_id.');
        }

        $question = isset($raw['question']) ? mb_substr(trim((string) $raw['question']), 0, 2000) : null;
        $questionKey = $question ?? '';

        // Prefer draft upsert; never silently mutate a published row from Train.
        $draftQuery = $this->knowledgeForTarget($apiKey)
            ->where('type', $type)
            ->where('title', mb_substr($title, 0, 191))
            ->where('status', 'draft');
        if ($questionKey === '') {
            $draftQuery->where(function ($q) {
                $q->whereNull('question')->orWhere('question', '');
            });
        } else {
            $draftQuery->where('question', $questionKey);
        }
        if ($externalId !== '') {
            $draftQuery->where('external_id', mb_substr($externalId, 0, 191));
        }

        $existingDraft = $draftQuery->first();
        if ($existingDraft) {
            $mergedMeta = is_array($existingDraft->meta) ? array_merge($existingDraft->meta, $meta) : $meta;
            $existingDraft->update([
                'scope' => $scope,
                'answer' => mb_substr($answer, 0, 5000),
                'keywords' => $keywords,
                'meta' => $mergedMeta,
                'external_id' => $externalId !== '' ? mb_substr($externalId, 0, 191) : $existingDraft->external_id,
            ]);

            return ['item' => $existingDraft->fresh(), 'created' => false];
        }

        $publishedQuery = $this->knowledgeForTarget($apiKey)
            ->where('type', $type)
            ->where('status', 'published');

        if ($type === KnowledgeSchema::KIND_OFFER && $externalId !== '') {
            $publishedQuery->where('external_id', mb_substr($externalId, 0, 191));
        } else {
            $publishedQuery
                ->where('title', mb_substr($title, 0, 191))
                ->when(
                    $questionKey === '',
                    fn ($q) => $q->where(function ($qq) {
                        $qq->whereNull('question')->orWhere('question', '');
                    }),
                    fn ($q) => $q->where('question', $questionKey),
                );
        }

        if ($publishedQuery->exists()) {
            throw new InvalidArgumentException(
                'matching published knowledge already exists — edit/unpublish in Knowledge, or change title/question.'
            );
        }

        $item = WiseKnowledgeItem::create([
            'wise_api_key_id' => $apiKey?->id,
            'external_id' => $externalId !== '' ? mb_substr($externalId, 0, 191) : null,
            'type' => $type,
            'scope' => $scope,
            'title' => mb_substr($title, 0, 191),
            'question' => $question,
            'answer' => mb_substr($answer, 0, 5000),
            'keywords' => $keywords,
            'meta' => $meta,
            'status' => 'draft',
            'version' => 1,
        ]);

        return ['item' => $item, 'created' => true];
    }

    /**
     * Seed Language Discovery as open reviews — never publish / compile.
     *
     * @param  array<string, mixed>  $raw
     * @return array{review: WiseLanguageReview|null, status: 'created'|'updated'|'reused'}
     */
    private function importLanguage(?WiseApiKey $apiKey, array $raw): array
    {
        $from = $this->normalizeSurface((string) ($raw['from'] ?? $raw['token'] ?? $raw['surface'] ?? ''));
        if ($from === '') {
            throw new InvalidArgumentException('language items need from (surface text).');
        }

        if (in_array($from, PlatformLexicon::AMBIGUOUS, true)) {
            throw new InvalidArgumentException(
                "surface “{$from}” is ambiguous and cannot be promoted — skip or use a clearer form."
            );
        }

        $category = strtolower(trim((string) ($raw['category'] ?? $raw['type'] ?? 'banglish')));
        if (! in_array($category, self::LANGUAGE_CATEGORIES, true)) {
            $category = 'banglish';
        }

        $to = $this->normalizeExpansion((string) ($raw['to'] ?? ''), $category);
        if ($category !== 'filler' && $to === '') {
            throw new InvalidArgumentException('language items need to (canonical expansion), unless category=filler.');
        }
        if ($category !== 'filler' && $from === $to) {
            throw new InvalidArgumentException('language from and to must differ.');
        }

        $publishedQuery = WiseLanguageEntry::query()
            ->where('from_text', $from)
            ->where('status', 'published')
            ->where('enabled', true);
        if ($apiKey === null) {
            // Platform train: only treat platform-published as already covered.
            $publishedQuery->whereNull('wise_api_key_id');
        } else {
            $publishedQuery->where(function ($q) use ($apiKey) {
                $q->where('wise_api_key_id', $apiKey->id)
                    ->orWhereNull('wise_api_key_id');
            });
        }
        if ($publishedQuery->exists()) {
            return ['review' => null, 'status' => 'reused'];
        }

        $packSlug = trim((string) ($raw['pack_slug'] ?? ''));
        $region = RegionCode::normalize((string) ($raw['region'] ?? ''));
        if ($packSlug === '' && $region !== null) {
            $packSlug = 'region-'.$region;
        }
        if ($packSlug === '') {
            $packSlug = 'core-bd';
        }
        $packSlug = mb_substr($packSlug, 0, 80);

        $hasSpace = str_contains($from, ' ');
        $kind = match ($category) {
            'abbrev', 'sms', 'messenger' => $hasSpace ? 'phrase' : 'abbrev',
            'filler' => 'filler',
            default => $hasSpace ? 'phrase' : 'token',
        };

        $conceptKey = trim((string) ($raw['concept_key'] ?? ''));
        if ($conceptKey === '') {
            $slug = preg_replace('/[^a-z0-9_]+/u', '_', $from) ?: 'surface';
            $conceptKey = $category.'.'.mb_substr((string) $slug, 0, 80);
        }

        $existing = WiseLanguageReview::query()
            ->when(
                $apiKey === null,
                fn ($q) => $q->whereNull('wise_api_key_id'),
                fn ($q) => $q->where('wise_api_key_id', $apiKey->id),
            )
            ->where('token', $from)
            ->where('status', 'open')
            ->first();

        $payload = [
            'kind' => $kind,
            'channel' => 'train',
            'sample_text' => $category === 'filler' ? null : $to,
            'suggested_pack_slug' => $packSlug,
            'suggested_category' => $category,
            'suggested_concept_key' => mb_substr($conceptKey, 0, 120),
            'rank_score' => 900,
            'last_seen_at' => now(),
        ];

        if ($existing) {
            $existing->update(array_merge($payload, [
                'hit_count' => max(1, (int) $existing->hit_count) + 1,
            ]));

            return ['review' => $existing->fresh(), 'status' => 'updated'];
        }

        $review = WiseLanguageReview::create(array_merge($payload, [
            'wise_api_key_id' => $apiKey?->id,
            'token' => $from,
            'hit_count' => 1,
            'key_breadth' => $apiKey === null ? 99 : 1,
            'status' => 'open',
        ]));

        return ['review' => $review, 'status' => 'created'];
    }

    /** Match DiscoveryPromoter: lowercase + collapse whitespace. */
    private function normalizeSurface(string $raw): string
    {
        $raw = preg_replace('/\s+/u', ' ', trim($raw)) ?? trim($raw);
        $raw = mb_strtolower($raw);

        return mb_substr($raw, 0, 191);
    }

    private function normalizeExpansion(string $raw, string $category): string
    {
        $raw = preg_replace('/\s+/u', ' ', trim($raw)) ?? trim($raw);
        if ($raw === '') {
            return '';
        }
        // Keep Bengali / mixed script casing; lowercase pure Latin abbrev/sms targets.
        if (in_array($category, ['abbrev', 'sms', 'messenger'], true)
            && preg_match('/^[\x20-\x7E]+$/u', $raw) === 1) {
            $raw = mb_strtolower($raw);
        }

        return mb_substr($raw, 0, 191);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{signal: WiseExperienceSignal, created: bool}
     */
    private function importExperience(WiseApiKey $apiKey, array $raw, int $index): array
    {
        $encoded = json_encode($raw);
        $fingerprint = is_string($encoded) ? sha1($encoded) : sha1((string) $index);
        $idem = trim((string) ($raw['idempotency_key'] ?? ''));
        if ($idem === '') {
            // Content-stable (no pack index) so reorder/re-import does not duplicate.
            $idem = 'train:'.$apiKey->id.':'.$fingerprint;
        }

        $existing = WiseExperienceSignal::query()
            ->where('wise_api_key_id', $apiKey->id)
            ->where('idempotency_key', mb_substr($idem, 0, 191))
            ->first();
        if ($existing) {
            return ['signal' => $existing, 'created' => false];
        }

        $signal = $this->experience->fromExternal($apiKey, [
            'signal_type' => (string) ($raw['signal_type'] ?? 'external'),
            'intent' => $raw['intent'] ?? null,
            'action' => $raw['action'] ?? null,
            'source' => $raw['source'] ?? 'training',
            'pattern_key' => $raw['pattern_key'] ?? null,
            'weight' => isset($raw['weight']) ? (float) $raw['weight'] : 1.0,
            'idempotency_key' => $idem,
            'context' => [
                'note' => $raw['note'] ?? null,
                'via' => 'training_pack',
            ],
        ]);

        return [
            'signal' => $signal,
            'created' => (bool) $signal->wasRecentlyCreated,
        ];
    }

    /** @return Builder<WiseKnowledgeItem> */
    private function knowledgeForTarget(?WiseApiKey $apiKey): Builder
    {
        return WiseKnowledgeItem::query()->when(
            $apiKey === null,
            fn ($q) => $q->whereNull('wise_api_key_id'),
            fn ($q) => $q->where('wise_api_key_id', $apiKey->id),
        );
    }
}
