<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformOrder;
use App\Services\FraudCheckService;
use Illuminate\Support\Facades\DB;

class FraudCheckInventory
{
    public function __construct(private FraudCheckService $fraudCheckService) {}

    /**
     * Platform-wide counts. No store or merchant split.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $couriers = $this->courierRollup();

        return [
            'phones' => PlatformCustomer::query()->count(),
            'snapshots' => (int) array_sum(array_column($couriers, 'records')),
            'plugin_orders' => PlatformOrder::query()->count(),
            'order_status' => $this->statusCounts(),
            'sources' => $this->sourceCounts(),
            'couriers' => $couriers,
            'courier_totals' => [
                'orders' => (int) array_sum(array_column($couriers, 'orders')),
                'delivered' => (int) array_sum(array_column($couriers, 'delivered')),
                'cancelled' => (int) array_sum(array_column($couriers, 'cancelled')),
                'partial' => (int) array_sum(array_column($couriers, 'partial')),
            ],
        ];
    }

    /**
     * Records stored for one phone. No store fields.
     *
     * @return array<string, mixed>
     */
    public function person(string $phone): array
    {
        $normalized = $this->fraudCheckService->normalizePhone($phone);
        $customer = PlatformCustomer::query()->where('phone_normalized', $normalized)->first();

        if ($customer === null) {
            return [
                'phone' => $normalized,
                'found' => false,
                'name' => null,
                'snapshots' => [],
                'orders' => [
                    'total' => 0,
                    'by_status' => $this->emptyStatuses(),
                    'by_source' => [],
                    'recent' => [],
                ],
            ];
        }

        $snapshots = CourierCustomerSnapshot::query()
            ->where('platform_customer_id', $customer->id)
            ->orderBy('courier')
            ->get()
            ->map(fn (CourierCustomerSnapshot $snapshot) => [
                'courier' => $snapshot->courier,
                'label' => $this->courierLabel($snapshot->courier),
                'orders' => (int) $snapshot->total_order,
                'delivered' => (int) $snapshot->confirmed,
                'cancelled' => (int) $snapshot->cancel,
                'partial' => (int) (is_array($snapshot->raw_report) ? ($snapshot->raw_report['partial'] ?? 0) : 0),
                'fetched_at' => optional($snapshot->fetched_at)?->timezone('Asia/Dhaka')->format('d M Y, H:i'),
            ])
            ->values()
            ->all();

        $orders = PlatformOrder::query()->where('platform_customer_id', $customer->id);

        $recent = (clone $orders)
            ->orderByDesc('status_changed_at')
            ->limit(8)
            ->get(['current_status', 'source', 'courier_partner', 'order_amount', 'status_changed_at'])
            ->map(fn (PlatformOrder $order) => [
                'status' => (string) $order->current_status,
                'status_label' => $this->statusLabel((string) $order->current_status),
                'source' => (string) $order->source,
                'source_label' => $this->sourceLabel((string) $order->source),
                'courier' => $order->courier_partner ? $this->courierLabel((string) $order->courier_partner) : null,
                'amount' => $order->order_amount !== null ? (float) $order->order_amount : null,
                'changed_at' => optional($order->status_changed_at)?->timezone('Asia/Dhaka')->format('d M Y, H:i'),
            ])
            ->all();

        return [
            'phone' => $normalized,
            'found' => true,
            'name' => $customer->latest_name,
            'snapshots' => $snapshots,
            'orders' => [
                'total' => (clone $orders)->count(),
                'by_status' => $this->statusCounts($customer->id),
                'by_source' => $this->sourceCounts($customer->id),
                'recent' => $recent,
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, emphasis: bool}>
     */
    private function statusCounts(?int $customerId = null): array
    {
        $query = PlatformOrder::query();

        if ($customerId !== null) {
            $query->where('platform_customer_id', $customerId);
        }

        $raw = $query
            ->select('current_status', DB::raw('COUNT(*) as total'))
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        $rows = [];

        foreach (OrderStatus::cases() as $status) {
            $rows[] = [
                'key' => $status->value,
                'label' => $this->statusLabel($status->value),
                'count' => (int) ($raw[$status->value] ?? 0),
                'emphasis' => in_array($status, [
                    OrderStatus::Delivered,
                    OrderStatus::Canceled,
                    OrderStatus::PartiallyDelivered,
                    OrderStatus::Returned,
                ], true),
            ];
            unset($raw[$status->value]);
        }

        foreach ($raw as $status => $total) {
            $rows[] = [
                'key' => (string) $status,
                'label' => $this->statusLabel((string) $status),
                'count' => (int) $total,
                'emphasis' => false,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function emptyStatuses(): array
    {
        return array_map(fn (OrderStatus $status) => [
            'key' => $status->value,
            'label' => $this->statusLabel($status->value),
            'count' => 0,
            'emphasis' => in_array($status, [
                OrderStatus::Delivered,
                OrderStatus::Canceled,
                OrderStatus::PartiallyDelivered,
                OrderStatus::Returned,
            ], true),
        ], OrderStatus::cases());
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function sourceCounts(?int $customerId = null): array
    {
        $query = PlatformOrder::query();

        if ($customerId !== null) {
            $query->where('platform_customer_id', $customerId);
        }

        return $query
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->source,
                'label' => $this->sourceLabel((string) $row->source),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array{courier: string, label: string, records: int, orders: int, delivered: int, cancelled: int, partial: int}>
     */
    private function courierRollup(): array
    {
        return CourierCustomerSnapshot::query()
            ->select('courier')
            ->selectRaw('COUNT(*) as records')
            ->selectRaw('COALESCE(SUM(total_order), 0) as orders')
            ->selectRaw('COALESCE(SUM(confirmed), 0) as delivered')
            ->selectRaw('COALESCE(SUM(cancel), 0) as cancelled')
            ->selectRaw("COALESCE(SUM(CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_report, '$.partial')), '0') AS UNSIGNED)), 0) as partial")
            ->groupBy('courier')
            ->orderBy('courier')
            ->get()
            ->map(fn ($row) => [
                'courier' => (string) $row->courier,
                'label' => $this->courierLabel((string) $row->courier),
                'records' => (int) $row->records,
                'orders' => (int) $row->orders,
                'delivered' => (int) $row->delivered,
                'cancelled' => (int) $row->cancelled,
                'partial' => (int) $row->partial,
            ])
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new_order' => 'New',
            'courier_entry' => 'Courier entry',
            'courier_handover' => 'Handover',
            'delivered' => 'Delivered',
            'partially_delivered' => 'Partial',
            'returned' => 'Returned',
            'canceled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'fraud_check' => 'Fraud check',
            'courier_create' => 'Courier booking',
            'webhook' => 'Courier webhook',
            default => ucfirst(str_replace('_', ' ', $source)),
        };
    }

    private function courierLabel(string $courier): string
    {
        return match (strtolower($courier)) {
            'steadfast' => 'Steadfast',
            'pathao' => 'Pathao',
            'paperfly' => 'Paperfly',
            'redx' => 'RedX',
            'carrybee' => 'Carrybee',
            default => ucfirst($courier),
        };
    }
}
