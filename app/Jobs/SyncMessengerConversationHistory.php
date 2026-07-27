<?php

namespace App\Jobs;

use App\Models\MessengerPageConnection;
use App\Services\Messenger\MessengerConversationHistorySync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMessengerConversationHistory implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    /**
     * @param  array{max_conversations?:int,max_messages_per_conversation?:int}  $options
     */
    public function __construct(
        public int $connectionId,
        public array $options = []
    ) {
    }

    public function uniqueId(): string
    {
        return 'messenger-history-sync:' . $this->connectionId;
    }

    public function handle(MessengerConversationHistorySync $sync): void
    {
        $connection = MessengerPageConnection::query()->find($this->connectionId);
        if (! $connection || $connection->status !== 'connected') {
            return;
        }

        $result = $sync->sync($connection, $this->options);
        Log::info('Messenger conversation history sync finished', [
            'connection_id' => $connection->id,
            'page_id' => $connection->page_id,
            'result' => $result,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Messenger conversation history sync job failed', [
            'connection_id' => $this->connectionId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
