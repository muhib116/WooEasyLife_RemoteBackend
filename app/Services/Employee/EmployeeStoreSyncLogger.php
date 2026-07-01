<?php

namespace App\Services\Employee;

use App\Jobs\Employee\RetryEmployeeStoreSyncJob;
use App\Models\EmployeeStoreSyncLog;
use App\Models\MerchantEmployee;
use App\Models\User;

class EmployeeStoreSyncLogger
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function record(User $merchant, MerchantEmployee $employee, array $result): EmployeeStoreSyncLog
    {
        $log = EmployeeStoreSyncLog::create([
            'merchant_user_id' => $merchant->id,
            'merchant_employee_id' => $employee->id,
            'website_id' => (int) ($result['website_id'] ?? 0),
            'domain' => isset($result['domain']) ? (string) $result['domain'] : null,
            'action' => (string) ($result['action'] ?? 'sync'),
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'http_status' => isset($result['http_status']) ? (int) $result['http_status'] : null,
            'attempt_count' => 1,
            'max_attempts' => EmployeeStoreSyncLog::MAX_ATTEMPTS,
            'retry_scheduled' => false,
            'payload' => $this->buildPayload($employee, $result),
            'last_attempted_at' => now(),
            'resolved_at' => ($result['success'] ?? false) ? now() : null,
        ]);

        $this->scheduleRetryIfNeeded($log);

        return $log;
    }

    public function scheduleRetryIfNeeded(EmployeeStoreSyncLog $log): void
    {
        if (! $log->canRetry()) {
            return;
        }

        $log->update(['retry_scheduled' => true]);

        $delaySeconds = match ($log->attempt_count) {
            1 => 60,
            2 => 300,
            default => 900,
        };

        RetryEmployeeStoreSyncJob::dispatch($log->id)
            ->delay(now()->addSeconds($delaySeconds));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function updateFromRetry(EmployeeStoreSyncLog $log, array $result): EmployeeStoreSyncLog
    {
        $log->update([
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'http_status' => isset($result['http_status']) ? (int) $result['http_status'] : null,
            'attempt_count' => $log->attempt_count + 1,
            'last_attempted_at' => now(),
            'resolved_at' => ($result['success'] ?? false) ? now() : null,
            'retry_scheduled' => false,
        ]);

        $log->refresh();

        if (! $log->success) {
            $this->scheduleRetryIfNeeded($log);
        }

        return $log;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function recentUnresolvedFailures(User $merchant, int $limit = 10)
    {
        return EmployeeStoreSyncLog::query()
            ->with(['employee:id,name', 'website:id,domain'])
            ->where('merchant_user_id', $merchant->id)
            ->where('success', false)
            ->whereNull('resolved_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (EmployeeStoreSyncLog $log) => [
                'id' => $log->id,
                'employee_id' => $log->merchant_employee_id,
                'employee_name' => $log->employee?->name,
                'website_id' => $log->website_id,
                'domain' => $log->domain ?: $log->website?->domain,
                'display_url' => is_array($log->payload) && ! empty($log->payload['display_url'])
                    ? (string) $log->payload['display_url']
                    : null,
                'action' => $log->action,
                'message' => $log->message,
                'http_status' => $log->http_status,
                'attempt_count' => $log->attempt_count,
                'max_attempts' => $log->max_attempts,
                'retry_scheduled' => $log->retry_scheduled,
                'last_attempted_at' => $log->last_attempted_at?->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function buildPayload(MerchantEmployee $employee, array $result): array
    {
        $payload = [
            'action' => (string) ($result['action'] ?? 'sync'),
        ];

        if (! empty($result['display_url'])) {
            $payload['display_url'] = (string) $result['display_url'];
        }

        if (($result['action'] ?? '') === 'delete') {
            $payload['email'] = (string) ($employee->email ?? '');
        }

        return $payload;
    }
}
