<?php

namespace App\Console\Commands;

use App\WiseAi\Knowledge\Search\DatabaseKnowledgeSearchDriver;
use App\WiseAi\Knowledge\Search\KnowledgeSearchManager;
use Illuminate\Console\Command;

class WiseKnowledgeReindexCommand extends Command
{
    protected $signature = 'wise:knowledge-reindex';

    protected $description = 'Reindex published Wise knowledge into the configured search driver (no-op for database)';

    public function handle(KnowledgeSearchManager $search): int
    {
        $driver = $search->driver();
        if ($driver instanceof DatabaseKnowledgeSearchDriver) {
            $this->info('Driver is database — nothing to reindex.');

            return self::SUCCESS;
        }

        $count = $search->reindexAll();
        $this->info("Reindexed {$count} published knowledge document(s).");

        return self::SUCCESS;
    }
}
