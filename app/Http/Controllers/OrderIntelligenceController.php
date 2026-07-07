<?php

namespace App\Http\Controllers;

use App\Services\Courier\CourierAccountService;
use App\Services\OrderIntelligence\AiIntelligenceService;
use App\Services\OrderIntelligence\IntelligenceSuggestService;
use App\Services\OrderIntelligence\MerchantIntelligenceService;
use App\Services\OrderIntelligence\ProductDemandAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderIntelligenceController extends Controller
{
    public function __construct(
        private IntelligenceSuggestService $suggestService,
        private MerchantIntelligenceService $merchantIntelligenceService,
        private AiIntelligenceService $aiIntelligenceService,
        private ProductDemandAnalyticsService $productDemandAnalyticsService,
        private CourierAccountService $courierAccountService,
    ) {}

    public function suggest(Request $request): JsonResponse
    {
        if (! config('order_intelligence.suggest.enabled', true)) {
            return response()->json([
                'message' => 'Suggestions are currently disabled.',
            ], 503);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:20'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('order_intelligence.suggest.max_limit', 20)],
        ]);

        return response()->json(
            $this->suggestService->suggest(
                (string) $validated['q'],
                (int) ($validated['limit'] ?? config('order_intelligence.suggest.default_limit', 8)),
            ),
        );
    }

    public function customer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $accessToken = $this->courierAccountService->resolveAccessToken($request);
            $profile = $this->merchantIntelligenceService->customerProfile(
                (string) $validated['phone'],
                $accessToken?->id,
            );

            if ($profile === null) {
                return response()->json([
                    'message' => 'No platform intelligence found for this phone.',
                ], 404);
            }

            return response()->json($profile);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function dashboard(Request $request): JsonResponse
    {
        $accessToken = $this->courierAccountService->resolveAccessToken($request);

        if (! $accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        return response()->json(
            $this->merchantIntelligenceService->dashboard((int) $accessToken->id),
        );
    }

    public function orders(Request $request): JsonResponse
    {
        $accessToken = $this->courierAccountService->resolveAccessToken($request);

        if (! $accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->merchantIntelligenceService->orders(
                (int) $accessToken->id,
                $validated['status'] ?? null,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            ),
        );
    }

    public function aiCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $accessToken = $this->courierAccountService->resolveAccessToken($request);
            $profile = $this->aiIntelligenceService->customerAiProfile(
                (string) $validated['phone'],
                $accessToken?->id,
            );

            if ($profile === null) {
                return response()->json([
                    'message' => 'No AI profile found for this phone.',
                ], 404);
            }

            return response()->json($profile);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function aiFeatures(Request $request): JsonResponse
    {
        $accessToken = $this->courierAccountService->resolveAccessToken($request);

        if (! $accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json(
            $this->aiIntelligenceService->exportFeatures(
                (int) $accessToken->id,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 50),
            ),
        );
    }

    public function analyticsProducts(Request $request): JsonResponse
    {
        $accessToken = $this->courierAccountService->resolveAccessToken($request);

        if (! $accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'scope' => ['nullable', 'in:merchant,global'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $scope = $validated['scope'] ?? 'merchant';
        $limit = (int) ($validated['limit'] ?? 20);

        if ($scope === 'global') {
            return response()->json($this->productDemandAnalyticsService->globalProducts($limit));
        }

        return response()->json(
            $this->productDemandAnalyticsService->merchantProducts((int) $accessToken->id, $limit),
        );
    }

    public function analyticsPlatform(Request $request): JsonResponse
    {
        $accessToken = $this->courierAccountService->resolveAccessToken($request);

        if (! $accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        return response()->json($this->productDemandAnalyticsService->platformSummary());
    }
}
