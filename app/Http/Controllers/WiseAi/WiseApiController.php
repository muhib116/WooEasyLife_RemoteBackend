<?php

namespace App\Http\Controllers\WiseAi;

use App\Http\Controllers\Controller;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Commerce\CommerceEventIngestor;
use App\WiseAi\Commerce\CommerceEventTypes;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\DecideEngine;
use App\WiseAi\Experience\ExperienceRecorder;
use App\WiseAi\Explain\ExplainBuilder;
use App\WiseAi\Knowledge\CatalogKnowledgeUpsertor;
use App\WiseAi\TurnRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Wise AI public API v1.
 *
 * Auth: `Authorization: Bearer <api key>` (or `X-Wise-Key` header).
 */
class WiseApiController extends Controller
{
    public function __construct(
        private TurnRunner $runner,
        private ExplainBuilder $explainer,
        private CommerceEventIngestor $commerceEvents,
        private CatalogKnowledgeUpsertor $catalogUpsertor,
        private ExperienceRecorder $experienceRecorder,
    ) {}

    /**
     * Adapter connectivity check — does not create a wise_turn.
     */
    public function ping(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
                'message' => 'Provide a valid Wise AI API key as a Bearer token.',
            ], 401);
        }

        return response()->json([
            'ok' => true,
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'catalog_schema_version' => CatalogKnowledgeUpsertor::SCHEMA_VERSION,
            'key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'prefix' => $apiKey->key_prefix,
                'sandbox' => (bool) ($apiKey->meta['sandbox'] ?? false),
            ],
        ]);
    }

    public function decide(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
                'message' => 'Provide a valid Wise AI API key as a Bearer token.',
            ], 401);
        }

        $validated = $request->validate([
            'text' => 'required|string|max:5000',
            'channel' => 'nullable|string|max:40',
            'conversation_id' => 'nullable|string|max:191',
            'context' => 'nullable|array',
        ]);

        $result = $this->runner->run($apiKey, IncomingTurn::fromPayload($validated));

        return response()->json([
            'ok' => true,
            'turn_id' => $result['turn']->id,
            'decision' => $result['decision'],
            'latency_ms' => $result['latency_ms'],
            'evidence' => $result['turn']->evidence,
            'gap' => (bool) $result['turn']->gap,
        ]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
                'message' => 'Provide a valid Wise AI API key as a Bearer token.',
            ], 401);
        }

        $validated = $request->validate([
            'turn_id' => 'required|integer',
            'outcome' => 'required|in:approved,edited,rejected',
            'reason_code' => 'nullable|string|max:60',
            'edited_reply' => 'nullable|string|max:5000',
        ]);

        $turn = WiseTurn::query()
            ->where('id', $validated['turn_id'])
            ->where('wise_api_key_id', $apiKey->id)
            ->first();

        if (! $turn) {
            return response()->json([
                'ok' => false,
                'error' => 'turn_not_found',
                'message' => 'Turn not found for this API key.',
            ], 404);
        }

        $reason = trim((string) ($validated['reason_code'] ?? ''));
        if ($validated['outcome'] === 'rejected') {
            if ($reason === '') {
                $reason = 'playground_reject';
            }
            if (! \App\WiseAi\Learning\ReasonCodes::isValid($reason)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'invalid_reason_code',
                    'message' => 'Unknown reason_code for reject.',
                    'reason_codes' => \App\WiseAi\Learning\ReasonCodes::reviewChoices(),
                ], 422);
            }
        } elseif ($reason !== '' && ! \App\WiseAi\Learning\ReasonCodes::isValid($reason)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_reason_code',
                'message' => 'Unknown reason_code.',
            ], 422);
        }

        $feedback = WiseFeedback::create([
            'wise_api_key_id' => $apiKey->id,
            'wise_turn_id' => $turn->id,
            'outcome' => $validated['outcome'],
            'reason_code' => $reason !== '' ? $reason : null,
            'edited_reply' => $validated['edited_reply'] ?? null,
            'meta' => [
                'via' => 'api',
                'reason_codes_version' => \App\WiseAi\Learning\ReasonCodes::VERSION,
            ],
        ]);

        $this->experienceRecorder->fromFeedback($feedback, $turn);

        return response()->json([
            'ok' => true,
            'feedback_id' => $feedback->id,
            'turn_id' => $turn->id,
            'outcome' => $feedback->outcome,
        ]);
    }

    /**
     * External / adapter experience intake — what worked, never Knowledge.
     */
    public function experience(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
                'message' => 'Provide a valid Wise AI API key as a Bearer token.',
            ], 401);
        }

        $validated = $request->validate([
            'signal_type' => 'required|string|in:'.implode(',', ExperienceRecorder::SIGNAL_TYPES),
            'intent' => 'nullable|string|max:60',
            'action' => 'nullable|string|max:40',
            'source' => 'nullable|string|max:40',
            'pattern_key' => 'nullable|string|max:120',
            'weight' => 'nullable|numeric|min:-5|max:5',
            'turn_id' => 'nullable|integer',
            'idempotency_key' => 'nullable|string|max:191',
            'context' => 'nullable|array',
        ]);

        if (! empty($validated['turn_id'])) {
            $owns = WiseTurn::query()
                ->where('id', (int) $validated['turn_id'])
                ->where('wise_api_key_id', $apiKey->id)
                ->exists();
            if (! $owns) {
                return response()->json([
                    'ok' => false,
                    'error' => 'turn_not_found',
                    'message' => 'turn_id not found for this API key.',
                ], 404);
            }
        }

        try {
            $signal = $this->experienceRecorder->fromExternal($apiKey, $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_experience',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'signal_id' => $signal->id,
            'signal_type' => $signal->signal_type,
            'weight' => $signal->weight,
            'experience_version' => ExperienceRecorder::VERSION,
        ], 201);
    }

    /**
     * Commerce event ingest (Wave C4) — adapters map store webhooks here.
     * Attribution uses conversation_id ↔ prior turns; never invents GMV.
     */
    public function commerceEvent(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
                'message' => 'Provide a valid Wise AI API key as a Bearer token.',
            ], 401);
        }

        $validated = $request->validate([
            'event_type' => 'required|string|in:'.implode(',', CommerceEventTypes::all()),
            'idempotency_key' => 'required|string|max:191',
            'conversation_id' => 'nullable|string|max:191',
            'turn_id' => 'nullable|integer',
            'external_order_id' => 'nullable|string|max:191',
            'platform' => 'nullable|string|max:40',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:8',
            'occurred_at' => 'nullable|date',
            'context' => 'nullable|array',
            'meta' => 'nullable|array',
        ]);

        try {
            $result = $this->commerceEvents->ingest($apiKey, $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_commerce_event',
                'message' => $e->getMessage(),
            ], 422);
        }

        $event = $result['event'];
        if (! empty($result['created']) && $event->event_type === 'order_paid') {
            $linkedTurn = $event->wise_turn_id
                ? WiseTurn::query()->find($event->wise_turn_id)
                : null;
            $this->experienceRecorder->fromCommercePaid($event, $linkedTurn);
        }

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'conversation_id' => $event->conversation_id,
            'wise_turn_id' => $event->wise_turn_id,
            'schema_version' => CommerceEventTypes::VERSION,
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Catalog offer upsert — adapters sync products/services as type=product drafts.
     * Never auto-publishes; content/identity change unpublishes until human approve.
     */
    public function knowledgeUpsert(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
                'message' => 'Provide a valid Wise AI API key as a Bearer token.',
            ], 401);
        }

        $validated = $request->validate([
            'external_id' => 'required|string|max:191',
            'title' => 'required|string|max:191',
            'answer' => 'required|string|max:5000',
            'platform' => 'nullable|string|max:40',
            'offer_kind' => 'nullable|in:physical,digital,service,subscription,other',
            'sku' => 'nullable|string|max:64',
            'question' => 'nullable|string|max:2000',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:60',
        ]);

        try {
            $result = $this->catalogUpsertor->upsert($apiKey, $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_catalog_upsert',
                'message' => $e->getMessage(),
            ], 422);
        }

        $item = $result['item'];

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'changed' => $result['changed'],
            'unpublished' => $result['unpublished'],
            'knowledge_id' => $item->id,
            'external_id' => $item->external_id,
            'status' => $item->status,
            'version' => $item->version,
            'schema_version' => CatalogKnowledgeUpsertor::SCHEMA_VERSION,
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Replay-safe explainability for a sealed turn (same API key ownership).
     */
    public function explain(Request $request, int $turn): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
                'message' => 'Provide a valid Wise AI API key as a Bearer token.',
            ], 401);
        }

        $record = WiseTurn::query()
            ->where('id', $turn)
            ->where('wise_api_key_id', $apiKey->id)
            ->first();

        if (! $record) {
            return response()->json([
                'ok' => false,
                'error' => 'turn_not_found',
                'message' => 'Turn not found for this API key.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'explain' => $this->explainer->build($record),
        ]);
    }

    private function resolveApiKey(Request $request): ?WiseApiKey
    {
        $plain = $request->bearerToken() ?: (string) $request->header('X-Wise-Key', '');

        if ($plain === '') {
            return null;
        }

        return WiseApiKey::findActiveByPlainKey($plain);
    }
}
