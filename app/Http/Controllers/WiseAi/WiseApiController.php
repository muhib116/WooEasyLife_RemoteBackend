<?php

namespace App\Http\Controllers\WiseAi;

use App\Http\Controllers\Controller;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseFeedback;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\TurnRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wise AI public API v1.
 *
 * Auth: `Authorization: Bearer <api key>` (or `X-Wise-Key` header).
 */
class WiseApiController extends Controller
{
    public function __construct(
        private TurnRunner $runner,
    ) {}

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

        $feedback = WiseFeedback::create([
            'wise_api_key_id' => $apiKey->id,
            'wise_turn_id' => $turn->id,
            'outcome' => $validated['outcome'],
            'reason_code' => $validated['reason_code'] ?? null,
            'edited_reply' => $validated['edited_reply'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'feedback_id' => $feedback->id,
            'turn_id' => $turn->id,
            'outcome' => $feedback->outcome,
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
