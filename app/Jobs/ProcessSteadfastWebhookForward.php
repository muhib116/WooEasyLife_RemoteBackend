<?php

namespace App\Jobs;

use App\Services\Courier\SteadfastWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSteadfastWebhookForward implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $eventId) {}

    public function handle(SteadfastWebhookProcessor $processor): void
    {
        $processor->process($this->eventId);
    }
}
