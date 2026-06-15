<?php

namespace App\Console\Commands;

use App\Services\Courier\CourierForwardRetryService;
use Illuminate\Console\Command;

class RetryCourierWebhookForwards extends Command
{
    protected $signature = 'courier:retry-webhook-forwards {--limit=25 : Maximum retries to process per run}';

    protected $description = 'Retry failed WooCommerce courier webhook forwards';

    public function handle(CourierForwardRetryService $retryService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $retryService->processDueRetries($limit);

        $this->info(sprintf(
            'Processed %d retries (%d succeeded, %d failed/pending).',
            $result['processed'],
            $result['succeeded'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
