<?php

namespace App\Http\Controllers\Plugin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Services\ApiAccessTokenResolver;
use App\Services\MerchantPackageFeatureGate;
use App\Services\ReturnAi\ReturnAiClient;
use App\Services\ReturnAi\ReturnAiEngine;
use App\Services\ReturnAi\ReturnAiMemoryStore;
use App\Services\ReturnAi\ReturnAiSettings;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * Return desk AI for the WEL plugin (license token auth). The store sends a
 * masked case file and gets back the finished case: decision, flags, call plan,
 * reminder and the actions it may run now. Settings, prompt, model, key, memory
 * and limits all live here.
 */
class ReturnAiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ApiAccessTokenResolver $tokens,
        private MerchantPackageFeatureGate $features,
        private ReturnAiSettings $settings,
        private ReturnAiClient $client,
        private ReturnAiEngine $engine,
        private ReturnAiMemoryStore $memory,
    ) {}

    public function config(Request $request): JsonResponse
    {
        $token = $this->tokens->resolve($request->bearerToken(), $request);
        if (! $token) {
            return $this->errorResponse('Invalid Token', 401);
        }

        return $this->successResponse([
            'settings' => $this->settings->forStore(),
            'enabled' => (bool) config('return_ai.enabled', true),
            'configured' => $this->client->isConfigured(),
            'feature' => $this->features->hasFromRequest($request, 'ai_return_decide'),
            'quota' => $this->quota($token),
            'memory_hash' => $this->memory->hash($token),
        ]);
    }

    public function review(Request $request): JsonResponse
    {
        $token = $this->gate($request);
        if ($token instanceof JsonResponse) {
            return $token;
        }

        $settings = $this->settings->all();
        if (! config('return_ai.enabled', true) || $settings['mode'] === 'off') {
            return $this->refuse('return_ai_off', 'Return AI is turned off on the WooEasyLife hub.', 403);
        }

        $validator = Validator::make($request->all(), [
            'context' => ['required', 'array'],
            'context.today' => ['nullable', 'date_format:Y-m-d'],
            'context.parcel' => ['required', 'array'],
            'context.notes' => ['nullable', 'array', 'max:60'],
            'context.calls' => ['nullable', 'array', 'max:30'],
            'can_act' => ['nullable', 'boolean'],
            'trigger' => ['nullable', 'in:manual,background'],
        ]);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $context = (array) $request->input('context', []);
        if (strlen((string) json_encode($context, JSON_UNESCAPED_UNICODE)) > (int) config('return_ai.max_context_chars', 60000)) {
            return $this->errorResponse('The case file is too large.', 413);
        }

        try {
            $out = $this->engine->review(
                $context,
                $this->memory->forPrompt($token),
                $settings,
                $request->boolean('can_act'),
                fn (): bool => $this->takeCall($token),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->refuse('hub_error', 'Something went wrong on the hub. Please try again.', 500);
        }

        return $this->successResponse([
            ...$out,
            'memory_hash' => $this->memory->hash($token),
            'quota' => $this->quota($token),
            'settings' => $this->settings->forStore(),
        ]);
    }

    public function memoryIndex(Request $request): JsonResponse
    {
        $token = $this->gate($request);
        if ($token instanceof JsonResponse) {
            return $token;
        }

        return $this->successResponse($this->memoryPayload($token));
    }

    public function memorySave(Request $request): JsonResponse
    {
        $token = $this->gate($request);
        if ($token instanceof JsonResponse) {
            return $token;
        }

        $limits = ReturnAiMemoryStore::limits();
        $validator = Validator::make($request->all(), [
            'id' => ['nullable', 'integer', 'min:1'],
            'when' => ['nullable', 'string', 'max:'.($limits['when'] * 2)],
            'then' => ['nullable', 'string', 'max:'.($limits['then'] * 2)],
            'active' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $saved = $this->memory->save($token, $validator->validated());
        if (! $saved['ok']) {
            return $this->errorResponse($saved['error'], $saved['status']);
        }

        return $this->successResponse([...$this->memoryPayload($token), 'item' => $saved['item']], 'মেমোরি সেভ হয়েছে');
    }

    public function memoryDelete(Request $request, int $memory): JsonResponse
    {
        $token = $this->gate($request);
        if ($token instanceof JsonResponse) {
            return $token;
        }

        if (! $this->memory->delete($token, $memory)) {
            return $this->errorResponse('এই মেমোরি পাওয়া যায়নি', 404);
        }

        return $this->successResponse($this->memoryPayload($token), 'মুছে ফেলা হয়েছে');
    }

    private function gate(Request $request): AccessToken|JsonResponse
    {
        $token = $this->tokens->resolve($request->bearerToken(), $request);
        if (! $token) {
            return $this->errorResponse('Invalid Token', 401);
        }
        if (! $this->features->hasFromRequest($request, 'ai_return_decide')) {
            return $this->refuse('feature_locked', 'Your plan does not include AI Return Decide.', 403);
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function memoryPayload(AccessToken $token): array
    {
        return [
            'items' => $this->memory->all($token),
            'limits' => ReturnAiMemoryStore::limits(),
            'memory_hash' => $this->memory->hash($token),
        ];
    }

    /**
     * @return array{used: int, limit: int}
     */
    private function quota(AccessToken $token): array
    {
        return [
            'used' => (int) Cache::get($this->counterKey($token), 0),
            'limit' => (int) $this->settings->all()['daily_review_limit'],
        ];
    }

    /**
     * Spend one model call from today's per-store quota.
     */
    private function takeCall(AccessToken $token): bool
    {
        $quota = $this->quota($token);
        if ($quota['used'] >= $quota['limit']) {
            return false;
        }

        $key = $this->counterKey($token);
        Cache::add($key, 0, now()->endOfDay()->addHour());
        Cache::increment($key);

        return true;
    }

    private function counterKey(AccessToken $token): string
    {
        return 'return_ai:calls:'.$token->id.':'.now()->toDateString();
    }

    private function refuse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
