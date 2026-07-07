<?php

namespace App\Services\OrderIntelligence;

use App\Models\AccessToken;
use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\CourierFraudReport;
use App\Models\OrderIntelligence\MerchantCustomerStats;
use App\Models\OrderIntelligence\MerchantOrderDetail;
use App\Models\OrderIntelligence\OrderStatusEvent;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformCustomerStats;
use App\Models\OrderIntelligence\PlatformOrder;
use App\Services\FraudCheckService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class OrderIntelligenceAdminService
{
    public function __construct(
        private ProductDemandAnalyticsService $productDemandAnalyticsService,
        private MerchantIntelligenceService $merchantIntelligenceService,
        private PlatformIntelligenceReader $platformIntelligenceReader,
        private AiIntelligenceService $aiIntelligenceService,
        private FraudCheckService $fraudCheckService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function merchants(): array
    {
        return AccessToken::query()
            ->with('tokenable:id,name,email')
            ->where('status', true)
            ->orderBy('domain')
            ->get(['id', 'name', 'domain', 'tokenable_type', 'tokenable_id'])
            ->map(fn (AccessToken $token) => [
                'id' => $token->id,
                'domain' => $token->domain,
                'name' => $token->name,
                'merchant_name' => $token->tokenable?->name,
                'merchant_email' => $token->tokenable?->email,
                'label' => trim(($token->tokenable?->name ?? 'Merchant').' — '.($token->domain ?? $token->name)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(): array
    {
        $summary = $this->productDemandAnalyticsService->platformSummary();
        $topProducts = $this->productDemandAnalyticsService->globalProducts(
            (int) config('order_intelligence.analytics.default_product_limit', 20),
        );

        $recentOrders = PlatformOrder::query()
            ->with(['details:id,platform_order_id,customer_name,product_title', 'customer:id,phone_normalized,latest_name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (PlatformOrder $order) => $this->formatOrderRow($order))
            ->values()
            ->all();

        $recentCustomers = PlatformCustomerStats::query()
            ->orderByDesc('stats_computed_at')
            ->limit(8)
            ->get(['phone_normalized', 'total_orders', 'total_merchants', 'risk_tier', 'risk_score', 'stats_computed_at'])
            ->map(fn (PlatformCustomerStats $stats) => [
                'phone' => $stats->phone_normalized,
                'total_orders' => $stats->total_orders,
                'total_merchants' => $stats->total_merchants,
                'risk_tier' => $stats->risk_tier,
                'risk_score' => $stats->risk_score,
                'stats_computed_at' => optional($stats->stats_computed_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'top_products' => $topProducts['products'] ?? [],
            'recent_orders' => $recentOrders,
            'recent_customers' => $recentCustomers,
            'config' => $this->configSnapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function configSnapshot(): array
    {
        return [
            'enabled' => (bool) config('order_intelligence.enabled'),
            'fraud_check_mode' => config('order_intelligence.fraud_check.mode'),
            'search_driver' => config('order_intelligence.search.driver'),
            'suggest_enabled' => (bool) config('order_intelligence.suggest.enabled'),
            'analytics_enabled' => (bool) config('order_intelligence.analytics.enabled'),
            'cache_enabled' => (bool) config('order_intelligence.cache.enabled'),
            'statuses' => config('order_intelligence.statuses', []),
            'terminal_statuses' => config('order_intelligence.terminal_statuses', []),
            'risk_tiers' => config('order_intelligence.risk_tiers', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function merchantDashboard(int $accessTokenId): array
    {
        return $this->merchantIntelligenceService->dashboard($accessTokenId);
    }

    /**
     * @return array<string, mixed>
     */
    public function customersList(?string $search, ?string $riskTier, int $page, int $perPage): array
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $query = PlatformCustomerStats::query()->orderByDesc('total_orders');

        if ($search !== null && $search !== '') {
            $query->where('phone_normalized', 'like', '%'.preg_replace('/\D+/', '', $search).'%');
        }

        if ($riskTier !== null && $riskTier !== '') {
            $query->where('risk_tier', $riskTier);
        }

        $paginator = $query->paginate($perPage, [
            'id',
            'phone_normalized',
            'total_orders',
            'total_merchants',
            'risk_tier',
            'risk_score',
            'stats_computed_at',
        ], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (PlatformCustomerStats $stats) => [
                'phone' => $stats->phone_normalized,
                'total_orders' => $stats->total_orders,
                'total_merchants' => $stats->total_merchants,
                'risk_tier' => $stats->risk_tier,
                'risk_score' => $stats->risk_score,
                'stats_computed_at' => optional($stats->stats_computed_at)?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customerLookup(string $phone, ?int $accessTokenId = null): ?array
    {
        $phoneNormalized = $this->fraudCheckService->normalizePhone($phone);
        $platform = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessTokenId);
        $aiProfile = $this->aiIntelligenceService->customerAiProfile($phoneNormalized, $accessTokenId);

        if ($platform === null && $aiProfile === null) {
            return null;
        }

        $customer = PlatformCustomer::query()
            ->where('phone_normalized', $phoneNormalized)
            ->first(['id', 'phone_normalized', 'latest_name', 'latest_address', 'first_seen_at', 'last_seen_at', 'last_order_at']);

        return [
            'phone' => $phoneNormalized,
            'customer' => $customer ? [
                'id' => $customer->id,
                'latest_name' => $customer->latest_name,
                'latest_address' => $customer->latest_address,
                'first_seen_at' => optional($customer->first_seen_at)?->toIso8601String(),
                'last_seen_at' => optional($customer->last_seen_at)?->toIso8601String(),
                'last_order_at' => optional($customer->last_order_at)?->toIso8601String(),
            ] : null,
            'platform' => $platform,
            'ai_profile' => $aiProfile,
            'recent_orders' => PlatformOrder::query()
                ->with(['details', 'customer:id,phone_normalized,latest_name'])
                ->when($accessTokenId, fn (Builder $q) => $q->where('access_token_id', $accessTokenId))
                ->whereHas('customer', fn (Builder $q) => $q->where('phone_normalized', $phoneNormalized))
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(fn (PlatformOrder $order) => $this->formatOrderRow($order))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ordersList(?int $accessTokenId, ?string $status, ?string $search, int $page, int $perPage): array
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $query = PlatformOrder::query()
            ->with(['details', 'customer:id,phone_normalized,latest_name'])
            ->orderByDesc('created_at');

        if ($accessTokenId !== null) {
            $query->where('access_token_id', $accessTokenId);
        }

        if ($status !== null && $status !== '') {
            $query->where('current_status', $status);
        }

        if ($search !== null && $search !== '') {
            $needle = '%'.$search.'%';
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(function (Builder $builder) use ($needle, $digits) {
                $builder->where('wc_order_id', 'like', $needle)
                    ->orWhere('consignment_id', 'like', $needle);

                if ($digits !== '') {
                    $builder->orWhereHas('customer', fn (Builder $q) => $q->where('phone_normalized', 'like', '%'.$digits.'%'));
                }
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (PlatformOrder $order) => $this->formatOrderRow($order))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordsOverview(): array
    {
        $tables = [
            'platform_customers' => PlatformCustomer::class,
            'platform_orders' => PlatformOrder::class,
            'merchant_order_details' => MerchantOrderDetail::class,
            'order_status_events' => OrderStatusEvent::class,
            'platform_customer_stats' => PlatformCustomerStats::class,
            'merchant_customer_stats' => MerchantCustomerStats::class,
            'courier_customer_snapshots' => CourierCustomerSnapshot::class,
            'courier_fraud_reports' => CourierFraudReport::class,
        ];

        $overview = [];

        foreach ($tables as $key => $modelClass) {
            /** @var class-string<Model> $modelClass */
            $overview[] = [
                'key' => $key,
                'label' => str_replace('_', ' ', ucwords($key, '_')),
                'count' => $modelClass::query()->count(),
                'latest_at' => $this->latestTimestamp($modelClass),
            ];
        }

        return [
            'tables' => $overview,
            'total_records' => collect($overview)->sum('count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordsForTable(string $table, int $page, int $perPage): array
    {
        $config = $this->tableBrowseConfig()[$table] ?? null;

        if ($config === null) {
            abort(404, 'Unknown intelligence table.');
        }

        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query()->orderByDesc($config['order_column']);

        $paginator = $query->paginate($perPage, $config['columns'], 'page', $page);

        return [
            'table' => $table,
            'label' => $config['label'],
            'columns' => $config['columns'],
            'data' => collect($paginator->items())->map(function (Model $row) use ($config) {
                $item = [];

                foreach ($config['columns'] as $column) {
                    $value = $row->getAttribute($column);

                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    } elseif (is_array($value)) {
                        $value = json_encode($value);
                    }

                    $item[$column] = $value;
                }

                return $item;
            })->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tableBrowseConfig(): array
    {
        return [
            'platform_customers' => [
                'label' => 'Platform Customers',
                'model' => PlatformCustomer::class,
                'order_column' => 'id',
                'columns' => ['id', 'phone_normalized', 'latest_name', 'first_seen_at', 'last_seen_at', 'last_order_at'],
            ],
            'platform_orders' => [
                'label' => 'Platform Orders',
                'model' => PlatformOrder::class,
                'order_column' => 'id',
                'columns' => ['id', 'platform_customer_id', 'access_token_id', 'wc_order_id', 'current_status', 'order_amount', 'courier_partner', 'consignment_id', 'created_at'],
            ],
            'merchant_order_details' => [
                'label' => 'Merchant Order Details',
                'model' => MerchantOrderDetail::class,
                'order_column' => 'id',
                'columns' => ['id', 'platform_order_id', 'access_token_id', 'customer_name', 'product_title', 'product_sku', 'quantity', 'unit_price'],
            ],
            'order_status_events' => [
                'label' => 'Order Status Events',
                'model' => OrderStatusEvent::class,
                'order_column' => 'id',
                'columns' => ['id', 'platform_order_id', 'from_status', 'to_status', 'source', 'courier_partner', 'created_at'],
            ],
            'platform_customer_stats' => [
                'label' => 'Platform Customer Stats',
                'model' => PlatformCustomerStats::class,
                'order_column' => 'id',
                'columns' => ['id', 'phone_normalized', 'total_orders', 'total_merchants', 'risk_tier', 'risk_score', 'stats_computed_at'],
            ],
            'merchant_customer_stats' => [
                'label' => 'Merchant Customer Stats',
                'model' => MerchantCustomerStats::class,
                'order_column' => 'id',
                'columns' => ['id', 'phone_normalized', 'access_token_id', 'total_orders', 'stats_computed_at'],
            ],
            'courier_customer_snapshots' => [
                'label' => 'Courier Snapshots',
                'model' => CourierCustomerSnapshot::class,
                'order_column' => 'id',
                'columns' => ['id', 'phone_normalized', 'courier', 'total_order', 'confirmed', 'cancel', 'success_rate', 'fetched_at'],
            ],
            'courier_fraud_reports' => [
                'label' => 'Courier Fraud Reports',
                'model' => CourierFraudReport::class,
                'order_column' => 'id',
                'columns' => ['id', 'phone_normalized', 'courier', 'reporter_name', 'consignment_id', 'reported_at'],
            ],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function latestTimestamp(string $modelClass): ?string
    {
        $table = (new $modelClass)->getTable();
        $column = match ($table) {
            'platform_customer_stats', 'merchant_customer_stats' => 'stats_computed_at',
            'courier_customer_snapshots' => 'fetched_at',
            'courier_fraud_reports' => 'reported_at',
            default => 'created_at',
        };

        if (! Schema::hasColumn($table, $column)) {
            return null;
        }

        $value = $modelClass::query()->max($column);

        return $value ? (string) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderRow(PlatformOrder $order): array
    {
        return [
            'id' => $order->id,
            'access_token_id' => $order->access_token_id,
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
