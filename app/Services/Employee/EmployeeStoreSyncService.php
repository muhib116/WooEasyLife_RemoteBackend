<?php

namespace App\Services\Employee;

use App\Models\MerchantEmployee;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class EmployeeStoreSyncService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $lastStoreSync = [];

    public function __construct(
        protected EmployeeStoreTargetResolver $targetResolver,
        protected WordPressEmployeeForwarder $forwarder,
        protected EmployeeStoreSyncExecutor $executor,
        protected EmployeeStoreSyncLogger $logger
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pullLastStoreSync(): array
    {
        return $this->lastStoreSync;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function recentUnresolvedFailures(User $merchant, int $limit = 10)
    {
        return $this->logger->recentUnresolvedFailures($merchant, $limit);
    }

    /**
     * @param  array<int, int>  $websiteIds
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertEmailAvailableOnStores(
        User $merchant,
        string $email,
        array $websiteIds,
        int $employeeId = 0
    ): void {
        $email = trim($email);

        if ($email === '' || $websiteIds === []) {
            return;
        }

        $failures = [];
        $targets = $this->targetResolver->resolveTargets($merchant, $websiteIds);

        foreach ($websiteIds as $websiteId) {
            $target = $targets->firstWhere('website_id', (int) $websiteId);

            if (! $target) {
                continue;
            }

            $response = $this->forwarder->validateEmail(
                $target['site_urls'],
                $target['access_token'],
                [
                    'email' => $email,
                    'employee_id' => $employeeId,
                ]
            );

            if (! ($response['success'] ?? false)) {
                $domain = (string) ($target['domain'] ?? "website #{$websiteId}");
                $message = (string) ($response['message'] ?? 'Email cannot be used on this store.');

                if ($this->isStoreReachabilityFailure($message)) {
                    Log::warning('Skipping WordPress email validation because the store is unreachable.', [
                        'merchant_user_id' => $merchant->id,
                        'website_id' => $websiteId,
                        'domain' => $domain,
                        'message' => $message,
                    ]);

                    continue;
                }

                $failures[] = "{$domain}: {$message}";
            }
        }

        if ($failures !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => implode(' ', $failures),
            ]);
        }
    }

    private function isStoreReachabilityFailure(string $message): bool
    {
        return in_array($message, [
            'forward_exception',
            'forward_failed',
            'missing_site_url',
            'missing_access_token',
            'invalid_access_token',
        ], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncAfterCreate(User $merchant, MerchantEmployee $employee): array
    {
        $employee = $employee->fresh(['websites']);

        if (! $employee || ! $employee->status) {
            return $this->rememberStoreSync([]);
        }

        $websiteIds = $this->targetResolver->resolveEffectiveWebsiteIds($merchant, $employee);

        return $this->rememberStoreSync(
            $this->syncOnWebsites($merchant, $employee, $websiteIds)
        );
    }

    /**
     * @param  array{
     *     before_website_ids: array<int, int>,
     *     before_email: ?string,
     *     before_phone: ?string,
     *     before_name: ?string,
     *     before_status: bool,
     * }  $context
     * @return array<int, array<string, mixed>>
     */
    public function syncAfterUpdate(User $merchant, MerchantEmployee $employee, array $context): array
    {
        $employee = $employee->fresh(['websites']);

        if (! $employee) {
            return $this->rememberStoreSync([]);
        }

        $beforeWebsiteIds = $this->normalizeWebsiteIds($context['before_website_ids'] ?? []);
        $afterWebsiteIds = $this->targetResolver->resolveEffectiveWebsiteIds($merchant, $employee);

        $removedWebsiteIds = array_values(array_diff($beforeWebsiteIds, $afterWebsiteIds));
        $addedWebsiteIds = array_values(array_diff($afterWebsiteIds, $beforeWebsiteIds));

        $wasActive = (bool) ($context['before_status'] ?? true);
        $isActive = (bool) $employee->status;

        if ($wasActive && ! $isActive) {
            return $this->rememberStoreSync(
                $this->deleteOnWebsites(
                    $merchant,
                    $employee,
                    $beforeWebsiteIds,
                    trim((string) ($context['before_email'] ?? $employee->email ?? ''))
                )
            );
        }

        $contactChanged = trim((string) ($context['before_name'] ?? '')) !== trim((string) ($employee->name ?? ''))
            || trim((string) ($context['before_email'] ?? '')) !== trim((string) ($employee->email ?? ''))
            || trim((string) ($context['before_phone'] ?? '')) !== trim((string) ($employee->phone ?? ''))
            || $wasActive !== $isActive;

        $results = [];

        if ($removedWebsiteIds !== []) {
            $results = array_merge(
                $results,
                $this->deleteOnWebsites(
                    $merchant,
                    $employee,
                    $removedWebsiteIds,
                    trim((string) ($context['before_email'] ?? $employee->email ?? ''))
                )
            );
        }

        $syncWebsiteIds = $addedWebsiteIds;

        if ($contactChanged) {
            $stillAssigned = array_values(array_intersect($beforeWebsiteIds, $afterWebsiteIds));

            foreach ($stillAssigned as $websiteId) {
                if (! in_array($websiteId, $syncWebsiteIds, true)) {
                    $syncWebsiteIds[] = $websiteId;
                }
            }
        }

        if ($employee->status && $syncWebsiteIds !== []) {
            $results = array_merge(
                $results,
                $this->syncOnWebsites($merchant, $employee, $syncWebsiteIds)
            );
        }

        return $this->rememberStoreSync($results);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deleteOnAllAssignedStores(User $merchant, MerchantEmployee $employee): array
    {
        $employee->loadMissing('websites');

        $websiteIds = $this->targetResolver->resolveEffectiveWebsiteIds($merchant, $employee);

        return $this->rememberStoreSync(
            $this->deleteOnWebsites(
                $merchant,
                $employee,
                $websiteIds,
                trim((string) ($employee->email ?? ''))
            )
        );
    }

    /**
     * @param  array<int, int>  $websiteIds
     * @return array<int, array<string, mixed>>
     */
    private function syncOnWebsites(User $merchant, MerchantEmployee $employee, array $websiteIds): array
    {
        if ($websiteIds === []) {
            return [];
        }

        $results = [];

        foreach ($websiteIds as $websiteId) {
            $result = $this->executor->syncOnWebsite($merchant, $employee, (int) $websiteId);
            $results[] = $result;
            $this->logger->record($merchant, $employee, $result);
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $websiteIds
     * @return array<int, array<string, mixed>>
     */
    private function deleteOnWebsites(
        User $merchant,
        MerchantEmployee $employee,
        array $websiteIds,
        string $email
    ): array {
        if ($websiteIds === []) {
            return [];
        }

        $results = [];

        foreach ($websiteIds as $websiteId) {
            $result = $this->executor->deleteOnWebsite($merchant, $employee, (int) $websiteId, $email);
            $results[] = $result;
            $this->logger->record($merchant, $employee, $result);
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function rememberStoreSync(array $results): array
    {
        $this->lastStoreSync = $results;

        return $results;
    }

    /**
     * @param  array<int, int>  $websiteIds
     * @return array<int, int>
     */
    private function normalizeWebsiteIds(array $websiteIds): array
    {
        return collect($websiteIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
