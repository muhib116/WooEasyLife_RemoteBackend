<?php

namespace App\Jobs;

use App\Models\MessengerPageConnection;
use App\Services\Messenger\MessengerForwardRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMessengerInboundForward implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function __construct(
        public int $connectionId,
        public array $events
    ) {
    }

    public function handle(MessengerForwardRetryService $retryService): void
    {
        $connection = MessengerPageConnection::query()->find($this->connectionId);
        if (! $connection || $connection->status !== 'connected') {
            return;
        }

        $result = $retryService->forwardNow($connection, $this->events, true);
        if (! empty($result['success'])) {
            return;
        }

        $error = (string) ($result['message'] ?? 'forward_failed');
        Log::warning('Messenger inbound forward failed; queueing retry', [
            'connection_id' => $connection->id,
            'page_id' => $connection->page_id,
            'error' => $error,
        ]);

        $retryService->queueRetry($connection, $this->events, $error);
    }

    public function failed(?Throwable $exception): void
    {
        try {
            $connection = MessengerPageConnection::query()->find($this->connectionId);
            if (! $connection) {
                return;
            }

            /** @var MessengerForwardRetryService $retryService */
            $retryService = app(MessengerForwardRetryService::class);
            $retryService->queueRetry(
                $connection,
                $this->events,
                $exception?->getMessage() ?: 'job_failed'
            );
        } catch (Throwable $inner) {
            Log::error('Messenger inbound forward failed() could not queue retry', [
                'connection_id' => $this->connectionId,
                'message' => $inner->getMessage(),
            ]);
        }
    }
}
