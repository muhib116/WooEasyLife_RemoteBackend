<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\MerchantOrderDetail;
use App\Models\OrderIntelligence\PlatformOrder;
use App\Services\FraudCheckService;
use Illuminate\Support\Facades\DB;

class MerchantIntelligenceService
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private PlatformIntelligenceReader $platformIntelligenceReader,
        private LegacyReportFormatter $legacyReportFormatter,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function customerProfile(string $phone, ?int $accessTokenId = null): ?array
    {
        $phoneNormalized = $this->fraudCheckService->normalizePhone($phone);
        $platformData = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessTokenId);

        if ($platformData === null) {
            return null;
        }

        return [
            'phone' => $phoneNormalized,
            'report' => $this->legacyReportFormatter->format($platformData),
            'recent_orders' => $this->recentOrdersForPhone($phoneNormalized, $accessTokenId, 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $accessTokenId): array
    {
        $ordersQuery = PlatformOrder::query()->where('access_token_id', $accessTokenId);

        $statusCounts = (clone $ordersQuery)
            ->select('current_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status')
            ->all();

        $totalOrders = (int) (clone $ordersQuery)->count();
        $totalCustomers = (int) (clone $ordersQuery)->distinct('platform_customer_id')->count('platform_customer_id');
        $totalRevenue = (float) (clone $ordersQuery)->sum('order_amount');

        return [
            'summary' => [
                'total_orders' => $totalOrders,
                'total_customers' => $totalCustomers,
                'total_revenue' => round($totalRevenue, 2),
            ],
            'status_counts' => $statusCounts,
            'top_products' => $this->topProducts($accessTokenId, 10),
            'recent_orders' => $this->recentOrders($accessTokenId, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orders(int $accessTokenId, ?string $status = null, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $query = PlatformOrder::query()
            ->with(['details', 'customer:id,phone_normalized,latest_name'])
            ->where('access_token_id', $accessTokenId)
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '') {
            $query->where('current_status', $status);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (PlatformOrder $order) => $this->formatOrder($order))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(int $accessTokenId, int $limit): array
    {
        return PlatformOrder::query()
            ->with(['details', 'customer:id,phone_normalized,latest_name'])
            ->where('access_token_id', $accessTokenId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (PlatformOrder $order) => $this->formatOrder($order))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrdersForPhone(string $phoneNormalized, ?int $accessTokenId, int $limit): array
    {
        $query = PlatformOrder::query()
            ->with(['details', 'customer:id,phone_normalized,latest_name'])
            ->whereHas('customer', fn ($builder) => $builder->where('phone_normalized', $phoneNormalized))
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($accessTokenId !== null) {
            $query->where('access_token_id', $accessTokenId);
        }

        return $query->get()
            ->map(fn (PlatformOrder $order) => $this->formatOrder($order))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topProducts(int $accessTokenId, int $limit): array
    {
        return MerchantOrderDetail::query()
            ->select('product_title', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(quantity) as total_quantity'))
            ->where('access_token_id', $accessTokenId)
            ->whereNotNull('product_title')
            ->where('product_title', '!=', '')
            ->groupBy('product_title')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_title' => $row->product_title,
                'order_count' => (int) $row->order_count,
                'total_quantity' => (int) $row->total_quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrder(PlatformOrder $order): array
    {
        return [
            'id' => $order->id,
            'wc_order_id' => $order->wc_order_id,
            'phone' => $order->customer?->phone_normalized,
            'customer_name' => $order->details?->customer_name ?? $order->customer?->latest_name,
            'product_title' => $order->details?->product_title,
            'order_amount' => $order->order_amount,
            'current_status' => $order->current_status,
            'courier_partner' => $order->courier_partner,
            'consignment_id' => $order->consignment_id,
            'created_at' => optional($order->created_at)?->toIso8601String(),
            'status_changed_at' => optional($order->status_changed_at)?->toIso8601String(),
        ];
    }
}
