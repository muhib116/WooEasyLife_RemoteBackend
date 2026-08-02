<?php

namespace App\Console\Commands;

use App\WiseAi\Language\DiscoveryRanker;
use Illuminate\Console\Command;

class WiseDiscoveryRankCommand extends Command
{
    protected $signature = 'wise:discovery-rank
        {--open : Recompute ranks for top open tokens (not only dirty queue)}
        {--limit=500 : Max open tokens when using --open}';

    protected $description = 'Flush queued Discovery Queue rank refreshes (or recompute open queue)';

    public function handle(DiscoveryRanker $ranker): int
    {
        if ($this->option('open')) {
            $n = $ranker->refreshOpen((int) $this->option('limit'));
            $this->info("Recomputed ranks for {$n} open token(s).");

            return self::SUCCESS;
        }

        $n = $ranker->flushQueued();
        $this->info("Flushed ranks for {$n} dirty token(s).");

        return self::SUCCESS;
    }
}
