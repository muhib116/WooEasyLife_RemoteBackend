<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseLanguageArtifact;
use App\Models\WiseAi\WiseLanguageEntry;
use App\Models\WiseAi\WiseLanguagePack;
use App\Models\WiseAi\WiseLanguagePackAssignment;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseLanguageSurface;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\DecideEngine;
use App\WiseAi\Experience\ExperienceRecorder;
use App\WiseAi\Explain\ExplainBuilder;
use App\WiseAi\Governance\Constitution;
use App\WiseAi\Governance\MerchantPolicy;
use App\WiseAi\Governance\PolicyPack;
use App\WiseAi\Governance\WisePermission;
use App\WiseAi\Intelligence\AiHealthScore;
use App\WiseAi\Intelligence\FleetAlerts;
use App\WiseAi\Intelligence\FleetHealth;
use App\WiseAi\Intelligence\HealAlerts;
use App\WiseAi\Intelligence\MerchantIntelligence;
use App\WiseAi\Intelligence\MetricDefinitions;
use App\WiseAi\Knowledge\KnowledgeAnswerRegenerator;
use App\WiseAi\Knowledge\KnowledgePublisher;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\RelatedQuestionSuggester;
use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use App\WiseAi\Knowledge\SeededKnowledge;
use App\WiseAi\Language\LanguageNormalizer;
use App\WiseAi\Language\LlmLanguageConfig;
use App\WiseAi\Language\PlatformLexicon;
use App\WiseAi\Learning\GapAutoDrafter;
use App\WiseAi\Learning\LearningInbox;
use App\WiseAi\Learning\ReasonCodes;
use App\WiseAi\Playground\PlaygroundCoach;
use App\WiseAi\Playground\PlaygroundCoachApplier;
use App\WiseAi\Training\TrainingPackGenerator;
use App\WiseAi\Training\TrainingPackImporter;
use App\WiseAi\Training\TrainingPrompt;
use App\WiseAi\Training\TrainingSchema;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WiseAiAdminController extends Controller
{
    public function dashboard(AiHealthScore $health, HealAlerts $healAlerts, LlmLanguageConfig $llm): Response
    {
        $today = now()->startOfDay();
        $todayTurns = WiseTurn::where('created_at', '>=', $today);
        $live = $health->live(24);
        $gapsOpen = WiseTurn::where('gap', true)->whereNull('gap_handled_at')->count();
        $heal = $healAlerts->fromLive($live, $gapsOpen);

        return Inertia::render('WiseAi/Dashboard', [
            'stats' => [
                'turns_today' => (clone $todayTurns)->count(),
                'turns_total' => WiseTurn::count(),
                'avg_confidence' => round((float) WiseTurn::where('created_at', '>=', $today)
                    ->get(['decision'])
                    ->avg(fn (WiseTurn $turn) => (int) ($turn->decision['confidence'] ?? 0))),
                'active_keys' => WiseApiKey::where('status', 'active')->count(),
                'gaps_today' => WiseTurn::where('created_at', '>=', $today)->where('gap', true)->count(),
                'gaps_open' => $gapsOpen,
                'assist_pending' => WiseTurn::query()
                    ->whereIn('decision->action', ['suggest_reply', 'clarify'])
                    ->whereDoesntHave('feedbacks')
                    ->count(),
                'language_open' => WiseLanguageReview::where('status', 'open')->count(),
                'learning_open' => app(LearningInbox::class)->stats()['open_total'],
                'needs_human_today' => WiseTurn::where('created_at', '>=', $today)
                    ->get(['decision'])
                    ->filter(fn (WiseTurn $turn) => ($turn->decision['action'] ?? '') === 'needs_human')
                    ->count(),
                'published_knowledge' => WiseKnowledgeItem::where('status', 'published')->count(),
                'knowledge_drafts' => WiseKnowledgeItem::where('status', 'draft')->count(),
                'brain_version' => DecideEngine::BRAIN_VERSION,
            ],
            'live' => $live,
            'heal_alerts' => $heal,
            'heal_alerts_version' => HealAlerts::VERSION,
            'llm_pipeline' => [
                'enabled' => $llm->enabled(),
                'key_set' => $llm->hasApiKey(),
                'model' => $llm->model(),
            ],
            'recentTurns' => WiseTurn::with(['apiKey:id,name', 'latestFeedback'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (WiseTurn $turn) => $this->turnRow($turn)),
        ]);
    }

    public function dashboardLive(AiHealthScore $health, HealAlerts $healAlerts, LlmLanguageConfig $llm): JsonResponse
    {
        $live = $health->live(24);
        $gapsOpen = WiseTurn::where('gap', true)->whereNull('gap_handled_at')->count();

        return response()->json([
            'ok' => true,
            'live' => $live,
            'heal_alerts' => $healAlerts->fromLive($live, $gapsOpen),
            'heal_alerts_version' => HealAlerts::VERSION,
            'llm_pipeline' => [
                'enabled' => $llm->enabled(),
                'key_set' => $llm->hasApiKey(),
                'model' => $llm->model(),
            ],
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'polled_at' => now()->toIso8601String(),
        ]);
    }

    public function tutorials(): Response
    {
        return Inertia::render('WiseAi/Tutorials', [
            'brain_version' => DecideEngine::BRAIN_VERSION,
        ]);
    }

    public function train(Request $request, LlmLanguageConfig $llm, WisePermission $perms): Response
    {
        return Inertia::render('WiseAi/Train', [
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'schema_version' => TrainingSchema::VERSION,
            'example_pack' => TrainingSchema::examplePack(),
            'example_platform_pack' => TrainingSchema::examplePlatformPack(),
            'starter_packs' => TrainingSchema::starterPacks(),
            'prompts' => TrainingPrompt::all(),
            'prompt_types' => TrainingPrompt::typeOptions(),
            'apiKeys' => WiseApiKey::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'key_prefix']),
            'llm' => [
                'enabled' => $llm->enabled(),
                'key_set' => $llm->hasApiKey(),
                'model' => $llm->model(),
            ],
            'can_edit' => $perms->canEdit($request->user()),
            'first_learning_bn' => TrainingPrompt::instructionsBn(),
            'recommended_types_bn' => [
                ['value' => 'language', 'why' => 'টার্গেট ২৫ সারফেস — দ্রুত বোঝাপড়া'],
                ['value' => 'knowledge', 'why' => 'টার্গেট ২০ FAQ — ডেলিভারি/পেমেন্ট/রিটার্ন'],
                ['value' => 'platform', 'why' => 'টার্গেট ২৪ — সব স্টোরের নিরাপদ স্ক্রিপ্ট+slang'],
            ],
            'volume_by_type' => collect(TrainingPrompt::types())
                ->mapWithKeys(fn (string $t) => [$t => TrainingPrompt::volumeFor($t)])
                ->all(),
        ]);
    }

    public function importTrainingPack(
        Request $request,
        TrainingPackImporter $importer,
        WisePermission $perms,
    ): JsonResponse {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to import training.'], 403);
        }

        $validated = $request->validate([
            'target' => 'nullable|in:merchant,platform',
            'wise_api_key_id' => 'nullable|integer|exists:wise_api_keys,id',
            'pack' => 'required|array',
            'pack.items' => 'required|array|min:1|max:200',
            'import_experience' => 'nullable|boolean',
            'prompt_type' => 'nullable|in:merchant,platform,knowledge,language,experience',
        ]);

        $target = (string) ($validated['target'] ?? 'merchant');
        $promptType = TrainingPrompt::normalizeType($validated['prompt_type'] ?? null);
        $pack = $validated['pack'];
        $lanesDropped = 0;
        if (! empty($validated['prompt_type'])) {
            $filtered = TrainingPrompt::filterPack($pack, $promptType);
            $pack = $filtered['pack'];
            $lanesDropped = (int) $filtered['dropped'];
            if (! is_array($pack['items'] ?? null) || $pack['items'] === []) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No items left after filtering for prompt type “'.$promptType.'”.',
                    'stats' => ['errors' => ['All items were off-type for '.$promptType], 'lanes_dropped' => $lanesDropped],
                ], 422);
            }
        }

        $apiKey = null;
        if ($target === 'platform') {
            // Platform Train: no merchant key required.
            if (TrainingPrompt::requiresMerchantKey($promptType) && ! empty($validated['prompt_type'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'prompt_type “'.$promptType.'” requires a merchant API key target.',
                ], 422);
            }
        } else {
            if (empty($validated['wise_api_key_id'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'wise_api_key_id required unless target is platform.',
                ], 422);
            }
            $apiKey = WiseApiKey::query()->findOrFail((int) $validated['wise_api_key_id']);
            if ($apiKey->status !== 'active') {
                return response()->json(['ok' => false, 'message' => 'API key is not active.'], 422);
            }
        }

        try {
            $stats = $importer->import(
                $apiKey,
                $pack,
                $request->boolean('import_experience', true),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $stats['lanes_dropped'] = $lanesDropped;
        $stats['prompt_type'] = $promptType;

        $message = sprintf(
            'Knowledge: %d new, %d updated · Language: %d new, %d updated, %d reused · Experience: %d new, %d reused · %d skipped.',
            $stats['knowledge_created'],
            $stats['knowledge_updated'],
            $stats['language_created'],
            $stats['language_updated'],
            $stats['language_reused'],
            $stats['experience_created'],
            $stats['experience_reused'],
            $stats['skipped']
        );

        $applied = (int) ($stats['applied'] ?? 0);
        if ($applied === 0) {
            return response()->json([
                'ok' => false,
                'message' => 'No items imported. '.implode(' ', array_slice($stats['errors'] ?? [], 0, 3)),
                'stats' => $stats,
                'next_steps' => $stats['next_steps'] ?? [],
            ], 422);
        }

        if ($lanesDropped > 0) {
            $message .= sprintf(' · %d off-type lane row(s) stripped.', $lanesDropped);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'stats' => $stats,
            'next_steps' => $stats['next_steps'] ?? [],
            'prompt_type' => $promptType,
            'links' => [
                'knowledge' => route('wiseAi.knowledge'),
                'language' => route('wiseAi.language', ['review' => 'open', 'channel' => 'train']),
            ],
        ]);
    }

    public function generateTrainingPack(
        Request $request,
        TrainingPackGenerator $generator,
        WisePermission $perms,
    ): JsonResponse {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to generate training packs.'], 403);
        }

        $validated = $request->validate([
            'brief' => 'required|string|min:20|max:8000',
            'target_items' => 'nullable|integer|min:8|max:50',
            'prompt_type' => 'nullable|in:merchant,platform,knowledge,language,experience',
        ]);

        $promptType = TrainingPrompt::normalizeType($validated['prompt_type'] ?? null);
        $targetItems = (int) ($validated['target_items'] ?? TrainingPrompt::recommendedTargetItems($promptType));

        try {
            $result = $generator->generate(
                $validated['brief'],
                $targetItems,
                $promptType,
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'pack' => $result['pack'],
            'model' => $result['model'],
            'latency_ms' => $result['latency_ms'],
            'prompt_type' => $result['prompt_type'],
            'lanes_dropped' => $result['lanes_dropped'] ?? 0,
            'message' => 'Draft pack generated — review JSON, then Import as drafts (not published).',
        ]);
    }

    public function playground(WisePermission $perms, LlmLanguageConfig $llm): Response
    {
        return Inertia::render('WiseAi/Playground', [
            'can_edit' => $perms->canEdit(request()->user()),
            'can_publish' => $perms->canPublish(request()->user()),
            'llm_ready' => $llm->enabled() && $llm->hasApiKey(),
        ]);
    }

    public function proposePlaygroundCoach(
        Request $request,
        PlaygroundCoach $coach,
        WisePermission $perms,
    ): JsonResponse {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to run Playground Coach.'], 403);
        }

        $validated = $request->validate([
            'turn_id' => 'required|integer|exists:wise_turns,id',
            'messages' => 'nullable|array|max:24',
            'messages.*.role' => 'nullable|string|max:20',
            'messages.*.text' => 'nullable|string|max:2000',
        ]);

        $turn = WiseTurn::query()->findOrFail((int) $validated['turn_id']);

        try {
            $proposal = $coach->propose($turn, $validated['messages'] ?? []);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'proposal' => $proposal,
            'turn_id' => $turn->id,
        ]);
    }

    public function applyPlaygroundCoach(
        Request $request,
        PlaygroundCoachApplier $applier,
        WisePermission $perms,
    ): JsonResponse {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to apply Playground Coach.'], 403);
        }

        $categories = implode(',', PlaygroundCoach::CATEGORIES);
        $langTypes = implode(',', PlaygroundCoach::LANGUAGE_TYPES);
        $validated = $request->validate([
            'turn_id' => 'required|integer|exists:wise_turns,id',
            'wise_api_key_id' => 'required|integer|exists:wise_api_keys,id',
            'category' => "required|in:{$categories}",
            'publish_now' => 'nullable|boolean',
            'knowledge' => 'nullable|array',
            'knowledge.title' => 'nullable|string|max:191',
            'knowledge.question' => 'nullable|string|max:2000',
            'knowledge.answer' => 'nullable|string|max:5000',
            'knowledge.keywords' => 'nullable|array',
            'knowledge.keywords.*' => 'string|max:60',
            'language' => 'nullable|array',
            'language.type' => "nullable|in:{$langTypes}",
            'language.from' => 'nullable|string|max:80',
            'language.to' => 'nullable|string|max:200',
        ]);

        $publishNow = (bool) ($validated['publish_now'] ?? false);
        if ($publishNow && ! $perms->canPublish($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Publisher role required to publish coach proposals.'], 403);
        }

        $turn = WiseTurn::query()->findOrFail((int) $validated['turn_id']);
        $apiKey = WiseApiKey::query()->findOrFail((int) $validated['wise_api_key_id']);

        try {
            $result = $applier->apply($turn, $apiKey, $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'category' => $result['category'],
            'published' => $result['published'],
            'knowledge_item' => $result['knowledge_item']
                ? $this->knowledgeRow($result['knowledge_item'])
                : null,
            'language_entry' => $result['language_entry']
                ? [
                    'id' => $result['language_entry']->id,
                    'type' => $result['language_entry']->type,
                    'from' => $result['language_entry']->from_text,
                    'to' => $result['language_entry']->to_text,
                    'status' => $result['language_entry']->status,
                ]
                : null,
            'turn_id' => $result['turn']->id,
        ]);
    }

    public function intelligence(Request $request, MerchantIntelligence $bi): Response
    {
        $days = (int) $request->integer('days', 7);
        if (! in_array($days, [7, 14, 30, 90], true)) {
            $days = 7;
        }

        $keyId = $request->filled('key_id') ? (int) $request->integer('key_id') : null;
        if ($keyId !== null && $keyId <= 0) {
            $keyId = null;
        }

        $excludeSandbox = ! $request->boolean('include_sandbox');

        $report = $bi->report($days, $keyId, $excludeSandbox);

        return Inertia::render('WiseAi/Intelligence', [
            'report' => $report,
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'metrics_version' => MetricDefinitions::VERSION,
            'keys' => WiseApiKey::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'meta'])
                ->map(fn (WiseApiKey $key) => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'status' => $key->status,
                    'sandbox' => (bool) (($key->meta['sandbox'] ?? false)
                        || ($key->meta['governance']['sandbox'] ?? false)),
                ]),
            'filters' => [
                'days' => $days,
                'key_id' => $keyId,
                'include_sandbox' => ! $excludeSandbox,
            ],
        ]);
    }

    public function turnExplain(WiseTurn $turn, ExplainBuilder $explain): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'turn_id' => $turn->id,
            'explain' => $explain->build($turn),
        ]);
    }

    /**
     * Sealed-turn Replay — same ExplainBuilder payload (never re-runs live brain/lexicon).
     */
    public function turnReplay(WiseTurn $turn, ExplainBuilder $explain): JsonResponse
    {
        $payload = $explain->build($turn);

        return response()->json([
            'ok' => true,
            'turn_id' => $turn->id,
            'replay_safe' => true,
            'explain' => $payload,
        ]);
    }

    /**
     * Decide request/response log — analyze Messenger / Playground / API turns.
     */
    public function log(Request $request): Response
    {
        $channel = trim($request->string('channel', '')->toString());
        $q = trim($request->string('q', '')->toString());
        $keyId = (int) $request->integer('key_id', 0);
        $gap = $request->string('gap', 'all')->toString();
        if (! in_array($gap, ['all', 'yes', 'no'], true)) {
            $gap = 'all';
        }
        $action = trim($request->string('action', '')->toString());
        $source = trim($request->string('source', '')->toString());
        $status = trim($request->string('status', '')->toString());
        $conversationId = trim($request->string('conversation_id', '')->toString());
        $hours = (int) $request->integer('hours', 24);
        if (! in_array($hours, [0, 1, 6, 24, 72, 168, 720], true)) {
            $hours = 24;
        }
        $perPage = (int) $request->integer('per_page', 40);
        if (! in_array($perPage, [25, 40, 60, 100], true)) {
            $perPage = 40;
        }
        $page = max(1, (int) $request->integer('page', 1));
        $selectedTurnId = (int) $request->integer('turn', 0);

        $query = WiseTurn::query()
            ->with(['apiKey:id,name,key_prefix'])
            ->orderByDesc('id');

        $this->applyLogFilters($query, [
            'channel' => $channel,
            'q' => $q,
            'key_id' => $keyId,
            'gap' => $gap,
            'action' => $action,
            'source' => $source,
            'status' => $status,
            'conversation_id' => $conversationId,
            'hours' => $hours,
        ]);

        $statsBase = WiseTurn::query();
        $this->applyLogFilters($statsBase, [
            'channel' => $channel,
            'q' => $q,
            'key_id' => $keyId,
            'gap' => $gap,
            'action' => $action,
            'source' => $source,
            'status' => $status,
            'conversation_id' => $conversationId,
            'hours' => $hours,
        ]);

        $matched = (clone $statsBase)->count();
        $gaps = (clone $statsBase)->where('gap', true)->count();
        $avgLatency = (clone $statsBase)->avg('latency_ms');
        $avgConfidence = (clone $statsBase)->avg(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(decision, '$.confidence')) AS DECIMAL(10,2))"));
        $p95Latency = $this->logLatencyPercentile(clone $statsBase, 0.95);
        $actionMix = (clone $statsBase)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(decision, '$.action')) as action_name, COUNT(*) as c")
            ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(decision, '$.action'))"))
            ->orderByDesc('c')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'action' => $row->action_name ?: 'unknown',
                'count' => (int) $row->c,
            ])
            ->values()
            ->all();
        $channelMix = (clone $statsBase)
            ->selectRaw('channel, COUNT(*) as c')
            ->groupBy('channel')
            ->orderByDesc('c')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'channel' => $row->channel ?: 'unknown',
                'count' => (int) $row->c,
            ])
            ->values()
            ->all();

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $turns = collect($paginator->items())
            ->map(fn (WiseTurn $turn) => $this->logTurnRow($turn))
            ->values()
            ->all();

        // Facets stay inside the probe window (hours/key/thread) — not a full-table scan.
        $facetBase = WiseTurn::query();
        $this->applyLogFilters($facetBase, [
            'channel' => '',
            'q' => '',
            'key_id' => $keyId,
            'gap' => 'all',
            'action' => '',
            'source' => '',
            'status' => '',
            'conversation_id' => $conversationId,
            'hours' => $hours,
        ]);

        $channels = (clone $facetBase)
            ->select('channel')
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel')
            ->values()
            ->all();

        $sources = (clone $facetBase)
            ->whereNotNull('decision')
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(decision, '$.source')) as source_name")
            ->havingRaw('source_name IS NOT NULL AND source_name <> ?', [''])
            ->orderBy('source_name')
            ->pluck('source_name')
            ->values()
            ->all();

        return Inertia::render('WiseAi/Log', [
            'turns' => $turns,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'stats' => [
                'matched' => $matched,
                'gaps' => $gaps,
                'gap_rate' => $matched > 0 ? round(($gaps / $matched) * 100, 1) : 0.0,
                'avg_latency_ms' => $avgLatency !== null ? (int) round((float) $avgLatency) : null,
                'p95_latency_ms' => $p95Latency,
                'avg_confidence' => $avgConfidence !== null ? (int) round((float) $avgConfidence) : null,
                'action_mix' => $actionMix,
                'channel_mix' => $channelMix,
            ],
            'filters' => [
                'channel' => $channel,
                'q' => $q,
                'key_id' => $keyId > 0 ? $keyId : null,
                'gap' => $gap,
                'action' => $action,
                'source' => $source,
                'status' => $status,
                'conversation_id' => $conversationId,
                'hours' => $hours,
                'per_page' => $perPage,
                'page' => $page,
                'turn' => $selectedTurnId > 0 ? $selectedTurnId : null,
            ],
            'channels' => $channels,
            'sources' => $sources,
            'actions' => ['suggest_reply', 'clarify', 'needs_human'],
            'statuses' => ['ok', 'error'],
            'hour_options' => [
                ['value' => 1, 'label' => '1h'],
                ['value' => 6, 'label' => '6h'],
                ['value' => 24, 'label' => '24h'],
                ['value' => 72, 'label' => '3d'],
                ['value' => 168, 'label' => '7d'],
                ['value' => 720, 'label' => '30d'],
                ['value' => 0, 'label' => 'All'],
            ],
            'api_keys' => WiseApiKey::query()
                ->orderBy('name')
                ->get(['id', 'name', 'key_prefix'])
                ->map(fn (WiseApiKey $k) => [
                    'id' => $k->id,
                    'name' => $k->name,
                    'key_prefix' => $k->key_prefix,
                ])
                ->values()
                ->all(),
            'brain_version' => DecideEngine::BRAIN_VERSION,
        ]);
    }

    /**
     * Full sealed request + response for one turn (Log detail pane).
     */
    public function logTurn(Request $request, WiseTurn $turn): JsonResponse
    {
        $turn->loadMissing(['apiKey:id,name,key_prefix']);
        $requestPayload = is_array($turn->payload) ? $turn->payload : [
            'text' => $turn->text,
            'channel' => $turn->channel,
            'conversation_id' => $turn->conversation_id,
            'context' => [],
        ];
        $decision = is_array($turn->decision) ? $turn->decision : [];
        $evidence = is_array($turn->evidence) ? $turn->evidence : null;
        $trace = is_array($turn->trace) ? $turn->trace : null;
        $context = is_array($requestPayload['context'] ?? null) ? $requestPayload['context'] : [];

        $navFilters = $this->logFiltersFromRequest($request);
        $prevQuery = WiseTurn::query()->where('id', '<', $turn->id)->orderByDesc('id');
        $this->applyLogFilters($prevQuery, $navFilters);
        $prevId = $prevQuery->value('id');

        $nextQuery = WiseTurn::query()->where('id', '>', $turn->id)->orderBy('id');
        $this->applyLogFilters($nextQuery, $navFilters);
        $nextId = $nextQuery->value('id');

        $thread = [];
        if (is_string($turn->conversation_id) && $turn->conversation_id !== '') {
            $thread = WiseTurn::query()
                ->where('conversation_id', $turn->conversation_id)
                ->where('wise_api_key_id', $turn->wise_api_key_id)
                ->orderByDesc('id')
                ->limit(12)
                ->get()
                ->map(fn (WiseTurn $row) => [
                    'id' => $row->id,
                    'created_at' => optional($row->created_at)?->toIso8601String(),
                    'text' => $row->text,
                    'action' => is_array($row->decision) ? ($row->decision['action'] ?? null) : null,
                    'gap' => (bool) $row->gap,
                    'latency_ms' => $row->latency_ms,
                    'is_current' => $row->id === $turn->id,
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'ok' => true,
            'turn' => [
                'id' => $turn->id,
                'created_at' => optional($turn->created_at)?->toIso8601String(),
                'latency_ms' => $turn->latency_ms,
                'status' => $turn->status,
                'gap' => (bool) $turn->gap,
                'channel' => $turn->channel,
                'conversation_id' => $turn->conversation_id,
                'wise_api_key_id' => $turn->wise_api_key_id,
                'key_name' => $turn->apiKey?->name,
                'key_prefix' => $turn->apiKey?->key_prefix,
                'brain_version' => $decision['brain_version'] ?? null,
            ],
            'nav' => [
                'prev_id' => $prevId ? (int) $prevId : null,
                'next_id' => $nextId ? (int) $nextId : null,
                'scoped' => true,
            ],
            'highlights' => [
                'text' => $turn->text,
                'action' => $decision['action'] ?? null,
                'intent' => $decision['intent'] ?? null,
                'confidence' => $decision['confidence'] ?? null,
                'source' => $decision['source'] ?? null,
                'suggested_reply' => $decision['suggested_reply'] ?? null,
                'reason' => $decision['reason'] ?? ($decision['reason_code'] ?? null),
                'knowledge_id' => $evidence['knowledge_id'] ?? null,
                'match_score' => $evidence['match_score'] ?? null,
                'context_keys' => array_keys($context),
                'context_bytes' => strlen((string) json_encode($context)),
            ],
            'trace_steps' => $this->logTraceSteps($trace),
            'thread' => $thread,
            'request' => $requestPayload,
            'response' => [
                'decision' => $decision,
                'evidence' => $evidence,
                'gap' => (bool) $turn->gap,
                'latency_ms' => $turn->latency_ms,
                'status' => $turn->status,
            ],
            'trace' => $trace,
            'config_snapshot' => is_array($turn->config_snapshot) ? $turn->config_snapshot : null,
        ]);
    }

    /**
     * @return array{channel:string,q:string,key_id:int,gap:string,action:string,source:string,status:string,conversation_id:string,hours:int}
     */
    private function logFiltersFromRequest(Request $request): array
    {
        $gap = $request->string('gap', 'all')->toString();
        if (! in_array($gap, ['all', 'yes', 'no'], true)) {
            $gap = 'all';
        }
        $hours = (int) $request->integer('hours', 0);
        if (! in_array($hours, [0, 1, 6, 24, 72, 168, 720], true)) {
            $hours = 0;
        }

        return [
            'channel' => trim($request->string('channel', '')->toString()),
            'q' => trim($request->string('q', '')->toString()),
            'key_id' => (int) $request->integer('key_id', 0),
            'gap' => $gap,
            'action' => trim($request->string('action', '')->toString()),
            'source' => trim($request->string('source', '')->toString()),
            'status' => trim($request->string('status', '')->toString()),
            'conversation_id' => trim($request->string('conversation_id', '')->toString()),
            // Detail nav defaults to no time clip unless the probe passes hours.
            'hours' => $hours,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\WiseAi\WiseTurn>  $query
     * @param  array{channel:string,q:string,key_id:int,gap:string,action:string,source:string,status:string,conversation_id:string,hours:int}  $filters
     */
    private function applyLogFilters($query, array $filters): void
    {
        if ($filters['hours'] > 0) {
            $query->where('created_at', '>=', now()->subHours($filters['hours']));
        }
        if ($filters['channel'] !== '') {
            $query->where('channel', $filters['channel']);
        }
        if ($filters['key_id'] > 0) {
            $query->where('wise_api_key_id', $filters['key_id']);
        }
        if ($filters['gap'] === 'yes') {
            $query->where('gap', true);
        } elseif ($filters['gap'] === 'no') {
            $query->where(function ($inner) {
                $inner->where('gap', false)->orWhereNull('gap');
            });
        }
        if ($filters['action'] !== '') {
            $query->where('decision->action', $filters['action']);
        }
        if ($filters['source'] !== '') {
            $query->where('decision->source', $filters['source']);
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['conversation_id'] !== '') {
            $query->where('conversation_id', $filters['conversation_id']);
        }
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $q = $filters['q'];
            $query->where(function ($inner) use ($like, $q) {
                $inner->where('text', 'like', $like)
                    ->orWhere('conversation_id', 'like', $like)
                    ->orWhere('decision->suggested_reply', 'like', $like)
                    ->orWhere('decision->intent', 'like', $like);
                if (ctype_digit($q)) {
                    $inner->orWhere('id', (int) $q);
                }
            });
        }
    }

    /**
     * True percentile over the filtered set (single ordered row at offset).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\WiseAi\WiseTurn>  $query
     */
    private function logLatencyPercentile($query, float $percentile): ?int
    {
        $count = (clone $query)->whereNotNull('latency_ms')->count();
        if ($count < 1) {
            return null;
        }

        $offset = (int) max(0, min($count - 1, (int) floor(($count - 1) * $percentile)));

        $value = (clone $query)
            ->whereNotNull('latency_ms')
            ->orderBy('latency_ms')
            ->offset($offset)
            ->limit(1)
            ->value('latency_ms');

        return $value !== null ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $trace
     * @return list<array{key:string,summary:string,raw:mixed}>
     */
    private function logTraceSteps(?array $trace): array
    {
        if (! $trace) {
            return [];
        }

        $steps = [];
        foreach ($trace as $key => $value) {
            $summary = '';
            if (is_array($value)) {
                $bits = [];
                foreach (['ok', 'hit', 'gap', 'intent', 'action', 'source', 'score', 'match_score', 'next_action'] as $field) {
                    if (array_key_exists($field, $value)) {
                        $bits[] = $field.'='.(is_scalar($value[$field]) ? (string) $value[$field] : json_encode($value[$field]));
                    }
                }
                $summary = $bits ? implode(' · ', $bits) : (count($value).' keys');
            } elseif (is_scalar($value) || $value === null) {
                $summary = (string) ($value ?? 'null');
            } else {
                $summary = gettype($value);
            }
            $steps[] = [
                'key' => (string) $key,
                'summary' => $summary,
                'raw' => $value,
            ];
        }

        return $steps;
    }

    /**
     * @return array<string, mixed>
     */
    private function logTurnRow(WiseTurn $turn): array
    {
        $decision = is_array($turn->decision) ? $turn->decision : [];
        $payload = is_array($turn->payload) ? $turn->payload : [];
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $evidence = is_array($turn->evidence) ? $turn->evidence : [];

        return [
            'id' => $turn->id,
            'created_at' => optional($turn->created_at)?->toIso8601String(),
            'channel' => $turn->channel,
            'conversation_id' => $turn->conversation_id,
            'text' => $turn->text,
            'action' => $decision['action'] ?? null,
            'intent' => $decision['intent'] ?? null,
            'confidence' => isset($decision['confidence']) ? (int) $decision['confidence'] : null,
            'source' => $decision['source'] ?? null,
            'gap' => (bool) $turn->gap,
            'status' => $turn->status,
            'latency_ms' => $turn->latency_ms,
            'suggested_reply' => $decision['suggested_reply'] ?? null,
            'key_name' => $turn->apiKey?->name,
            'key_prefix' => $turn->apiKey?->key_prefix,
            'brain_version' => $decision['brain_version'] ?? null,
            'has_context' => $context !== [],
            'context_keys' => count($context),
            'knowledge_id' => $evidence['knowledge_id'] ?? null,
        ];
    }

    public function fleet(Request $request, FleetHealth $fleet): Response
    {
        $days = (int) $request->integer('days', 7);
        if (! in_array($days, [7, 14, 30, 90], true)) {
            $days = 7;
        }

        $excludeSandbox = ! $request->boolean('include_sandbox');
        $report = $fleet->report($days, $excludeSandbox);

        return Inertia::render('WiseAi/Fleet', [
            'report' => $report,
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'metrics_version' => MetricDefinitions::VERSION,
            'alerts_version' => FleetAlerts::VERSION,
            'filters' => [
                'days' => $days,
                'include_sandbox' => ! $excludeSandbox,
            ],
        ]);
    }

    public function learning(Request $request, LearningInbox $inbox, WisePermission $perms): Response
    {
        $kind = $request->string('kind', 'all')->toString();
        if (! in_array($kind, LearningInbox::KINDS, true)) {
            $kind = 'all';
        }

        $reasonChoices = [];
        foreach (ReasonCodes::reviewChoices() as $code => $label) {
            $reasonChoices[] = ['value' => $code, 'label' => $label];
        }

        return Inertia::render('WiseAi/Learning', [
            'kind' => $kind,
            'stats' => $inbox->stats(),
            'items' => $inbox->feed($kind, 80),
            'list_limit' => 80,
            'reason_codes' => $reasonChoices,
            'reason_codes_version' => ReasonCodes::VERSION,
            'can_edit' => $perms->canEdit($request->user()),
            'can_publish' => $perms->canPublish($request->user()),
            'api_keys' => WiseApiKey::query()
                ->orderBy('name')
                ->get(['id', 'name', 'key_prefix'])
                ->map(fn (WiseApiKey $k) => [
                    'id' => $k->id,
                    'name' => $k->name,
                    'key_prefix' => $k->key_prefix,
                ])
                ->values()
                ->all(),
            'seeded_drafts' => SeededKnowledge::scopeDraftsForReview(WiseKnowledgeItem::query())
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (WiseKnowledgeItem $item) => $this->knowledgeRow($item))
                ->values()
                ->all(),
        ]);
    }

    public function language(Request $request, PlatformLexicon $lexicon): Response
    {
        $filter = $request->string('review', 'open')->toString();
        if (! in_array($filter, ['open', 'ignored', 'promoted', 'all'], true)) {
            $filter = 'open';
        }

        $channel = $request->string('channel', '')->toString();
        if (! in_array($channel, ['', 'train'], true)) {
            $channel = '';
        }

        $reviewQuery = WiseLanguageReview::with(['apiKey:id,name', 'entry:id,type,from_text,to_text,status'])
            ->orderByDesc('rank_score')
            ->orderByDesc('hit_count')
            ->orderByDesc('last_seen_at');
        if ($filter !== 'all') {
            $reviewQuery->where('status', $filter);
        }
        if ($channel === 'train') {
            $reviewQuery->where('channel', 'train');
        }

        return Inertia::render('WiseAi/Language', [
            'dict_version' => PlatformLexicon::DICT_VERSION,
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'ambiguous' => PlatformLexicon::AMBIGUOUS,
            'channel_filter' => $channel !== '' ? $channel : null,
            'entries' => $lexicon->flatEntries(),
            'bclc_packs' => WiseLanguagePack::query()
                ->orderBy('slug')
                ->get()
                ->sortBy(function (WiseLanguagePack $p) {
                    $rank = ['core' => 1, 'commerce' => 2, 'channel' => 3, 'region' => 4, 'merchant' => 5];

                    return ($rank[$p->kind] ?? 9).'-'.$p->slug;
                })
                ->values()
                ->map(function (WiseLanguagePack $p) {
                    $artifact = WiseLanguageArtifact::query()
                        ->where('pack_id', $p->id)
                        ->where('status', 'published')
                        ->orderByDesc('published_at')
                        ->first();
                    $assignments = WiseLanguagePackAssignment::query()
                        ->where('pack_id', $p->id)
                        ->where('enabled', true)
                        ->get(['target_type', 'target_id', 'priority'])
                        ->map(fn ($a) => [
                            'target_type' => $a->target_type,
                            'target_id' => $a->target_id,
                            'priority' => $a->priority,
                        ])
                        ->values()
                        ->all();

                    return [
                        'slug' => $p->slug,
                        'kind' => $p->kind,
                        'name' => $p->name,
                        'status' => $p->status,
                        'semver' => $p->semver,
                        'locale_scope' => $p->locale_scope,
                        'region' => $p->meta['region'] ?? null,
                        'artifact_hash' => $artifact?->content_hash
                            ? substr((string) $artifact->content_hash, 0, 12)
                            : null,
                        'assignments' => $assignments,
                    ];
                }),
            'region_ui_options' => \App\WiseAi\Language\RegionCode::uiOptions(),
            'region_place_coverage' => \App\WiseAi\Language\RegionCode::placeCoverage(),
            'review_filter' => $filter,
            'review_stats' => [
                'open' => WiseLanguageReview::where('status', 'open')->count(),
                'ignored' => WiseLanguageReview::where('status', 'ignored')->count(),
                'promoted' => WiseLanguageReview::where('status', 'promoted')->count(),
                'all' => WiseLanguageReview::count(),
            ],
            'reviews' => $reviewQuery->limit(100)->get()->map(fn (WiseLanguageReview $r) => [
                'id' => $r->id,
                'token' => $r->token,
                'kind' => $r->kind ?? 'token',
                'channel' => $r->channel,
                'sample_text' => $r->sample_text,
                'hit_count' => $r->hit_count,
                'rank_score' => $r->rank_score,
                'key_breadth' => $r->key_breadth ?? 1,
                'suggested_pack_slug' => $r->suggested_pack_slug,
                'suggested_category' => $r->suggested_category,
                'turn_id' => $r->last_turn_id,
                'status' => $r->status,
                'key_name' => $r->apiKey?->name ?? ($r->wise_api_key_id === null ? 'Platform' : null),
                'wise_api_key_id' => $r->wise_api_key_id,
                'entry_from' => $r->entry?->from_text,
                'entry_to' => $r->entry?->to_text,
                'entry_type' => $r->entry?->type,
                'last_seen_at' => $r->last_seen_at?->toDateTimeString(),
            ]),
            'approved_entries' => WiseLanguageEntry::with('apiKey:id,name')
                ->where('status', 'published')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (WiseLanguageEntry $e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'from' => $e->from_text,
                    'to' => $e->to_text,
                    'key_name' => $e->apiKey?->name ?? 'Platform',
                    'enabled' => $e->enabled,
                ]),
            'can_edit' => app(WisePermission::class)->canEdit($request->user()),
        ]);
    }

    /**
     * Thin Language Lab — pack browser + surfaces + normalize (not Marketplace Lab).
     */
    public function languageLab(Request $request): Response
    {
        $packSlug = $request->string('pack', 'core-bd')->toString();
        $packs = WiseLanguagePack::query()
            ->orderBy('slug')
            ->get()
            ->map(function (WiseLanguagePack $p) {
                $artifact = WiseLanguageArtifact::query()
                    ->where('pack_id', $p->id)
                    ->where('status', 'published')
                    ->orderByDesc('published_at')
                    ->first();

                return [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'kind' => $p->kind,
                    'name' => $p->name,
                    'status' => $p->status,
                    'semver' => $p->semver,
                    'region' => $p->meta['region'] ?? null,
                    'surface_count' => WiseLanguageSurface::query()->where('pack_id', $p->id)->count(),
                    'artifact_hash' => $artifact?->content_hash
                        ? substr((string) $artifact->content_hash, 0, 12)
                        : null,
                ];
            })
            ->values();

        $selected = $packs->firstWhere('slug', $packSlug) ?? $packs->first();
        $surfaces = [];
        if ($selected) {
            $surfaces = WiseLanguageSurface::query()
                ->with('concept:id,category,concept_key')
                ->where('pack_id', $selected['id'])
                ->orderBy('id')
                ->limit(250)
                ->get()
                ->map(fn (WiseLanguageSurface $s) => [
                    'id' => $s->id,
                    'surface' => $s->surface_text,
                    'to' => $s->to_text,
                    'category' => $s->concept?->category,
                    'evidence' => $s->evidence_source,
                    'approval' => $s->approval_status,
                ])
                ->all();
        }

        return Inertia::render('WiseAi/LanguageLab', [
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'packs' => $packs,
            'selected_pack' => $selected['slug'] ?? null,
            'surfaces' => $surfaces,
            'stats' => [
                'packs' => $packs->count(),
                'surfaces' => (int) WiseLanguageSurface::query()->count(),
                'discovery_open' => WiseLanguageReview::where('status', 'open')->count(),
            ],
        ]);
    }

    public function normalizeLanguage(Request $request, LanguageNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:5000',
            'region' => 'nullable|string|max:40',
            'channel' => 'nullable|string|max:40',
        ]);

        return response()->json([
            'ok' => true,
            'language' => $normalizer->normalize(
                $validated['text'],
                null,
                $validated['channel'] ?? null,
                $validated['region'] ?? null,
            ),
        ]);
    }

    public function promoteLanguageReview(
        Request $request,
        WiseLanguageReview $review,
        WisePermission $perms,
        \App\WiseAi\Language\DiscoveryPromoter $promoter,
    ): JsonResponse {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to create language entries.'], 403);
        }

        if ($review->status === 'promoted' && $review->wise_language_entry_id) {
            return response()->json(['ok' => false, 'message' => 'Already promoted.'], 422);
        }

        $validated = $request->validate([
            'type' => 'required|in:abbrev,sms,banglish,phonetic,commerce,filler,messenger',
            'to_text' => 'nullable|string|max:191',
            'scope' => 'nullable|in:merchant,platform',
            'pack_slug' => 'nullable|string|max:80',
            'category' => 'nullable|string|max:40',
        ]);

        try {
            $result = $promoter->promote($review, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $entry = $result['entry'];

        return response()->json([
            'ok' => true,
            'entry' => [
                'id' => $entry->id,
                'type' => $entry->type,
                'from' => $entry->from_text,
                'to' => $entry->to_text,
            ],
            'bclc' => [
                'pack_slug' => $result['pack_slug'],
                'surface_id' => $result['surface_id'],
                'artifact_hash' => $result['artifact_hash']
                    ? substr((string) $result['artifact_hash'], 0, 12)
                    : null,
            ],
            'review' => [
                'id' => $review->fresh()->id,
                'status' => 'promoted',
            ],
            'stats' => [
                'open' => WiseLanguageReview::where('status', 'open')->count(),
                'ignored' => WiseLanguageReview::where('status', 'ignored')->count(),
                'promoted' => WiseLanguageReview::where('status', 'promoted')->count(),
                'all' => WiseLanguageReview::count(),
            ],
        ]);
    }

    public function ignoreLanguageReview(Request $request, WiseLanguageReview $review, WisePermission $perms): JsonResponse
    {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required.'], 403);
        }

        $review->update([
            'status' => 'ignored',
            'handled_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'stats' => [
                'open' => WiseLanguageReview::where('status', 'open')->count(),
                'ignored' => WiseLanguageReview::where('status', 'ignored')->count(),
                'promoted' => WiseLanguageReview::where('status', 'promoted')->count(),
                'all' => WiseLanguageReview::count(),
            ],
        ]);
    }

    public function assist(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Classic Assist page folded into Learning inbox (UX simplification).
        return redirect()->route('wiseAi.learning', ['kind' => 'assist']);
    }

    public function assistFeedback(Request $request, WiseTurn $turn): JsonResponse
    {
        if (! in_array($turn->decision['action'] ?? '', ['suggest_reply', 'clarify'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Only suggested or clarify replies can be reviewed in Assist.',
            ], 422);
        }

        if ($turn->feedbacks()->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'This suggestion was already reviewed.',
            ], 422);
        }

        $validated = $request->validate([
            'outcome' => 'required|in:approved,edited,rejected',
            'reason_code' => 'nullable|string|max:60',
            'edited_reply' => 'nullable|string|max:5000',
        ]);

        if ($validated['outcome'] === 'edited' && trim((string) ($validated['edited_reply'] ?? '')) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'edited_reply is required when outcome is edited.',
            ], 422);
        }

        $reason = trim((string) ($validated['reason_code'] ?? ''));
        if ($validated['outcome'] === 'rejected') {
            if ($reason === '' || ! ReasonCodes::isValid($reason)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'reason_code required when rejecting (Learning taxonomy).',
                    'reason_codes' => ReasonCodes::reviewChoices(),
                ], 422);
            }
        } elseif ($reason !== '' && ! ReasonCodes::isValid($reason)) {
            return response()->json(['ok' => false, 'message' => 'Invalid reason_code.'], 422);
        } elseif ($reason === '') {
            $reason = match ($validated['outcome']) {
                'approved' => 'assist_approve',
                default => 'assist_edit',
            };
        }

        $feedback = WiseFeedback::create([
            'wise_api_key_id' => $turn->wise_api_key_id,
            'wise_turn_id' => $turn->id,
            'outcome' => $validated['outcome'],
            'reason_code' => $reason,
            'edited_reply' => $validated['edited_reply'] ?? null,
            'meta' => [
                'via' => 'assist_admin',
                'reason_codes_version' => ReasonCodes::VERSION,
            ],
        ]);

        app(ExperienceRecorder::class)->fromFeedback($feedback, $turn);

        return response()->json([
            'ok' => true,
            'feedback_id' => $feedback->id,
            'turn' => $this->turnRow($turn->fresh(['apiKey:id,name', 'latestFeedback'])),
            'stats' => $this->assistStats(),
            'learning_stats' => app(LearningInbox::class)->stats(),
        ]);
    }

    public function gaps(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Classic Gaps page folded into Learning inbox (UX simplification).
        return redirect()->route('wiseAi.learning', ['kind' => 'gap']);
    }

    public function draftFromGap(Request $request, WiseTurn $turn, WisePermission $perms): JsonResponse
    {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to draft from gap.'], 403);
        }

        if (! $turn->gap) {
            return response()->json(['ok' => false, 'message' => 'Turn is not a knowledge gap.'], 422);
        }

        if ($turn->gap_handled_at !== null || $turn->gap_knowledge_id !== null) {
            return response()->json([
                'ok' => false,
                'message' => 'This gap was already handled. Open Knowledge or Handled tab.',
            ], 422);
        }

        $kinds = implode(',', KnowledgeSchema::kinds());
        $scopes = implode(',', KnowledgeSchema::scopes());
        $validated = $request->validate([
            'type' => "required|in:{$kinds}",
            'scope' => "nullable|in:{$scopes}",
            'title' => 'required|string|max:191',
            'question' => 'nullable|string|max:2000',
            'answer' => 'required|string|max:5000',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:60',
            'external_id' => 'nullable|string|max:191',
            'platform' => 'nullable|string|max:40',
            'offer_kind' => 'nullable|in:physical,digital,service,subscription,other',
            'sku' => 'nullable|string|max:64',
            'region' => 'nullable|string|max:60',
            'publish_now' => 'nullable|boolean',
        ]);
        $validated['scope'] = $validated['scope'] ?? KnowledgeSchema::SCOPE_PLATFORM;
        $publishNow = (bool) ($validated['publish_now'] ?? false);
        unset($validated['publish_now']);

        if ($publishNow && ! $perms->canPublish($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Publisher role required to publish now.'], 403);
        }

        if ($validated['type'] === KnowledgeSchema::KIND_OTHER) {
            $validated['type'] = KnowledgeSchema::KIND_FACT;
        }
        if (
            ($validated['type'] === KnowledgeSchema::KIND_OFFER || $validated['scope'] === KnowledgeSchema::SCOPE_OFFER)
            && trim((string) ($validated['external_id'] ?? '')) === ''
        ) {
            return response()->json(['ok' => false, 'message' => 'external_id required for offer kind or offer scope.'], 422);
        }
        if ($validated['scope'] === KnowledgeSchema::SCOPE_REGION && trim((string) ($validated['region'] ?? '')) === '') {
            return response()->json(['ok' => false, 'message' => 'region required for region scope.'], 422);
        }

        $feeErrors = app(KnowledgeSeedValidator::class)->answerFactGuards(
            (string) $validated['answer'],
            'gap draft',
        );
        if ($feeErrors !== []) {
            return response()->json([
                'ok' => false,
                'message' => 'Invented fee/phone/percent blocked — remove store-specific amounts or use refuse phrasing.',
                'errors' => $feeErrors,
            ], 422);
        }

        $publisher = app(KnowledgePublisher::class);

        [$item, $freshTurn] = DB::transaction(function () use ($turn, $validated, $publishNow, $publisher) {
            $locked = WiseTurn::query()->whereKey($turn->id)->lockForUpdate()->firstOrFail();

            if ($locked->gap_handled_at !== null || $locked->gap_knowledge_id !== null) {
                throw new HttpResponseException(response()->json([
                    'ok' => false,
                    'message' => 'This gap was already handled. Open Knowledge or Handled tab.',
                ], 422));
            }

            $meta = [];
            if (! empty($validated['sku'])) {
                $meta['sku'] = $validated['sku'];
            }
            if (! empty($validated['platform'])) {
                $meta['platform'] = $validated['platform'];
            }
            if (! empty($validated['offer_kind'])) {
                $meta['offer_kind'] = $validated['offer_kind'];
            }
            if (! empty($validated['region'])) {
                $meta['region'] = trim((string) $validated['region']);
            }

            $needsExternal = $validated['type'] === KnowledgeSchema::KIND_OFFER
                || $validated['scope'] === KnowledgeSchema::SCOPE_OFFER;

            $existingAuto = $locked->gap_auto_draft_id
                ? WiseKnowledgeItem::query()->whereKey($locked->gap_auto_draft_id)->first()
                : null;

            // Gap auto-drafts stay merchant-owned for this API key (no silent platform promotion).
            $scope = (string) $validated['scope'];
            if (
                $existingAuto
                && (($existingAuto->meta['source'] ?? null) === GapAutoDrafter::META_SOURCE
                    || ($existingAuto->meta['auto_draft'] ?? false) === true)
            ) {
                $scope = KnowledgeSchema::SCOPE_MERCHANT;
            }

            $payload = [
                'wise_api_key_id' => $scope === KnowledgeSchema::SCOPE_PLATFORM
                    ? null
                    : $locked->wise_api_key_id,
                'external_id' => $needsExternal
                    ? trim((string) ($validated['external_id'] ?? ''))
                    : ($validated['external_id'] ?? null),
                'type' => $validated['type'],
                'scope' => $scope,
                'title' => $validated['title'],
                'question' => $validated['question'] ?? $locked->text,
                'answer' => $validated['answer'],
                'keywords' => $validated['keywords'] ?? [],
                'meta' => array_filter(array_merge(
                    is_array($existingAuto?->meta) ? $existingAuto->meta : [],
                    $meta,
                    [
                        'source' => $existingAuto
                            ? GapAutoDrafter::META_SOURCE
                            : 'gap_human_draft',
                        'wise_turn_id' => (int) $locked->id,
                        'human_reviewed' => true,
                    ],
                ), fn ($v) => $v !== null && $v !== ''),
                'status' => 'draft',
            ];

            if ($existingAuto) {
                $existingAuto->fill($payload);
                if ((int) $existingAuto->version < 1) {
                    $existingAuto->version = 1;
                }
                $existingAuto->save();
                $item = $existingAuto;
            } else {
                $item = WiseKnowledgeItem::create(array_merge($payload, ['version' => 1]));
            }

            if ($publishNow) {
                $item = $publisher->publish($item);
            }

            $locked->update([
                'gap_handled_at' => now(),
                'gap_knowledge_id' => $item->id,
                'gap_auto_draft_id' => $item->id,
            ]);

            $item->load('apiKey:id,name');

            return [
                $item,
                $locked->fresh(['apiKey:id,name', 'gapKnowledge:id,title,status', 'gapAutoDraft:id,title,status']),
            ];
        });

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
            'turn' => $this->turnRow($freshTurn),
            'stats' => $this->gapStats(),
            'published' => $item->status === 'published',
        ]);
    }

    public function relatedQuestions(WiseTurn $turn, RelatedQuestionSuggester $suggester): JsonResponse
    {
        $result = $suggester->forTurn($turn->loadMissing('apiKey'));

        return response()->json([
            'ok' => true,
            'turn_id' => $turn->id,
            'version' => $result['version'],
            'items' => $result['items'],
        ]);
    }

    public function ignoreGap(Request $request, WiseTurn $turn, WisePermission $perms): JsonResponse
    {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to ignore gaps.'], 403);
        }

        if (! $turn->gap) {
            return response()->json(['ok' => false, 'message' => 'Turn is not a knowledge gap.'], 422);
        }

        if ($turn->gap_handled_at !== null) {
            return response()->json([
                'ok' => true,
                'turn' => $this->turnRow($turn->load(['apiKey:id,name', 'gapKnowledge:id,title,status'])),
                'stats' => $this->gapStats(),
            ]);
        }

        $turn->update([
            'gap_handled_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'turn' => $this->turnRow($turn->fresh(['apiKey:id,name', 'gapKnowledge:id,title,status'])),
            'stats' => $this->gapStats(),
        ]);
    }

    /** @return array{open: int, handled: int, all: int} */
    private function gapStats(): array
    {
        return [
            'open' => WiseTurn::where('gap', true)->whereNull('gap_handled_at')->count(),
            'handled' => WiseTurn::where('gap', true)->whereNotNull('gap_handled_at')->count(),
            'all' => WiseTurn::where('gap', true)->count(),
        ];
    }

    /** @return array{pending: int, reviewed: int, all: int} */
    private function assistStats(): array
    {
        $suggest = fn () => WiseTurn::query()->whereIn('decision->action', ['suggest_reply', 'clarify']);

        return [
            'pending' => $suggest()->whereDoesntHave('feedbacks')->count(),
            'reviewed' => $suggest()->whereHas('feedbacks')->count(),
            'all' => $suggest()->count(),
        ];
    }

    public function config(MerchantPolicy $merchantPolicy, LlmLanguageConfig $llm): Response
    {
        return Inertia::render('WiseAi/Config', [
            'apiKeys' => WiseApiKey::latest()
                ->get()
                ->map(fn (WiseApiKey $key) => $this->keyRow($key, $merchantPolicy)),
            'governance' => [
                'constitution_version' => Constitution::VERSION,
                'policy_pack_version' => PolicyPack::VERSION,
                'principles' => Constitution::principles(),
                'default_mode' => PolicyPack::DEFAULT_MODE,
                'allowed_modes' => PolicyPack::ALLOWED_MODES,
            ],
            'llm' => $llm->forAdmin(),
        ]);
    }

    public function updateLlmConfig(Request $request, LlmLanguageConfig $llm): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'model' => 'nullable|string|max:40',
            'api_key' => 'nullable|string|max:500',
        ]);

        $llm->update($validated);

        return response()->json([
            'ok' => true,
            'llm' => $llm->forAdmin(),
        ]);
    }

    public function knowledge(Request $request, WisePermission $perms): Response
    {
        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'seeded_drafts', 'draft', 'published'], true)) {
            $filter = 'all';
        }

        $query = WiseKnowledgeItem::with('apiKey:id,name')->latest();
        if ($filter === 'seeded_drafts') {
            SeededKnowledge::scopeDraftsForReview($query);
        } elseif ($filter === 'draft') {
            $query->where('status', 'draft');
        } elseif ($filter === 'published') {
            $query->where('status', 'published');
        }

        $seededDraftCount = SeededKnowledge::scopeDraftsForReview(WiseKnowledgeItem::query())->count();

        return Inertia::render('WiseAi/Knowledge', [
            'apiKeys' => WiseApiKey::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'key_prefix']),
            'items' => $query
                ->limit(100)
                ->get()
                ->map(fn (WiseKnowledgeItem $item) => $this->knowledgeRow($item)),
            'filter' => $filter,
            'seeded_draft_count' => $seededDraftCount,
            'can_edit' => $perms->canEdit($request->user()),
            'can_publish' => $perms->canPublish($request->user()),
        ]);
    }

    public function storeKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $result = WiseApiKey::generate($validated['name']);

        return response()->json([
            'ok' => true,
            'plain_key' => $result['plain'],
            'key' => $this->keyRow($result['key']),
        ]);
    }

    public function revokeKey(WiseApiKey $key): JsonResponse
    {
        $key->update(['status' => 'revoked']);

        return response()->json(['ok' => true]);
    }

    public function updateKeyGovernance(Request $request, WiseApiKey $key, MerchantPolicy $merchantPolicy): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'required|in:shadow,assist,auto',
            'allow_auto' => 'nullable|boolean',
            'sandbox' => 'nullable|boolean',
            'feature_flags' => 'nullable|array',
        ]);

        $meta = $key->meta ?? [];
        $previous = is_array($meta['governance'] ?? null) ? $meta['governance'] : [];
        $gov = $merchantPolicy->normalizeUpdate($validated, $previous);

        $audit = is_array($meta['governance_audit'] ?? null) ? $meta['governance_audit'] : [];
        $audit[] = [
            'at' => now()->toIso8601String(),
            'by' => $request->user()?->id,
            'from' => $previous,
            'to' => $gov,
        ];
        $meta['governance_audit'] = array_slice($audit, -20);
        $meta['governance'] = $gov;
        if ($gov['sandbox']) {
            $meta['sandbox'] = true;
        } else {
            unset($meta['sandbox']);
        }

        $key->update(['meta' => $meta]);

        return response()->json([
            'ok' => true,
            'key' => $this->keyRow($key->fresh(), $merchantPolicy),
        ]);
    }

    public function storeKnowledge(Request $request, WisePermission $perms): JsonResponse
    {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to create knowledge.'], 403);
        }

        $kinds = implode(',', KnowledgeSchema::kinds());
        $scopes = implode(',', KnowledgeSchema::scopes());
        $validated = $request->validate([
            'wise_api_key_id' => 'nullable|integer|exists:wise_api_keys,id',
            'type' => "required|in:{$kinds}",
            'scope' => "nullable|in:{$scopes}",
            'title' => 'required|string|max:191',
            'question' => 'nullable|string|max:2000',
            'answer' => 'required|string|max:5000',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:60',
            'external_id' => 'nullable|string|max:191',
            'platform' => 'nullable|string|max:40',
            'offer_kind' => 'nullable|in:physical,digital,service,subscription,other',
            'sku' => 'nullable|string|max:64',
            'region' => 'nullable|string|max:60',
            'pricing_menu' => 'nullable|boolean',
        ]);

        $validated['scope'] = $validated['scope'] ?? KnowledgeSchema::SCOPE_MERCHANT;
        if ($validated['type'] === KnowledgeSchema::KIND_OTHER) {
            $validated['type'] = KnowledgeSchema::KIND_FACT;
        }
        if ($validated['scope'] !== KnowledgeSchema::SCOPE_PLATFORM && empty($validated['wise_api_key_id'])) {
            return response()->json(['ok' => false, 'message' => 'wise_api_key_id required unless scope is platform.'], 422);
        }
        if (
            ($validated['type'] === KnowledgeSchema::KIND_OFFER || $validated['scope'] === KnowledgeSchema::SCOPE_OFFER)
            && trim((string) ($validated['external_id'] ?? '')) === ''
        ) {
            return response()->json(['ok' => false, 'message' => 'external_id required for offer kind or offer scope.'], 422);
        }
        if ($validated['scope'] === KnowledgeSchema::SCOPE_REGION && trim((string) ($validated['region'] ?? '')) === '') {
            return response()->json(['ok' => false, 'message' => 'region required for region scope.'], 422);
        }

        $meta = [];
        if (! empty($validated['sku'])) {
            $meta['sku'] = $validated['sku'];
        }
        if (! empty($validated['platform'])) {
            $meta['platform'] = $validated['platform'];
        }
        if (! empty($validated['offer_kind'])) {
            $meta['offer_kind'] = $validated['offer_kind'];
        }
        if (! empty($validated['region'])) {
            $meta['region'] = trim((string) $validated['region']);
        }
        $pricingKinds = [
            KnowledgeSchema::KIND_FAQ,
            KnowledgeSchema::KIND_POLICY,
            KnowledgeSchema::KIND_FACT,
            KnowledgeSchema::KIND_OTHER,
            KnowledgeSchema::KIND_SCRIPT,
        ];
        if (! empty($validated['pricing_menu']) && in_array($validated['type'], $pricingKinds, true)) {
            $meta['pricing_menu'] = true;
        }
        unset($validated['pricing_menu'], $validated['region']);

        $needsExternal = $validated['type'] === KnowledgeSchema::KIND_OFFER
            || $validated['scope'] === KnowledgeSchema::SCOPE_OFFER;

        // Always draft — only publishKnowledge() may set published (human approval).
        $item = WiseKnowledgeItem::create([
            'wise_api_key_id' => $validated['scope'] === KnowledgeSchema::SCOPE_PLATFORM
                ? null
                : $validated['wise_api_key_id'],
            'external_id' => $needsExternal
                ? trim((string) ($validated['external_id'] ?? ''))
                : ($validated['external_id'] ?? null),
            'type' => $validated['type'],
            'scope' => $validated['scope'],
            'title' => $validated['title'],
            'question' => $validated['question'] ?? null,
            'answer' => $validated['answer'],
            'keywords' => $validated['keywords'] ?? [],
            'meta' => $meta ?: null,
            'status' => 'draft',
            'version' => 1,
        ]);

        $item->load('apiKey:id,name');

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    public function updateKnowledge(Request $request, WiseKnowledgeItem $item, WisePermission $perms): JsonResponse
    {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to update knowledge.'], 403);
        }

        $kinds = implode(',', KnowledgeSchema::kinds());
        $scopes = implode(',', KnowledgeSchema::scopes());
        $validated = $request->validate([
            'type' => "sometimes|in:{$kinds}",
            'scope' => "sometimes|in:{$scopes}",
            'title' => 'sometimes|string|max:191',
            'question' => 'nullable|string|max:2000',
            'answer' => 'sometimes|string|max:5000',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:60',
            'external_id' => 'nullable|string|max:191',
            'platform' => 'nullable|string|max:40',
            'offer_kind' => 'nullable|in:physical,digital,service,subscription,other',
            'sku' => 'nullable|string|max:64',
            'region' => 'nullable|string|max:60',
            'pricing_menu' => 'nullable|boolean',
        ]);

        if (($validated['type'] ?? null) === KnowledgeSchema::KIND_OTHER) {
            $validated['type'] = KnowledgeSchema::KIND_FACT;
        }

        $identityTouched = array_key_exists('external_id', $validated)
            || array_key_exists('sku', $validated)
            || array_key_exists('platform', $validated)
            || array_key_exists('offer_kind', $validated)
            || array_key_exists('pricing_menu', $validated)
            || array_key_exists('region', $validated)
            || array_key_exists('scope', $validated)
            || array_key_exists('type', $validated);

        if (
            array_key_exists('sku', $validated)
            || array_key_exists('platform', $validated)
            || array_key_exists('offer_kind', $validated)
            || array_key_exists('pricing_menu', $validated)
            || array_key_exists('region', $validated)
        ) {
            $meta = $item->meta ?? [];
            foreach (['sku', 'platform', 'offer_kind', 'region'] as $metaKey) {
                if (! array_key_exists($metaKey, $validated)) {
                    continue;
                }
                if ($validated[$metaKey]) {
                    $meta[$metaKey] = $validated[$metaKey];
                } else {
                    unset($meta[$metaKey]);
                }
                unset($validated[$metaKey]);
            }
            if (array_key_exists('pricing_menu', $validated)) {
                if ($validated['pricing_menu']) {
                    $meta['pricing_menu'] = true;
                } else {
                    unset($meta['pricing_menu']);
                }
                unset($validated['pricing_menu']);
            }
            $validated['meta'] = $meta ?: null;
        }

        $nextScope = $validated['scope'] ?? $item->scope;
        if ($nextScope === KnowledgeSchema::SCOPE_PLATFORM) {
            $validated['wise_api_key_id'] = null;
        }

        if (
            isset($validated['answer'])
            || isset($validated['title'])
            || isset($validated['question'])
            || $identityTouched
        ) {
            $validated['version'] = $item->version + 1;
            // Content or catalog identity change unpublishes until human re-approves.
            $validated['status'] = 'draft';
        }

        $item->update($validated);
        $item->load('apiKey:id,name');

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    public function publishKnowledge(
        Request $request,
        WiseKnowledgeItem $item,
        WisePermission $perms,
        KnowledgePublisher $publisher,
    ): JsonResponse {
        if (! $perms->canPublish($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Publisher role required to publish knowledge.'], 403);
        }

        $item = $publisher->publish($item);

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    public function unpublishKnowledge(
        Request $request,
        WiseKnowledgeItem $item,
        WisePermission $perms,
        KnowledgePublisher $publisher,
    ): JsonResponse {
        if (! $perms->canPublish($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Publisher role required to unpublish knowledge.'], 403);
        }

        $item = $publisher->unpublish($item);

        return response()->json([
            'ok' => true,
            'item' => $this->knowledgeRow($item),
        ]);
    }

    public function bulkPublishKnowledge(
        Request $request,
        WisePermission $perms,
        KnowledgePublisher $publisher,
    ): JsonResponse {
        if (! $perms->canPublish($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Publisher role required to publish knowledge.'], 403);
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer|min:1',
        ]);

        try {
            $result = $publisher->bulkPublishSeededDrafts($validated['ids']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'published_count' => $result['published_count'],
            'skipped_count' => $result['skipped_count'],
            'skipped' => $result['skipped'],
            'items' => array_map(fn (WiseKnowledgeItem $item) => $this->knowledgeRow($item), $result['published']),
            'seeded_draft_count' => SeededKnowledge::scopeDraftsForReview(WiseKnowledgeItem::query())->count(),
        ]);
    }

    public function regenerateKnowledgeAnswer(
        Request $request,
        WiseKnowledgeItem $item,
        WisePermission $perms,
        KnowledgeAnswerRegenerator $regenerator,
    ): JsonResponse {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required to regenerate answers.'], 403);
        }

        try {
            $proposal = $regenerator->propose($item);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'item_id' => $item->id,
            ...$proposal,
            'message' => 'Proposed answer ready — review, Apply (stays draft), then Publish.',
        ]);
    }

    /**
     * Propose a safer Bangla rewrite without persisting (Learning gap dialog).
     */
    public function proposeKnowledgeAnswer(
        Request $request,
        WisePermission $perms,
        KnowledgeAnswerRegenerator $regenerator,
    ): JsonResponse {
        if (! $perms->canEdit($request->user())) {
            return response()->json(['ok' => false, 'message' => 'Editor role required.'], 403);
        }

        $scopes = implode(',', KnowledgeSchema::scopes());
        $validated = $request->validate([
            'title' => 'required|string|max:191',
            'question' => 'nullable|string|max:2000',
            'answer' => 'required|string|max:5000',
            'scope' => "nullable|in:{$scopes}",
        ]);

        $ephemeral = new WiseKnowledgeItem([
            'title' => $validated['title'],
            'question' => $validated['question'] ?? null,
            'answer' => $validated['answer'],
            'scope' => $validated['scope'] ?? KnowledgeSchema::SCOPE_PLATFORM,
            'type' => KnowledgeSchema::KIND_FAQ,
            'status' => 'draft',
        ]);

        try {
            $proposal = $regenerator->propose($ephemeral);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            ...$proposal,
            'message' => 'Proposed answer ready — review before Save / Publish.',
        ]);
    }

    private function turnRow(WiseTurn $turn): array
    {
        $feedback = $turn->relationLoaded('latestFeedback')
            ? $turn->latestFeedback
            : null;

        $psych = is_array($turn->decision['psych'] ?? null) ? $turn->decision['psych'] : null;
        $ops = is_array($turn->decision['opportunities'] ?? null) ? $turn->decision['opportunities'] : null;

        return [
            'id' => $turn->id,
            'wise_api_key_id' => $turn->wise_api_key_id,
            'key_name' => $turn->apiKey?->name,
            'channel' => $turn->channel,
            'text' => $turn->text,
            'intent' => $turn->decision['intent'] ?? null,
            'confidence' => $turn->decision['confidence'] ?? null,
            'action' => $turn->decision['action'] ?? null,
            'source' => $turn->decision['source'] ?? null,
            'suggested_reply' => $turn->decision['suggested_reply'] ?? null,
            'psych' => $psych,
            'opportunities' => $ops,
            'gap' => (bool) $turn->gap,
            'gap_handled' => $turn->gap_handled_at !== null,
            'gap_handled_at' => $turn->gap_handled_at?->toDateTimeString(),
            'gap_knowledge_id' => $turn->gap_knowledge_id,
            'gap_knowledge_title' => $turn->gapKnowledge?->title,
            'gap_knowledge_status' => $turn->gapKnowledge?->status,
            'gap_auto_draft_id' => $turn->gap_auto_draft_id,
            'gap_auto_draft_status' => $turn->gapAutoDraft?->status,
            'reviewed' => $feedback !== null,
            'feedback_outcome' => $feedback?->outcome,
            'feedback_edited_reply' => $feedback?->edited_reply,
            'latency_ms' => $turn->latency_ms,
            'created_at' => $turn->created_at?->toDateTimeString(),
            'llm_applied' => (bool) (($turn->decision['language_llm']['applied'] ?? false)
                || ($turn->decision['language_llm_applied'] ?? false)),
            'experience_net' => $turn->decision['experience']['net_weight'] ?? null,
        ];
    }

    private function keyRow(WiseApiKey $key, ?MerchantPolicy $merchantPolicy = null): array
    {
        $merchantPolicy ??= app(MerchantPolicy::class);
        $gov = $merchantPolicy->resolve($key);

        return [
            'id' => $key->id,
            'name' => $key->name,
            'key_prefix' => $key->key_prefix,
            'status' => $key->status,
            'turns_count' => $key->turns_count,
            'last_used_at' => $key->last_used_at?->toDateTimeString(),
            'created_at' => $key->created_at?->toDateTimeString(),
            'mode' => $gov['mode'],
            'allow_auto' => $gov['allow_auto'],
            'sandbox' => $gov['sandbox'],
            'policy_version' => $gov['policy_version'],
            'feature_flags' => $gov['feature_flags'],
        ];
    }

    private function knowledgeRow(WiseKnowledgeItem $item): array
    {
        $seededFrom = is_string($item->meta['seeded_from'] ?? null) ? (string) $item->meta['seeded_from'] : null;

        return [
            'id' => $item->id,
            'wise_api_key_id' => $item->wise_api_key_id,
            'key_name' => $item->apiKey?->name,
            'type' => $item->type,
            'kind' => $item->type,
            'scope' => $item->scope ?: KnowledgeSchema::SCOPE_MERCHANT,
            'title' => $item->title,
            'question' => $item->question,
            'answer' => $item->answer,
            'keywords' => $item->keywords ?? [],
            'external_id' => $item->external_id,
            'platform' => $item->meta['platform'] ?? null,
            'offer_kind' => $item->meta['offer_kind'] ?? null,
            'sku' => $item->meta['sku'] ?? null,
            'region' => $item->meta['region'] ?? null,
            'pricing_menu' => (bool) ($item->meta['pricing_menu'] ?? false),
            'seeded_from' => $seededFrom,
            'is_seeded' => SeededKnowledge::isSeeded($item),
            'bulk_eligible' => SeededKnowledge::isBulkPublishEligible($item),
            'status' => $item->status,
            'version' => $item->version,
            'updated_at' => $item->updated_at?->toDateTimeString(),
        ];
    }
}
