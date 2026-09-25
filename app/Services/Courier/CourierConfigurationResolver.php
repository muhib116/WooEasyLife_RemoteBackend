<?php

namespace App\Services\Courier;

use App\Models\CourierConfiguration;

class CourierConfigurationResolver
{
    /**
     * The row Courier Settings shows for this merchant and partner.
     * One row is unchanged. When older duplicate rows exist, the newest one is used
     * so booking cannot keep sending parcels with a replaced API key.
     */
    public function forUser(int $userId, string $slug): ?CourierConfiguration
    {
        if ($userId <= 0) {
            return null;
        }

        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        return CourierConfiguration::query()
            ->where('user_id', $userId)
            ->where('slug', $slug)
            ->orderByDesc('id')
            ->first();
    }
}
