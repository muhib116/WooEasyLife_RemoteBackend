<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\MerchantOrderDetail;
use App\Models\OrderIntelligence\PlatformCustomerStats;
use App\Models\OrderIntelligence\PlatformOrder;
use Illuminate\Support\Facades\DB;

class ProductDemandAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function merchantProducts(int $accessTokenId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $products = MerchantOrderDetail::query()
            ->select(
                'product_title',
                'product_sku',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(platform_orders.order_amount) as total_revenue'),
            )
            ->join('platform_orders', 'platform_orders.id', '=', 'merchant_order_details.platform_order_id')
            ->where('merchant_order_details.access_token_id', $accessTokenId)
            ->whereNotNull('merchant_order_details.product_title')
            ->where('merchant_order_details.product_title', '!=', '')
            ->groupBy('product_title', 'product_sku')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_title' => $row->product_title,
                'product_sku' => $row->product_sku,
                'order_count' => (int) $row->order_count,
                'total_quantity' => (int) $row->total_quantity,
                'total_revenue' => round((float) $row->total_revenue, 2),
            ])
            ->values()
            ->all();

        return [
            'scope' => 'merchant',
            'access_token_id' => $accessTokenId,
            'products' => $products,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function globalProducts(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $products = MerchantOrderDetail::query()
            ->select(
                'product_title',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('COUNT(DISTINCT platform_orders.platform_customer_id) as unique_customers'),
                DB::raw('COUNT(DISTINCT platform_orders.access_token_id) as merchant_count'),
            )
            ->join('platform_orders', 'platform_orders.id', '=', 'merchant_order_details.platform_order_id')
            ->whereNotNull('merchant_order_details.product_title')
            ->where('merchant_order_details.product_title', '!=', '')
            ->groupBy('product_title')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_title' => $row->product_title,
                'order_count' => (int) $row->order_count,
                'unique_customers' => (int) $row->unique_customers,
                'merchant_count' => (int) $row->merchant_count,
            ])
            ->values()
            ->all();

        return [
            'scope' => 'global',
            'products' => $products,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function platformSummary(): array
    {
        $totalCustomers = PlatformCustomerStats::query()->count();
        $totalOrders = PlatformOrder::query()->count();
        $totalRevenue = (float) PlatformOrder::query()->sum('order_amount');

        $riskDistribution = PlatformCustomerStats::query()
            ->select('risk_tier', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('risk_tier')
            ->pluck('aggregate', 'risk_tier')
            ->all();

        $statusDistribution = PlatformOrder::query()
            ->select('current_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status')
            ->all();

        return [
            'total_customers' => $totalCustomers,
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'risk_distribution' => $riskDistribution,
            'status_distribution' => $statusDistribution,
            'avg_orders_per_customer' => $totalCustomers > 0
                ? round($totalOrders / $totalCustomers, 2)
                : 0,
        ];
    }
}
