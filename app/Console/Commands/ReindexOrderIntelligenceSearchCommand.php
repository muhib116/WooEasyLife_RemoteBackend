<?php

namespace App\Console\Commands;

use App\Services\OrderIntelligence\Search\CustomerSearchIndexer;
use Illuminate\Console\Command;

class ReindexOrderIntelligenceSearchCommand extends Command
{
    protected $signature = 'order-intelligence:reindex-search {--chunk=500 : Records per batch}';

    protected $description = 'Rebuild the order intelligence customer search index';

    public function handle(CustomerSearchIndexer $indexer): int
    {
        if (! config('order_intelligence.enabled', true)) {
            $this->warn('Order intelligence is disabled.');

            return self::FAILURE;
        }

        $chunk = max(100, (int) $this->option('chunk'));
        $this->info('Reindexing customer search (chunk: ' . $chunk . ')...');

        $indexed = $indexer->reindexAll($chunk);

        $this->info("Indexed {$indexed} customers.");

        return self::SUCCESS;
    }
}
