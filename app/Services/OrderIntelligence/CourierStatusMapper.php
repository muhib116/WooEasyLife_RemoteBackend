<?php

namespace App\Services\OrderIntelligence;

use App\Domain\OrderIntelligence\OrderStatus;

class CourierStatusMapper
{
    public function map(string $partner, string $rawStatus): ?OrderStatus
    {
        $partner = strtolower(trim($partner));
        $normalized = strtolower(trim(str_replace('.', '_', $rawStatus)));

        $mapped = config("order_intelligence.courier_status_map.{$partner}.{$normalized}")
            ?? config("order_intelligence.courier_status_map.{$partner}.{$rawStatus}");

        if ($mapped === null) {
            return null;
        }

        return OrderStatus::tryFrom((string) $mapped);
    }
}
