<?php

namespace App\Jobs\Employee;

use App\Services\Employee\EmployeeStoreSyncRetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryEmployeeStoreSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $logId
    ) {
    }

    public function handle(EmployeeStoreSyncRetryService $retryService): void
    {
        $retryService->process($this->logId);
    }
}
