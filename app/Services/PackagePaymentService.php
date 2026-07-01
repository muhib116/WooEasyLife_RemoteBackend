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
        protected PackagePlanResolver $planResolver,
        protected SubscriptionPaymentIntentService $paymentIntentService,
        protected DomainTrialService $domainTrial
    ) {
    }

    /**
     * @return Collection<int, PackageHub>
     */
    public function listActivePlans(): Collection
    {
        return PackageHub::query()
            ->where('is_active', true)
            ->whereNotNull('package_duration')
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

        $this->assertPluginCanSubmitPayment($user, $domain, $accessToken);

        $transactionCharge = round((float) ($data['transaction_charge'] ?? $data['total_charge'] ?? 0), 2);

        if ($this->planResolver->isCatalog($packageHub)) {
            [$orderLimit, $totalAmount] = $this->resolveCatalogPaymentAmounts($packageHub, $data);
        } else {
            [$orderLimit, $totalAmount] = $this->resolveLegacyPaymentAmounts($packageHub, $data);
        }

        $this->assertPluginPaymentAmounts($accessToken, $packageHub, $orderLimit, $data, $transactionCharge);

        $existing = $this->findActivePackageForDomain($user, $domain);
        $remainingOrder = $this->remainingOrderForDomain($user, $domain);
        $subscriptionStatus = $this->resolveSubscriptionStatus($existing, $remainingOrder);
        $resolvedIntent = $this->paymentIntentService->resolveIntent($existing, $packageHub);

        $trialBlock = $this->freeTrialBlockMessage($domain, $packageHub, $existing, $subscriptionStatus);
        if ($trialBlock !== null) {
            throw ValidationException::withMessages([
                'package_hub_id' => $trialBlock,
            ]);
        }

        if ($this->paymentIntentService->shouldEnforceIntentRules($accessToken)) {
            $validation = $this->paymentIntentService->validateSubmission(
                $existing,
                $packageHub,
                $subscriptionStatus,
                isset($data['intent']) ? (string) $data['intent'] : null
            );

            if (! $validation['allowed']) {
                throw ValidationException::withMessages([
                    'package_hub_id' => $validation['message'] ?? 'This payment request is not allowed.',
                ]);
            }

            $resolvedIntent = $validation['intent'];
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
            'payment_intent' => $resolvedIntent,
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
        $totalQuota = (int) $packages->sum('total_order_can_handle');
        $totalHandled = (int) $packages->sum('total_order_handled');

        $pendingPayments = PackagePaymentRequest::query()
            ->with('packageHub:id,title,per_order_rate,package_price,order_rate_token')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PackagePaymentRequest $request) => $this->domainNormalizer->matches(
                $request->domain,
                $accessToken->domain
            ))
            ->values();

        $hasPendingPayment = $pendingPayments->isNotEmpty();

        $snapshot = [
            'subscription_status' => $this->resolveSubscriptionStatus($activePackage, $remainingOrder),
            'remaining_order' => $remainingOrder,
            'total_order_can_handle' => $totalQuota,
            'total_order_handled' => $totalHandled,
            'expires_at' => $activePackage?->expires_at,
            'pending_payment_count' => $pendingPayments->count(),
            'has_pending_payment' => $hasPendingPayment,
            'pending_payments' => $pendingPayments
                ->map(fn (PackagePaymentRequest $request) => $this->mapPendingPaymentPayload($request))
                ->values()
                ->all(),
        ];

        $capabilities = $this->paymentIntentService->billingCapabilities(
            $activePackage,
            $snapshot['subscription_status'],
            $hasPendingPayment
        );

        $snapshot['can_renew_current_plan'] = $capabilities['can_renew_current_plan'];
        $snapshot['can_upgrade_plan'] = $capabilities['can_upgrade_plan'];
        $snapshot['can_submit_payment'] = $capabilities['can_submit_payment'];
        $snapshot['can_subscribe_plan'] = $capabilities['can_subscribe_plan'];

        $normalizedDomain = $this->domainNormalizer->normalize($accessToken->domain);
        $snapshot['domain_trial_used'] = $normalizedDomain
            ? $this->domainTrial->hasDomainUsedFreeTrial($normalizedDomain)
            : false;

        if ($activePackage) {
            $snapshot['plan_type'] = $activePackage->plan_type ?? 'legacy';
            $snapshot['plan_title'] = $activePackage->title;
            $snapshot['package_hub_id'] = (int) $activePackage->package_hub_id;

            $currentHub = PackageHub::query()->find($activePackage->package_hub_id);
            if ($currentHub) {
                $snapshot['current_plan_package_price'] = (float) ($currentHub->package_price ?? 0);
                $snapshot['current_plan_index'] = (int) ($currentHub->index ?? 0);
                $snapshot['current_plan_package_duration'] = $currentHub->package_duration;
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function paymentQuote(User $user, AccessToken $accessToken, array $data): array
    {
        $domain = $this->domainNormalizer->normalize($accessToken->domain);
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

        $existing = $this->findActivePackageForDomain($user, $domain);
        $remainingOrder = $this->remainingOrderForDomain($user, $domain);
        $subscriptionStatus = $this->resolveSubscriptionStatus($existing, $remainingOrder);
        $intent = $this->paymentIntentService->resolveIntent($existing, $packageHub);

        if ($this->hasPendingPaymentForDomain($user, $domain)) {
            return [
                'allowed' => false,
                'intent' => $intent,
                'package_hub_id' => (int) $packageHub->id,
                'plan_title' => $packageHub->title,
                'message' => 'You already have a payment pending approval. Please wait for admin verification before submitting another request.',
            ];
        }

        $trialBlock = $this->freeTrialBlockMessage($domain, $packageHub, $existing, $subscriptionStatus);
        if ($trialBlock !== null) {
            return [
                'allowed' => false,
                'intent' => $intent,
                'package_hub_id' => (int) $packageHub->id,
                'plan_title' => $packageHub->title,
                'message' => $trialBlock,
            ];
        }

        if ($this->paymentIntentService->shouldEnforceIntentRules($accessToken)) {
            $validation = $this->paymentIntentService->validateSubmission(
                $existing,
                $packageHub,
                $subscriptionStatus,
                isset($data['intent']) ? (string) $data['intent'] : null
            );

            if (! $validation['allowed']) {
                return [
                    'allowed' => false,
                    'intent' => $validation['intent'],
                    'package_hub_id' => (int) $packageHub->id,
                    'plan_title' => $packageHub->title,
                    'message' => $validation['message'],
                ];
            }

            $intent = $validation['intent'];
        }

        if ($this->planResolver->isCatalog($packageHub)) {
            [$orderLimit, $totalAmount] = $this->resolveCatalogPaymentAmounts($packageHub, $data);
        } else {
            [$orderLimit, $totalAmount] = $this->resolveLegacyPaymentAmounts($packageHub, $data);
        }

        return [
            'allowed' => true,
            'intent' => $intent,
            'package_hub_id' => (int) $packageHub->id,
            'plan_title' => $packageHub->title,
            'plan_type' => $this->planResolver->planType($packageHub),
            'order_limit' => $orderLimit,
            'total_amount' => $totalAmount,
            'per_order_rate' => $this->planResolver->isCatalog($packageHub)
                ? null
                : (float) $packageHub->per_order_rate,
            'pricing_note' => $this->pricingNote($intent, $subscriptionStatus, $remainingOrder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSubmissionGuide(PackagePaymentRequest $paymentRequest): array
    {
        $paymentRequest->loadMissing('packageHub:id,title');

        $display = config('package_catalog.plugin_display', []);
        $planTitle = $paymentRequest->packageHub?->title
            ?? ($display['submission_selected_plan_bn'] ?? 'নির্বাচিত প্ল্যান');
        $amount = number_format((float) $paymentRequest->total_amount, 0, '.', ',');
        $detail = str_replace(
            [':plan', ':amount', ':txn'],
            [$planTitle, $amount, (string) $paymentRequest->transaction_id],
            $display['submission_step_detail_bn'] ?? ':plan — :amount টাকা (ট্রানজেকশন: :txn)',
        );

        return [
            'status' => 'pending_review',
            'title' => $display['submission_title_bn'] ?? 'পেমেন্ট সফলভাবে জমা হয়েছে',
            'message' => $display['submission_message_bn'] ?? 'আপনার পেমেন্ট এডমিন যাচাইয়ের অপেক্ষায় আছে।',
            'steps' => [
                [
                    'step' => 1,
                    'label' => $display['submission_step_submitted_bn'] ?? 'পেমেন্ট জমা হয়েছে',
                    'status' => 'completed',
                    'detail' => $detail,
                ],
                [
                    'step' => 2,
                    'label' => $display['submission_step_waiting_bn'] ?? 'যাচাইয়ের অপেক্ষায়',
                    'status' => 'in_progress',
                    'detail' => $display['submission_step_waiting_detail_bn'] ?? 'আমাদের টিম শীঘ্রই আপনার ট্রানজেকশন যাচাই করবে।',
                ],
                [
                    'step' => 3,
                    'label' => $display['submission_step_activation_bn'] ?? 'প্ল্যান সক্রিয়করণ',
                    'status' => 'pending',
                    'detail' => $display['submission_step_activation_detail_bn'] ?? 'অনুমোদনের পর প্ল্যান স্বয়ংক্রিয়ভাবে আপডেট হবে।',
                ],
            ],
        ];
    }

    private function applyApprovedPaymentToExisting(
        UserPackage $existing,
        PackageHub $packageHub,
        PackagePaymentRequest $paymentRequest
    ): UserPackage {
        $existingIsCatalog = $this->planResolver->isCatalog($existing);
        $incomingIsCatalog = $this->planResolver->isCatalog($packageHub);

        if ($existingIsCatalog !== $incomingIsCatalog) {
            return app(SubscriptionAdminService::class)->changePlan(
                User::findOrFail($existing->user_id),
                $existing,
                $packageHub,
                $this->paymentChangeData($paymentRequest)
            );
        }

        if ($incomingIsCatalog) {
            return $this->applyApprovedCatalogPayment($existing, $packageHub, $paymentRequest);
        }

        if ((int) $existing->package_hub_id !== (int) $packageHub->id) {
            return app(SubscriptionAdminService::class)->changePlan(
                User::findOrFail($existing->user_id),
                $existing,
                $packageHub,
                $this->paymentChangeData($paymentRequest)
            );
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

    private function remainingOrderForDomain(User $user, string $domain): int
    {
        return (int) UserPackage::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (UserPackage $package) => $this->domainNormalizer->matches(
                $package->domain,
                $domain
            ))
            ->sum('remaining_order');
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

    /**
     * Applies an approved catalog payment (renewal, upgrade, or downgrade).
     * Catalog plan swaps always replace quota/expiry via applyCatalogRenewal().
     */
    private function applyApprovedCatalogPayment(
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

    /**
     * @return array<string, mixed>
     */
    private function paymentChangeData(PackagePaymentRequest $paymentRequest): array
    {
        return [
            'domain' => $paymentRequest->domain,
            'limit' => $paymentRequest->order_limit,
            'transaction_method' => $paymentRequest->transaction_method ?? 'Cash',
            'transaction_number' => $paymentRequest->account_number,
            'transaction_id' => $paymentRequest->transaction_id,
            'transaction_charge' => $paymentRequest->transaction_charge,
            'note' => $paymentRequest->note,
        ];
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

    private function assertPluginCanSubmitPayment(User $user, string $domain, ?AccessToken $accessToken): void
    {
        if ($accessToken === null) {
            return;
        }

        if ($this->hasPendingPaymentForDomain($user, $domain)) {
            throw ValidationException::withMessages([
                'payment' => 'You already have a payment pending approval. Please wait for admin verification before submitting another request.',
            ]);
        }
    }

    private function hasPendingPaymentForDomain(User $user, string $domain): bool
    {
        $normalizedDomain = $this->domainNormalizer->normalize($domain) ?? $domain;

        if (PackagePaymentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('domain', $normalizedDomain)
            ->exists()) {
            return true;
        }

        return PackagePaymentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('domain', '!=', $normalizedDomain)
            ->get()
            ->contains(fn (PackagePaymentRequest $request) => $this->domainNormalizer->matches(
                $request->domain,
                $normalizedDomain
            ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPluginPaymentAmounts(
        ?AccessToken $accessToken,
        PackageHub $packageHub,
        int $orderLimit,
        array $data,
        float $transactionCharge
    ): void {
        if ($accessToken === null || ! (bool) config('subscription_payments.validate_plugin_amounts', true)) {
            return;
        }

        $expectedBase = $this->expectedBaseAmount($packageHub, $orderLimit);
        $submittedTotal = round((float) ($data['total_amount'] ?? 0), 2);
        $submittedBase = round($submittedTotal - $transactionCharge, 2);

        if ($expectedBase <= 0 && $submittedBase <= 0) {
            return;
        }

        if (abs($submittedBase - $expectedBase) > 0.01) {
            throw ValidationException::withMessages([
                'total_amount' => 'Payment amount does not match the selected plan. Refresh the page and try again.',
            ]);
        }
    }

    private function expectedBaseAmount(PackageHub $packageHub, int $orderLimit): float
    {
        if ($this->planResolver->isCatalog($packageHub)) {
            return round((float) ($packageHub->package_price ?? 0), 2);
        }

        return round((float) $packageHub->per_order_rate * max(0, $orderLimit), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPendingPaymentPayload(PackagePaymentRequest $request): array
    {
        return [
            'id' => $request->id,
            'package_hub_id' => (int) $request->package_hub_id,
            'plan_title' => $request->packageHub?->title,
            'payment_intent' => $request->payment_intent,
            'order_limit' => (int) $request->order_limit,
            'total_amount' => (float) $request->total_amount,
            'transaction_method' => $request->transaction_method,
            'transaction_id' => $request->transaction_id,
            'account_number' => $request->account_number,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    private function pricingNote(
        string $intent,
        string $subscriptionStatus,
        int $remainingOrder
    ): string {
        $notes = config('package_catalog.plugin_display.pricing_notes_bn', []);

        if ($intent === SubscriptionPaymentIntentService::INTENT_UPGRADE && $subscriptionStatus === 'active') {
            if ($remainingOrder > 0) {
                return str_replace(
                    ':count',
                    (string) $remainingOrder,
                    $notes['upgrade_remaining'] ?? $notes['upgrade'] ?? ''
                );
            }

            return $notes['upgrade'] ?? '';
        }

        if ($intent === SubscriptionPaymentIntentService::INTENT_DOWNGRADE && $subscriptionStatus === 'active') {
            return $notes['downgrade'] ?? '';
        }

        if ($intent === SubscriptionPaymentIntentService::INTENT_RENEW) {
            return $notes['renew'] ?? '';
        }

        if ($intent === SubscriptionPaymentIntentService::INTENT_SUBSCRIBE) {
            return $notes['subscribe'] ?? '';
        }

        return $notes['default'] ?? '';
    }

    private function freeTrialBlockMessage(
        string $domain,
        PackageHub $packageHub,
        ?UserPackage $existing,
        string $subscriptionStatus
    ): ?string {
        if (! $this->planResolver->isFreeTrial($packageHub)) {
            return null;
        }

        if ($this->domainTrial->hasDomainUsedFreeTrial($domain)) {
            return config(
                'package_catalog.plugin_display.pricing_notes_bn.free_trial_used',
                'এই স্টোরের ফ্রি ট্রায়াল ইতিমধ্যে ব্যবহার করা হয়েছে।'
            );
        }

        if ($existing && $subscriptionStatus === 'active') {
            return 'Free trial is not available while you have an active subscription.';
        }

        return null;
    }
}
