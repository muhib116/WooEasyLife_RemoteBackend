<?php

use App\Services\FraudCheck\SteadfastCurlExporter;

it('accepts steadfast payloads that only include delivered and cancelled fields', function () {
    $payload = json_encode([
        'delivered' => 0,
        'cancelled' => 0,
    ]);

    expect(SteadfastCurlExporter::isValid($payload))->toBeTrue();
});

it('accepts empty fraud arrays in steadfast payloads', function () {
    $payload = json_encode([
        'total_delivered' => 0,
        'total_cancelled' => 0,
        'frauds' => [],
    ]);

    expect(SteadfastCurlExporter::isValid($payload))->toBeTrue();
});

it('rejects non-json steadfast responses', function () {
    expect(SteadfastCurlExporter::isValid('<html>login</html>'))->toBeFalse();
});
