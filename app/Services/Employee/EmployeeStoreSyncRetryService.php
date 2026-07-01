<?php

namespace App\Services\Employee;

use App\Models\EmployeeStoreSyncLog;
use App\Models\MerchantEmployee;
use App\Models\User;

class EmployeeStoreSyncRetryService
{
    public function __construct(
        protected EmployeeStoreSyncExecutor $executor,
        protected EmployeeStoreSyncLogger $logger
    ) {
    }

    public function process(int $logId): void
    {
        $log = EmployeeStoreSyncLog::query()->find($logId);

        if (! $log instanceof EmployeeStoreSyncLog) {
            return;
        }

        if ($log->success || $log->resolved_at !== null) {
            return;
        }

        if ($log->attempt_count >= $log->max_attempts) {
            return;
        }

        $merchant = User::query()->find($log->merchant_user_id);
        $employee = MerchantEmployee::query()->find($log->merchant_employee_id);

        if (! $merchant || ! $employee) {
            return;
        }

        $email = is_array($log->payload) ? (string) ($log->payload['email'] ?? '') : '';

        $result = $log->action === 'delete'
            ? $this->executor->deleteOnWebsite($merchant, $employee, (int) $log->website_id, $email)
            : $this->executor->syncOnWebsite($merchant, $employee, (int) $log->website_id);

        $this->logger->updateFromRetry($log, $result);
    }
}
