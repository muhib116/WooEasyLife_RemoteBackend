<?php

namespace App\Jobs\OrderIntelligence;

use App\Services\OrderIntelligence\Search\CustomerSearchIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexCustomerSearchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $platformCustomerId,
    ) {}

    public function handle(CustomerSearchIndexer $indexer): void
    {
        $indexer->indexCustomer($this->platformCustomerId);
    }
}
