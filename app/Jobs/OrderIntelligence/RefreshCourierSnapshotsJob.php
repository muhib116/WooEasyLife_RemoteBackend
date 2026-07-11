<?php

namespace App\Jobs\OrderIntelligence;

use App\Services\OrderIntelligence\CourierSnapshotRefresher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshCourierSnapshotsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 900;

    /**
     * @param  list<string>|null  $onlyCouriers
     */
    public function __construct(
        public string $phoneNormalized,
        public ?int $accessTokenId = null,
        public ?array $onlyCouriers = null,
    ) {
        $this->uniqueFor = max(60, (int) config('order_intelligence.fraud_check.refresh_unique_for_seconds', 900));
    }

    public function uniqueId(): string
    {
        $couriers = $this->onlyCouriers ?? ['*'];
        $normalized = array_values(array_unique(array_map(
            fn ($courier) => strtolower(trim((string) $courier)),
            $couriers,
        )));
        sort($normalized);

        return $this->phoneNormalized.':'.implode(',', $normalized);
    }

    public function handle(CourierSnapshotRefresher $refresher): void
    {
        $refresher->refresh($this->phoneNormalized, $this->accessTokenId, $this->onlyCouriers);
    }
}
