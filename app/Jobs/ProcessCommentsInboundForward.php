<?php

namespace App\Jobs;

use App\Models\MessengerPageConnection;
use App\Services\Messenger\WordPressMessengerForwarder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessCommentsInboundForward implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function __construct(
        public int $connectionId,
        public array $events
    ) {
    }

    public function handle(WordPressMessengerForwarder $forwarder): void
    {
        $connection = MessengerPageConnection::query()->find($this->connectionId);
        if (! $connection || $connection->status !== 'connected') {
            return;
        }

        try {
            $result = $forwarder->forwardCommentsInbound($connection, [
                'events' => $this->events,
                'source' => 'facebook_comment',
            ]);
            if (empty($result['success'])) {
                $message = (string) ($result['message'] ?? 'comments_forward_failed');
                Log::warning('Comments inbound forward failed; will retry', [
                    'connection_id' => $connection->id,
                    'page_id' => $connection->page_id,
                    'attempt' => $this->attempts(),
                    'result' => $result,
                ]);
                throw new RuntimeException($message);
            }
        } catch (Throwable $exception) {
            Log::error('Comments inbound forward exception', [
                'connection_id' => $this->connectionId,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }
}
