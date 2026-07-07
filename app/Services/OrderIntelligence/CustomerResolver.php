<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformCustomerStats;
use App\Services\FraudCheckService;
use Carbon\CarbonInterface;

class CustomerResolver
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
    ) {}

    public function resolve(string $phone, ?string $name = null, ?string $address = null): PlatformCustomer
    {
        $phoneNormalized = $this->fraudCheckService->normalizePhone($phone);
        $now = now();

        $customer = PlatformCustomer::query()->firstOrNew([
            'phone_normalized' => $phoneNormalized,
        ]);

        if (! $customer->exists) {
            $customer->first_seen_at = $now;
        }

        $customer->last_seen_at = $now;

        if ($name !== null && $name !== '') {
            $customer->latest_name = $name;
        }

        if ($address !== null && $address !== '') {
            $customer->latest_address = $address;
        }

        $customer->save();

        $this->ensureStatsRow($customer, $phoneNormalized, $now);

        return $customer;
    }

    private function ensureStatsRow(PlatformCustomer $customer, string $phoneNormalized, CarbonInterface $now): void
    {
        PlatformCustomerStats::query()->firstOrCreate(
            ['platform_customer_id' => $customer->id],
            [
                'phone_normalized' => $phoneNormalized,
                'counts' => OrderStatus::defaultCounts(),
                'rates' => $this->emptyRates(),
                'stats_computed_at' => $now,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function emptyRates(): array
    {
        return [
            'confirmation_rate' => '0%',
            'delivery_rate' => '0%',
            'return_rate' => '0%',
            'cancel_rate' => '0%',
        ];
    }
}
