<?php

namespace App\Console\Commands;

use App\Services\Messenger\MessengerForwardRetryService;
use Illuminate\Console\Command;

class RetryMessengerWebhookForwards extends Command
{
    protected $signature = 'messenger:retry-webhook-forwards {--limit=25 : Maximum retries to process per run}';

    protected $description = 'Retry failed WordPress Messenger webhook forwards';

    public function handle(MessengerForwardRetryService $retryService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $retryService->processDueRetries($limit);

        $this->info(sprintf(
            'Processed %d Messenger retries (%d succeeded, %d failed/pending).',
            $result['processed'],
            $result['succeeded'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
