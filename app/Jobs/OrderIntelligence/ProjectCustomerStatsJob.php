<?php

namespace App\Jobs\OrderIntelligence;

use App\Services\OrderIntelligence\Search\CustomerSearchIndexer;
use App\Services\OrderIntelligence\StatsProjector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProjectCustomerStatsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $platformCustomerId,
    ) {}

    public function handle(StatsProjector $projector, CustomerSearchIndexer $indexer): void
    {
        $projector->project($this->platformCustomerId);
        $indexer->indexCustomer($this->platformCustomerId);
    }
}
