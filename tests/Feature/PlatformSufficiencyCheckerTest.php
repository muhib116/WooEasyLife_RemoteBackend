<?php

use App\Services\OrderIntelligence\PlatformSufficiencyChecker;

it('flags rating-only pathao snapshots for refresh so plugin can upgrade to counts', function () {
    config()->set('fraud_check.include_redx', false);
    config()->set('fraud_check.include_carrybee', false);
    config()->set('order_intelligence.fraud_check.max_snapshot_staleness_hours', 24);

    $checker = new PlatformSufficiencyChecker();

    $platformData = [
        'courier_stats' => [
            [
                'courier' => 'steadfast',
                'total_order' => 50,
                'confirmed' => 40,
                'cancel' => 10,
                'fetched_at' => now()->toIso8601String(),
            ],
            [
                'courier' => 'pathao',
                'total_order' => 0,
                'confirmed' => 0,
                'cancel' => 0,
                'customer_rating' => 'good_customer',
                'data_type' => 'rating',
                'fetched_at' => now()->toIso8601String(),
            ],
            [
                'courier' => 'paperfly',
                'total_order' => 0,
                'confirmed' => 0,
                'cancel' => 0,
                'fetched_at' => now()->toIso8601String(),
            ],
        ],
    ];

    expect($checker->couriersNeedingRefresh($platformData))->toContain('pathao');
});
