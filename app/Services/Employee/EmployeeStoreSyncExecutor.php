<?php

namespace App\Services\Employee;

use App\Models\MerchantEmployee;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteUrlResolver;

class EmployeeStoreSyncExecutor
{
    public function __construct(
        protected EmployeeStoreTargetResolver $targetResolver,
        protected WordPressEmployeeForwarder $forwarder,
        protected WebsiteUrlResolver $websiteUrlResolver
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function syncOnWebsite(User $merchant, MerchantEmployee $employee, int $websiteId): array
    {
        $employee->loadMissing('websites');

        if (! $employee->status) {
            return $this->buildResult($websiteId, 'sync', false, 'inactive_employee');
        }

        $email = trim((string) ($employee->email ?? ''));

        if ($email === '') {
            return $this->buildResult($websiteId, 'sync', false, 'missing_email');
        }

        $phone = trim((string) ($employee->phone ?? ''));

        if ($phone === '') {
            return $this->buildResult($websiteId, 'sync', false, 'missing_phone');
        }

        $target = $this->targetResolver
            ->resolveTargets($merchant, [$websiteId])
            ->firstWhere('website_id', $websiteId);

        if (! $target) {
            return $this->buildMissingTargetResult($merchant, $websiteId, 'sync');
        }

        $displayUrl = $target['site_urls'][0] ?? null;
        $pivotWebsiteIds = $employee->websites->pluck('id')->map(fn ($id) => (int) $id)->all();
        $payloadWebsiteIds = $pivotWebsiteIds !== [] ? $pivotWebsiteIds : [];

        $response = $this->forwarder->sync(
            $target['site_urls'],
            $target['access_token'],
            [
                'employee_id' => (int) $employee->id,
                'name' => (string) $employee->name,
                'email' => $email,
                'phone' => $phone,
                'website_ids' => $payloadWebsiteIds,
                'current_website_id' => $target['website_id'],
                'status' => (bool) $employee->status,
            ]
        );

        return $this->buildResult(
            $websiteId,
            'sync',
            (bool) ($response['success'] ?? false),
            (string) ($response['message'] ?? ''),
            $target['domain'] ?? null,
            $response['http_status'] ?? null,
            is_array($response['data'] ?? null) ? $response['data'] : [],
            $displayUrl
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteOnWebsite(
        User $merchant,
        MerchantEmployee $employee,
        int $websiteId,
        ?string $email = null
    ): array {
        $target = $this->targetResolver
            ->resolveTargets($merchant, [$websiteId])
            ->firstWhere('website_id', $websiteId);

        if (! $target) {
            return $this->buildMissingTargetResult($merchant, $websiteId, 'delete');
        }

        $email = trim((string) ($email ?? $employee->email ?? ''));
        $displayUrl = $target['site_urls'][0] ?? null;

        $response = $this->forwarder->delete(
            $target['site_urls'],
            $target['access_token'],
            [
                'employee_id' => (int) $employee->id,
                'email' => $email,
                'website_ids' => [],
                'current_website_id' => $target['website_id'],
            ]
        );

        return $this->buildResult(
            $websiteId,
            'delete',
            (bool) ($response['success'] ?? false),
            (string) ($response['message'] ?? ''),
            $target['domain'] ?? null,
            $response['http_status'] ?? null,
            is_array($response['data'] ?? null) ? $response['data'] : [],
            $displayUrl
        );
    }

    private function buildMissingTargetResult(User $merchant, int $websiteId, string $action): array
    {
        $website = Website::query()
            ->where('user_id', $merchant->id)
            ->find($websiteId);

        $domain = trim((string) ($website?->domain ?? ''));
        $displayUrl = $website
            ? ($this->websiteUrlResolver->siteUrlCandidates($website)[0] ?? null)
            : null;

        return $this->buildResult(
            $websiteId,
            $action,
            false,
            'missing_store_target',
            $domain !== '' ? $domain : null,
            null,
            [],
            $displayUrl
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildResult(
        int $websiteId,
        string $action,
        bool $success,
        string $message,
        ?string $domain = null,
        ?int $httpStatus = null,
        array $data = [],
        ?string $displayUrl = null
    ): array {
        return [
            'website_id' => $websiteId,
            'domain' => $domain,
            'display_url' => $displayUrl,
            'action' => $action,
            'success' => $success,
            'message' => $message,
            'http_status' => $httpStatus,
            'data' => $data,
        ];
    }
}
