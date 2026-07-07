<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderIntelligence\OrderIntelligenceAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class OrderIntelligenceAdminController extends Controller
{
    public function __construct(
        private OrderIntelligenceAdminService $adminService,
    ) {}

    public function index(): Response
    {
        $this->ensureEnabled();

        return Inertia::render('OrderIntelligence/Index', [
            'merchants' => $this->adminService->merchants(),
            'dashboard' => $this->adminService->dashboardPayload(),
        ]);
    }

    public function customers(): Response
    {
        $this->ensureEnabled();

        return Inertia::render('OrderIntelligence/Customers', [
            'merchants' => $this->adminService->merchants(),
            'riskTiers' => array_keys(config('order_intelligence.risk_tiers', [])),
        ]);
    }

    public function orders(): Response
    {
        $this->ensureEnabled();

        return Inertia::render('OrderIntelligence/Orders', [
            'merchants' => $this->adminService->merchants(),
            'statuses' => config('order_intelligence.statuses', []),
        ]);
    }

    public function records(): Response
    {
        $this->ensureEnabled();

        return Inertia::render('OrderIntelligence/Records', [
            'overview' => $this->adminService->recordsOverview(),
        ]);
    }

    public function apiDocs(): Response
    {
        $this->ensureEnabled();

        return Inertia::render('OrderIntelligence/Api', [
            'apiBaseUrl' => rtrim(config('app.url'), '/'),
            'config' => $this->adminService->configSnapshot(),
        ]);
    }

    public function merchantDashboard(int $accessTokenId): JsonResponse
    {
        $this->ensureEnabled();

        return response()->json($this->adminService->merchantDashboard($accessTokenId));
    }

    public function customersList(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:20'],
            'risk_tier' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->adminService->customersList(
            $validated['q'] ?? null,
            $validated['risk_tier'] ?? null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        ));
    }

    public function customerLookup(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'access_token_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $profile = $this->adminService->customerLookup(
            (string) $validated['phone'],
            isset($validated['access_token_id']) ? (int) $validated['access_token_id'] : null,
        );

        if ($profile === null) {
            return response()->json([
                'message' => 'No intelligence found for this phone.',
            ], 404);
        }

        return response()->json($profile);
    }

    public function ordersList(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'access_token_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->adminService->ordersList(
            isset($validated['access_token_id']) ? (int) $validated['access_token_id'] : null,
            $validated['status'] ?? null,
            $validated['q'] ?? null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        ));
    }

    public function recordsTable(Request $request, string $table): JsonResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->adminService->recordsForTable(
            $table,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        ));
    }

    public function reindexSearch(): JsonResponse
    {
        $this->ensureEnabled();

        Artisan::call('order-intelligence:reindex-search');
        $output = trim(Artisan::output());

        return response()->json([
            'status' => 'success',
            'message' => $output !== '' ? $output : 'Search index rebuild started.',
        ]);
    }

    private function ensureEnabled(): void
    {
        if (! config('order_intelligence.enabled', true)) {
            abort(503, 'Order Intelligence is disabled.');
        }
    }
}
