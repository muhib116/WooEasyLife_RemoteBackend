<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Models\OrderIntelligence\MerchantCustomerStats;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformCustomerStats;
use App\Models\OrderIntelligence\PlatformOrder;
use Illuminate\Support\Facades\DB;

class StatsProjector
{
    public function __construct(
        private CustomerRiskScorer $riskScorer,
    ) {}

    /**
     * @return array<string, int>
     */
    public function countOrdersByStatus(int $platformCustomerId, ?int $accessTokenId = null): array
    {
        $counts = OrderStatus::defaultCounts();

        $query = PlatformOrder::query()
            ->select('current_status', DB::raw('COUNT(*) as aggregate'))
            ->where('platform_customer_id', $platformCustomerId)
            ->groupBy('current_status');

        if ($accessTokenId !== null) {
            $query->where('access_token_id', $accessTokenId);
        }

        foreach ($query->get() as $row) {
            $status = (string) $row->current_status;

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    public function project(int $platformCustomerId): void
    {
        $customer = PlatformCustomer::query()->find($platformCustomerId);

        if (! $customer) {
            return;
        }

        $globalCounts = $this->countOrdersByStatus($platformCustomerId);
        $rates = $this->computeRates($globalCounts);
        $totalOrders = array_sum($globalCounts);
        $totalMerchants = PlatformOrder::query()
            ->where('platform_customer_id', $platformCustomerId)
            ->distinct('access_token_id')
            ->count('access_token_id');
        $totalRevenue = (float) PlatformOrder::query()
            ->where('platform_customer_id', $platformCustomerId)
            ->sum('order_amount');
        $lastOrderAt = PlatformOrder::query()
            ->where('platform_customer_id', $platformCustomerId)
            ->max('created_at');

        $deliveryRate = $this->rateValue($rates['delivery_rate']);
        $returnRate = $this->rateValue($rates['return_rate']);

        $risk = $this->riskScorer->score(
            platformCustomerId: $platformCustomerId,
            counts: $globalCounts,
            rates: $rates,
            totalOrders: $totalOrders,
            totalRevenue: $totalRevenue,
            firstSeenAt: optional($customer->first_seen_at)?->toIso8601String(),
            lastOrderAt: $lastOrderAt ? (string) $lastOrderAt : null,
        );

        PlatformCustomerStats::query()->updateOrCreate(
            ['platform_customer_id' => $platformCustomerId],
            [
                'phone_normalized' => $customer->phone_normalized,
                'counts' => $globalCounts,
                'rates' => $rates,
                'total_orders' => $totalOrders,
                'total_merchants' => $totalMerchants,
                'total_revenue' => $totalRevenue,
                'delivery_rate' => $deliveryRate,
                'return_rate' => $returnRate,
                'risk_tier' => $risk['risk_tier']->value,
                'risk_score' => $risk['risk_score'],
                'ai_profile' => $risk['ai_profile'],
                'last_order_at' => $lastOrderAt,
                'stats_computed_at' => now(),
            ],
        );

        $merchantTokenIds = PlatformOrder::query()
            ->where('platform_customer_id', $platformCustomerId)
            ->distinct()
            ->pluck('access_token_id');

        foreach ($merchantTokenIds as $accessTokenId) {
            $merchantCounts = $this->countOrdersByStatus($platformCustomerId, (int) $accessTokenId);
            $userId = PlatformOrder::query()
                ->where('platform_customer_id', $platformCustomerId)
                ->where('access_token_id', $accessTokenId)
                ->value('user_id');

            MerchantCustomerStats::query()->updateOrCreate(
                [
                    'access_token_id' => (int) $accessTokenId,
                    'platform_customer_id' => $platformCustomerId,
                ],
                [
                    'user_id' => (int) $userId,
                    'phone_normalized' => $customer->phone_normalized,
                    'counts' => $merchantCounts,
                    'total_orders' => array_sum($merchantCounts),
                    'last_order_at' => PlatformOrder::query()
                        ->where('platform_customer_id', $platformCustomerId)
                        ->where('access_token_id', $accessTokenId)
                        ->max('created_at'),
                    'stats_computed_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, string>
     */
    public function computeRates(array $counts): array
    {
        $newOrder = max(1, (int) ($counts[OrderStatus::NewOrder->value] ?? 0));
        $courierEntry = (int) ($counts[OrderStatus::CourierEntry->value] ?? 0)
            + (int) ($counts[OrderStatus::CourierHandover->value] ?? 0)
            + (int) ($counts[OrderStatus::Delivered->value] ?? 0)
            + (int) ($counts[OrderStatus::PartiallyDelivered->value] ?? 0)
            + (int) ($counts[OrderStatus::Returned->value] ?? 0);
        $shipped = max(1, $courierEntry);
        $delivered = (int) ($counts[OrderStatus::Delivered->value] ?? 0);
        $returned = (int) ($counts[OrderStatus::Returned->value] ?? 0);
        $canceled = (int) ($counts[OrderStatus::Canceled->value] ?? 0);
        $total = max(1, array_sum($counts));

        return [
            'confirmation_rate' => $this->formatRate($courierEntry, $newOrder),
            'delivery_rate' => $this->formatRate($delivered, $shipped),
            'return_rate' => $this->formatRate($returned, $shipped),
            'cancel_rate' => $this->formatRate($canceled, $total),
        ];
    }

    private function formatRate(int $numerator, int $denominator): string
    {
        return (string) (int) ceil(($numerator / max(1, $denominator)) * 100) . '%';
    }

    private function rateValue(string $formattedRate): float
    {
        if (! preg_match('/(\d+)/', $formattedRate, $matches)) {
            return 0.0;
        }

        return ((int) $matches[1]) / 100;
    }
}
