<?php

namespace App\Console\Commands;

use App\Services\Courier\CourierForwardRetryService;
use App\Services\Courier\SteadfastWebhookProcessor;
use Illuminate\Console\Command;

class RetryCourierWebhookForwards extends Command
{
    protected $signature = 'courier:retry-webhook-forwards {--limit=25 : Maximum retries to process per run}';

    protected $description = 'Retry failed WooCommerce courier webhook forwards';

    public function handle(CourierForwardRetryService $retryService, SteadfastWebhookProcessor $steadfastWebhooks): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $budgetSeconds = 20;
        $started = microtime(true);
        $recovered = $steadfastWebhooks->recoverStuck($limit, $budgetSeconds);
        $remaining = max(1, $budgetSeconds - (int) floor(microtime(true) - $started));
        $result = $retryService->processDueRetries($limit, $remaining);

        $this->info(sprintf(
            'Reset %d stuck Steadfast forwards, claimed %d queued Steadfast events. Processed %d retries (%d succeeded, %d failed/pending).',
            $recovered['reset'],
            $recovered['processed'],
            $result['processed'],
            $result['succeeded'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
