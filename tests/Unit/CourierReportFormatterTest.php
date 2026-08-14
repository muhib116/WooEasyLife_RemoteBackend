<?php

use App\Services\FraudCheck\CourierReportFormatter;

it('parses pathao delivery counts from customer object', function () {
    $report = CourierReportFormatter::fromPathao([
        'data' => [
            'show_count' => true,
            'customer' => [
                'total_delivery' => 12,
                'successful_delivery' => 9,
                'failed_delivery' => 3,
            ],
        ],
    ]);

    expect($report['total_order'])->toBe(12);
    expect($report['confirmed'])->toBe(9);
    expect($report['cancel'])->toBe(3);
    expect($report['success_rate'])->toBe('75%');
    expect($report['data_type'])->toBe('delivery');
});

it('parses pathao delivery counts when only total and success are provided', function () {
    $report = CourierReportFormatter::fromPathao([
        'data' => [
            'show_count' => true,
            'customer' => [
                'total_delivery' => 10,
                'successful_delivery' => 8,
            ],
        ],
    ]);

    expect($report['confirmed'])->toBe(8);
    expect($report['cancel'])->toBe(2);
});

it('parses pathao rating-only responses without fabricating counts', function () {
    $report = CourierReportFormatter::fromPathao([
        'data' => [
            'version' => 'v2',
            'show_count' => false,
            'customer_rating' => 'good_customer',
        ],
    ]);

    expect($report['total_order'])->toBe(0);
    expect($report['confirmed'])->toBe(0);
    expect($report['cancel'])->toBe(0);
    expect($report['data_type'])->toBe('rating');
    expect($report['success_rate'])->toBe('Good Customer');
    expect($report['estimated_success_rate'])->toBe('85%');
});

it('exposes helpers for delivery counts vs rating-only pathao rows', function () {
    $delivery = CourierReportFormatter::fromPathao([
        'data' => [
            'show_count' => true,
            'customer' => [
                'total_delivery' => 10,
                'successful_delivery' => 9,
                'failed_delivery' => 1,
            ],
        ],
    ]);

    $rating = CourierReportFormatter::fromPathao([
        'data' => [
            'show_count' => false,
            'customer_rating' => 'good_customer',
        ],
    ]);

    expect(CourierReportFormatter::hasDeliveryCounts($delivery))->toBeTrue()
        ->and(CourierReportFormatter::isRatingOnly($delivery))->toBeFalse()
        ->and(CourierReportFormatter::hasDeliveryCounts($rating))->toBeFalse()
        ->and(CourierReportFormatter::isRatingOnly($rating))->toBeTrue();
});

it('parses steadfast and paperfly payloads into combined totals', function () {
    $steadfast = CourierReportFormatter::fromSteadfast([
        'total_delivered' => 7,
        'total_cancelled' => 2,
        'frauds' => [],
    ]);

    $pathao = CourierReportFormatter::fromPathao([
        'data' => [
            'show_count' => true,
            'customer' => [
                'total_delivery' => 10,
                'successful_delivery' => 8,
            ],
        ],
    ]);

    $paperfly = CourierReportFormatter::fromPaperfly([
        'records' => [
            ['delivered' => 3, 'returned' => 1],
            ['delivered' => 2, 'returned' => 0],
        ],
    ]);

    $totals = CourierReportFormatter::aggregateTotals($steadfast, $pathao, $paperfly);

    expect($totals['total_order'])->toBe(25);
    expect($totals['confirmed'])->toBe(20);
    expect($totals['cancel'])->toBe(5);
});

it('excludes pathao rating-only data from combined totals', function () {
    $steadfast = CourierReportFormatter::fromSteadfast([
        'total_delivered' => 7,
        'total_cancelled' => 2,
    ]);

    $pathao = CourierReportFormatter::fromPathao([
        'data' => [
            'show_count' => false,
            'customer_rating' => 'good_customer',
        ],
    ]);

    $totals = CourierReportFormatter::aggregateTotals($steadfast, $pathao);

    expect($totals['total_order'])->toBe(9);
    expect($totals['confirmed'])->toBe(7);
    expect($totals['cancel'])->toBe(2);
});
