<?php

namespace App\Services\FraudCheck;

use App\Models\OrderIntelligence\CourierCustomerSnapshot;

/**
 * RedX's customer-success endpoint currently ignores the phone and returns
 * this one payload for every number. It is not that customer's history.
 */
class RedxStaticSample
{
    public const MESSAGE = 'RedX did not return this number\'s delivery history.';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function matchesApi(array $data): bool
    {
        return (int) ($data['totalParcels'] ?? -1) === 10
            && (int) ($data['deliveredParcels'] ?? -1) === 6
            && (int) ($data['successRate'] ?? -1) === 60
            && (string) ($data['returnPercentage'] ?? '') === '4'
            && (string) ($data['customerSegment'] ?? '') === 'Normal Customer';
    }

    public static function matchesSnapshot(CourierCustomerSnapshot $snapshot): bool
    {
        if ($snapshot->courier !== 'redx') {
            return false;
        }

        if ((int) $snapshot->total_order !== 10
            || (int) $snapshot->confirmed !== 6
            || (int) $snapshot->cancel !== 4
            || (string) $snapshot->success_rate !== '60%') {
            return false;
        }

        $raw = is_array($snapshot->raw_report) ? $snapshot->raw_report : [];

        return (string) ($raw['customer_segment'] ?? '') === 'Normal Customer'
            && (string) ($raw['return_percentage'] ?? '') === '4';
    }
}
