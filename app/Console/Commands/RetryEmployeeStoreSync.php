<?php

namespace App\Console\Commands;

use App\Models\EmployeeStoreSyncLog;
use App\Services\Employee\EmployeeStoreSyncRetryService;
use Illuminate\Console\Command;

class RetryEmployeeStoreSync extends Command
{
    protected $signature = 'employee:retry-store-sync
                            {log : employee_store_sync_logs.id}
                            {--inspect : Show the stored log details without retrying}';

    protected $description = 'Inspect or manually retry a failed employee WordPress store sync';

    public function handle(EmployeeStoreSyncRetryService $retryService): int
    {
        $log = EmployeeStoreSyncLog::query()
            ->with(['employee:id,name', 'website:id,domain'])
            ->find((int) $this->argument('log'));

        if (! $log instanceof EmployeeStoreSyncLog) {
            $this->error('Sync log not found.');

            return self::FAILURE;
        }

        $this->line('Sync log #'.$log->id);
        $this->line('Employee: '.($log->employee?->name ?? '#'.$log->merchant_employee_id));
        $this->line('Website: '.($log->domain ?: $log->website?->domain ?: '#'.$log->website_id));
        $this->line('Action: '.$log->action);
        $this->line('Success: '.($log->success ? 'yes' : 'no'));
        $this->line('Message: '.($log->message ?: '(empty)'));
        $this->line('HTTP status: '.($log->http_status ?? 'n/a'));
        $this->line('Attempts: '.$log->attempt_count.' / '.$log->max_attempts);
        $this->line('Retry scheduled: '.($log->retry_scheduled ? 'yes' : 'no'));
        $this->line('Last attempted: '.($log->last_attempted_at?->toDateTimeString() ?? 'n/a'));

        if (is_array($log->payload) && $log->payload !== []) {
            $this->line('Payload: '.json_encode($log->payload, JSON_UNESCAPED_SLASHES));
        }

        if ($this->option('inspect')) {
            $this->comment('Inspect only. Check storage/logs/laravel.log for "Employee WordPress forward" entries.');

            return self::SUCCESS;
        }

        if ($log->success || $log->resolved_at !== null) {
            $this->info('This sync log is already resolved.');

            return self::SUCCESS;
        }

        if ($log->attempt_count >= $log->max_attempts) {
            $this->error('Retry limit reached. Re-save the employee in the hub to create a new sync attempt.');

            return self::FAILURE;
        }

        $retryService->process((int) $log->id);
        $log->refresh();

        $this->newLine();
        $this->info('Retry finished.');
        $this->line('Success: '.($log->success ? 'yes' : 'no'));
        $this->line('Message: '.($log->message ?: '(empty)'));
        $this->line('HTTP status: '.($log->http_status ?? 'n/a'));
        $this->line('Attempts: '.$log->attempt_count.' / '.$log->max_attempts);
        $this->line('Retry scheduled: '.($log->retry_scheduled ? 'yes' : 'no'));

        if (! $log->success) {
            $this->comment('See storage/logs/laravel.log for the full HTTP response body.');
        }

        return $log->success ? self::SUCCESS : self::FAILURE;
    }
}
