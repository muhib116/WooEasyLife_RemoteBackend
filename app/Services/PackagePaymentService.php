<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackagePaymentService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer,
        protected PlanAssignmentService $planAssignment,
        protected WebsiteSyncService $websiteSync,
        protected PackagePlanResolver $planResolver
    ) {
    }

    /**
     * @return Collection<int, PackageHub>
     */
    public function listActivePlans(): Collection
    {
        return PackageHub::query()
            ->where('is_active', true)
            ->orderBy('index')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRequest(User $user, array $data, ?AccessToken $accessToken = null): PackagePaymentRequest
    {
        $domain = $this->domainNormalizer->normalize($data['domain'] ?? $accessToken?->domain);
        if (! $domain) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        $packageHub = PackageHub::query()
            ->where('id', $data['package_hub_id'] ?? null)
            ->where('is_active', true)
            ->first();

        if (! $packageHub) {
            throw ValidationException::withMessages([
                'package_hub_id' => 'Invalid or inactive plan',
            ]);
        }

        $transactionCharge = round((float) ($data['transaction_charge'] ?? $data['total_charge'] ?? 0), 2);

        if ($this->planResolver->isCatalog($packageHub)) {
            [$orderLimit, $totalAmount] = $this->resolveCatalogPaymentAmounts($packageHub, $data);
        } else {
            [$orderLimit, $totalAmount] = $this->resolveLegacyPaymentAmounts($packageHub, $data);
        }

        $website = $this->websiteSync->resolveForUser($user, $domain);

        return PackagePaymentRequest::create([
            'user_id' => $user->id,
            'package_hub_id' => $packageHub->id,
            'website_id' => $website?->id,
            'domain' => $domain,
            'order_limit' => $orderLimit,
            'total_amount' => $totalAmount,
            'transaction_charge' => $transactionCharge,
            'transaction_method' => $data['transaction_method'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => 'pending',
            'created_by' => Auth::id() ?? $user->id,
        ]);
    }

    public function approve(PackagePaymentRequest $paymentRequest): UserPackage
    {
        if ($paymentRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending payment requests can be approved.',
            ]);
        }

        return DB::transaction(function () use ($paymentRequest) {
            $user = User::findOrFail($paymentRequest->user_id);
            $packageHub = PackageHub::findOrFail($paymentRequest->package_hub_id);

            $existing = $this->findActivePackageForDomain($user, $paymentRequest->domain);

            if ($existing) {
                $userPackage = $this->applyApprovedPaymentToExisting(
                    $existing,
                    $packageHub,
                    $paymentRequest
                );
            } else {
                $userPackage = $this->planAssignment->assign($user, $packageHub, [
                    'domain' => $paymentRequest->domain,
                    'limit' => $paymentRequest->order_limit,
                    'transaction_method' => $paymentRequest->transaction_method,
                    'transaction_id' => $paymentRequest->transaction_id,
                    'transaction_number' => $paymentRequest->account_number,
                    'transaction_charge' => $paymentRequest->transaction_charge,
                    'note' => $paymentRequest->note,
                ]);
            }

            $paymentRequest->update([
                'status' => 'approved',
                'user_package_id' => $userPackage->id,
                'updated_by' => Auth::id(),
            ]);

            return $userPackage->fresh();
        });
    }

    public function reject(PackagePaymentRequest $paymentRequest): PackagePaymentRequest
    {
        if ($paymentRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending payment requests can be rejected.',
            ]);
        }

        $paymentRequest->update([
            'status' => 'cancelled',
            'updated_by' => Auth::id(),
        ]);

        return $paymentRequest->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function billingSnapshot(User $user, AccessToken $accessToken): array
    {
        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (UserPackage $package) => $this->domainNormalizer->matches(
                $package->domain,
                $accessToken->domain
            ));

        $activePackage = $packages->sortByDesc('id')->first();
        $remainingOrder = (int) $packages->sum('remaining_order');

        $pendingPayments = PackagePaymentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->filter(fn (PackagePaymentRequest $request) => $this->domainNormalizer->matches(
                $request->domain,
                $accessToken->domain
            ));

        $snapshot = [
            'subscription_status' => $this->resolveSubscriptionStatus($activePackage, $remainingOrder),
            'remaining_order' => $remainingOrder,
            'expires_at' => $activePackage?->expires_at,
            'pending_payment_count' => $pendingPayments->count(),
            'has_pending_payment' => $pendingPayments->isNotEmpty(),
        ];

        if ($activePackage) {
            $snapshot['plan_type'] = $activePackage->plan_type ?? 'legacy';
            $snapshot['plan_title'] = $activePackage->title;
        }

        return $snapshot;
    }

    private function applyApprovedPaymentToExisting(
        UserPackage $existing,
        PackageHub $packageHub,
        PackagePaymentRequest $paymentRequest
    ): UserPackage {
        $existingIsCatalog = $this->planResolver->isCatalog($existing);
        $incomingIsCatalog = $this->planResolver->isCatalog($packageHub);

        if ($existingIsCatalog !== $incomingIsCatalog) {
            throw ValidationException::withMessages([
                'package_hub_id' => 'Cannot top up a '
                    .($existingIsCatalog ? 'catalog' : 'legacy')
                    .' subscription with a '
                    .($incomingIsCatalog ? 'catalog' : 'legacy')
                    .' plan. Assign a new plan from the admin panel or submit a matching renewal.',
            ]);
        }

        if ($incomingIsCatalog) {
            return $this->renewCatalogPackage($existing, $packageHub, $paymentRequest);
        }

        return $this->topUpLegacyPackage($existing, $paymentRequest);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: float}
     */
    private function resolveLegacyPaymentAmounts(PackageHub $packageHub, array $data): array
    {
        $orderLimit = (int) ($data['order_limit'] ?? 0);
        if ($orderLimit <= 0) {
            throw ValidationException::withMessages([
                'order_limit' => 'Order limit is required',
            ]);
        }

        $totalAmount = round((float) ($data['total_amount'] ?? ($packageHub->per_order_rate * $orderLimit)), 2);

        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'total_amount' => 'Total amount is required',
            ]);
        }

        return [$orderLimit, $totalAmount];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: float}
     */
    private function resolveCatalogPaymentAmounts(PackageHub $packageHub, array $data): array
    {
        $orderLimit = (int) ($packageHub->order_rate_token ?? 0);
        if ($orderLimit <= 0) {
            throw ValidationException::withMessages([
                'package_hub_id' => 'This catalog plan has no token quota configured.',
            ]);
        }

        $expectedAmount = round((float) ($packageHub->package_price ?? 0), 2);
        $totalAmount = round((float) ($data['total_amount'] ?? $expectedAmount), 2);

        if ($totalAmount < 0 || ($expectedAmount > 0 && $totalAmount <= 0)) {
            throw ValidationException::withMessages([
                'total_amount' => 'Total amount is required',
            ]);
        }

        return [$orderLimit, $totalAmount];
    }

    private function findActivePackageForDomain(User $user, string $domain): ?UserPackage
    {
        return UserPackage::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get()
            ->first(fn (UserPackage $package) => $this->domainNormalizer->matches(
                $package->domain,
                $domain
            ));
    }

    private function topUpLegacyPackage(UserPackage $package, PackagePaymentRequest $paymentRequest): UserPackage
    {
        $additionalOrders = $paymentRequest->order_limit;
        $additionalCost = $package->per_order_rate * $additionalOrders;

        $package->update([
            'total_order_can_handle' => $package->total_order_can_handle + $additionalOrders,
            'remaining_order' => $package->remaining_order + $additionalOrders,
            'total_cost' => $package->total_cost + $additionalCost,
            'transaction_charge' => $package->transaction_charge + $paymentRequest->transaction_charge,
            'transaction_method' => $paymentRequest->transaction_method ?? $package->transaction_method,
            'transaction_id' => $paymentRequest->transaction_id ?? $package->transaction_id,
            'transaction_number' => $paymentRequest->account_number ?? $package->transaction_number,
            'is_active' => true,
            'updated_by' => Auth::id(),
        ]);

        $this->websiteSync->linkUserPackage($package->fresh());

        return $package->fresh();
    }

    private function renewCatalogPackage(
        UserPackage $package,
        PackageHub $packageHub,
        PackagePaymentRequest $paymentRequest
    ): UserPackage {
        return app(SubscriptionAdminService::class)->applyCatalogRenewal($package, $packageHub, [
            'total_cost' => $package->total_cost + $paymentRequest->total_amount,
            'transaction_charge' => $package->transaction_charge + $paymentRequest->transaction_charge,
            'transaction_method' => $paymentRequest->transaction_method ?? $package->transaction_method,
            'transaction_id' => $paymentRequest->transaction_id ?? $package->transaction_id,
            'transaction_number' => $paymentRequest->account_number ?? $package->transaction_number,
        ]);
    }

    private function resolveSubscriptionStatus(?UserPackage $package, int $remainingOrder): string
    {
        if (! $package) {
            return 'none';
        }

        if ($package->expires_at && now()->greaterThan($package->expires_at)) {
            return 'expired';
        }

        if (! $package->is_active) {
            return 'inactive';
        }

        if ($remainingOrder <= 0) {
            return 'exhausted';
        }

        return 'active';
    }
}
