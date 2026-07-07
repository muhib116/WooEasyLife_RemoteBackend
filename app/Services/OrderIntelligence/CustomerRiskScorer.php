<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\RiskTier;
use App\Models\OrderIntelligence\CourierFraudReport;
use App\Models\OrderIntelligence\PlatformOrder;

class CustomerRiskScorer
{
    /**
     * @param  array<string, int>  $counts
     * @param  array<string, string>  $rates
     * @return array{risk_score: int, risk_tier: RiskTier, ai_profile: array<string, mixed>}
     */
    public function score(
        int $platformCustomerId,
        array $counts,
        array $rates,
        int $totalOrders,
        float $totalRevenue,
        ?string $firstSeenAt,
        ?string $lastOrderAt,
    ): array {
        $deliveryRate = $this->parseRate($rates['delivery_rate'] ?? '0%');
        $returnRate = $this->parseRate($rates['return_rate'] ?? '0%');
        $cancelRate = $this->parseRate($rates['cancel_rate'] ?? '0%');
        $confirmationRate = $this->parseRate($rates['confirmation_rate'] ?? '0%');

        $fraudCount = CourierFraudReport::query()
            ->where('platform_customer_id', $platformCustomerId)
            ->count();

        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0;
        $orderFrequencyDays = $this->orderFrequencyDays($platformCustomerId, $totalOrders);
        $recencyDays = $this->recencyDays($lastOrderAt);

        $riskScore = $this->computeRiskScore(
            $deliveryRate,
            $returnRate,
            $cancelRate,
            $fraudCount,
            $totalOrders,
        );

        $riskTier = $this->resolveRiskTier($riskScore, $totalOrders);

        $riskFactors = $this->riskFactors(
            $deliveryRate,
            $returnRate,
            $cancelRate,
            $fraudCount,
            $totalOrders,
        );

        return [
            'risk_score' => $riskScore,
            'risk_tier' => $riskTier,
            'ai_profile' => [
                'risk_score' => $riskScore,
                'risk_tier' => $riskTier->value,
                'risk_factors' => $riskFactors,
                'delivery_rate' => $deliveryRate,
                'return_rate' => $returnRate,
                'cancel_rate' => $cancelRate,
                'confirmation_rate' => $confirmationRate,
                'fraud_report_count' => $fraudCount,
                'total_orders' => $totalOrders,
                'avg_order_value' => $avgOrderValue,
                'order_frequency_days' => $orderFrequencyDays,
                'recency_days' => $recencyDays,
                'rfm' => [
                    'recency' => $recencyDays,
                    'frequency' => $totalOrders,
                    'monetary' => $totalRevenue,
                ],
                'status_distribution' => $counts,
                'computed_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function riskFactors(
        float $deliveryRate,
        float $returnRate,
        float $cancelRate,
        int $fraudCount,
        int $totalOrders,
    ): array {
        $factors = [];

        if ($fraudCount > 0) {
            $factors[] = "Reported on courier fraud list ({$fraudCount} report(s))";
        }

        if ($totalOrders < 3) {
            $factors[] = 'Limited order history on platform';
        }

        if ($returnRate >= 0.35) {
            $factors[] = 'High return rate';
        }

        if ($cancelRate >= 0.25) {
            $factors[] = 'High cancellation rate';
        }

        if ($deliveryRate > 0 && $deliveryRate < 0.45) {
            $factors[] = 'Low delivery success rate';
        }

        if ($deliveryRate >= 0.75 && $totalOrders >= 5) {
            $factors[] = 'Strong delivery history';
        }

        return $factors;
    }

    private function computeRiskScore(
        float $deliveryRate,
        float $returnRate,
        float $cancelRate,
        int $fraudCount,
        int $totalOrders,
    ): int {
        if ($totalOrders === 0) {
            return 50;
        }

        $score = ((1 - $deliveryRate) * 40)
            + ($returnRate * 30)
            + ($cancelRate * 15)
            + (min($fraudCount * 10, 15));

        if ($totalOrders < 3) {
            $score = max($score, 45);
        }

        return (int) min(100, max(0, round($score)));
    }

    private function resolveRiskTier(int $riskScore, int $totalOrders): RiskTier
    {
        if ($totalOrders === 0) {
            return RiskTier::Unknown;
        }

        if ($totalOrders < 3) {
            return RiskTier::Caution;
        }

        return match (true) {
            $riskScore <= 30 => RiskTier::Safe,
            $riskScore <= 60 => RiskTier::Caution,
            default => RiskTier::Risky,
        };
    }

    private function parseRate(string $formattedRate): float
    {
        if (! preg_match('/(\d+)/', $formattedRate, $matches)) {
            return 0.0;
        }

        return ((int) $matches[1]) / 100;
    }

    private function orderFrequencyDays(int $platformCustomerId, int $totalOrders): ?float
    {
        if ($totalOrders < 2) {
            return null;
        }

        $first = PlatformOrder::query()
            ->where('platform_customer_id', $platformCustomerId)
            ->min('created_at');
        $last = PlatformOrder::query()
            ->where('platform_customer_id', $platformCustomerId)
            ->max('created_at');

        if (! $first || ! $last) {
            return null;
        }

        $spanDays = max(1, now()->parse($first)->diffInDays(now()->parse($last)));

        return round($spanDays / max(1, $totalOrders - 1), 1);
    }

    private function recencyDays(?string $lastOrderAt): ?int
    {
        if ($lastOrderAt === null) {
            return null;
        }

        return (int) now()->parse($lastOrderAt)->diffInDays(now());
    }
}
