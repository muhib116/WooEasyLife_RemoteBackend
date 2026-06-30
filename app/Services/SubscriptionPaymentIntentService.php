<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\UserPackage;

class SubscriptionPaymentIntentService
{
    public const INTENT_SUBSCRIBE = 'subscribe';

    public const INTENT_RENEW = 'renew';

    public const INTENT_UPGRADE = 'upgrade';

    public const INTENT_DOWNGRADE = 'downgrade';

    public function __construct(
        protected PackagePlanResolver $planResolver
    ) {
    }

    public function resolveIntent(?UserPackage $activePackage, PackageHub $targetHub): string
    {
        if (! $activePackage) {
            return self::INTENT_SUBSCRIBE;
        }

        if ((int) $activePackage->package_hub_id === (int) $targetHub->id) {
            return self::INTENT_RENEW;
        }

        $currentHub = PackageHub::query()->find($activePackage->package_hub_id);
        if ($currentHub) {
            $tier = $this->planResolver->compareTier($currentHub, $targetHub);
            if ($tier > 0) {
                return self::INTENT_DOWNGRADE;
            }
        }

        return self::INTENT_UPGRADE;
    }

    /**
     * @return array{
     *     can_renew_current_plan: bool,
     *     can_upgrade_plan: bool,
     *     can_submit_payment: bool,
     *     can_subscribe_plan: bool
     * }
     */
    public function billingCapabilities(
        ?UserPackage $activePackage,
        string $subscriptionStatus,
        bool $hasPendingPayment = false
    ): array {
        if ($hasPendingPayment) {
            return [
                'can_renew_current_plan' => false,
                'can_upgrade_plan' => false,
                'can_submit_payment' => false,
                'can_subscribe_plan' => false,
            ];
        }

        if (! $activePackage) {
            return [
                'can_renew_current_plan' => false,
                'can_upgrade_plan' => false,
                'can_submit_payment' => true,
                'can_subscribe_plan' => true,
            ];
        }

        $renewable = in_array($subscriptionStatus, ['expired', 'exhausted'], true);
        $upgradable = in_array($subscriptionStatus, ['active', 'expired', 'exhausted'], true);

        return [
            'can_renew_current_plan' => $renewable,
            'can_upgrade_plan' => $upgradable,
            'can_submit_payment' => true,
            'can_subscribe_plan' => false,
        ];
    }

    /**
     * @return array{allowed: bool, intent: string, message: string|null}
     */
    public function validateSubmission(
        ?UserPackage $activePackage,
        PackageHub $targetHub,
        string $subscriptionStatus,
        ?string $requestedIntent = null
    ): array {
        $intent = $this->resolveIntent($activePackage, $targetHub);

        if ($requestedIntent !== null && $requestedIntent !== $intent) {
            return [
                'allowed' => false,
                'intent' => $intent,
                'message' => 'Payment intent does not match the selected plan.',
            ];
        }

        if ($activePackage) {
            $existingIsCatalog = $this->planResolver->isCatalog($activePackage);
            $incomingIsCatalog = $this->planResolver->isCatalog($targetHub);

            if ($existingIsCatalog !== $incomingIsCatalog) {
                return [
                    'allowed' => false,
                    'intent' => $intent,
                    'message' => 'Cannot switch between catalog and legacy plans via payment. Contact support for a plan change.',
                ];
            }
        }

        if ($intent === self::INTENT_SUBSCRIBE) {
            return [
                'allowed' => true,
                'intent' => $intent,
                'message' => null,
            ];
        }

        if ($intent === self::INTENT_RENEW) {
            if (in_array($subscriptionStatus, ['expired', 'exhausted'], true)) {
                return [
                    'allowed' => true,
                    'intent' => $intent,
                    'message' => null,
                ];
            }

            return [
                'allowed' => false,
                'intent' => $intent,
                'message' => 'Your current plan is still active. Renew when it expires or upgrade to a different plan.',
            ];
        }

        if ($subscriptionStatus === 'active' || in_array($subscriptionStatus, ['expired', 'exhausted'], true)) {
            return [
                'allowed' => true,
                'intent' => $intent,
                'message' => null,
            ];
        }

        return [
            'allowed' => false,
            'intent' => $intent,
            'message' => 'This plan change is not available for your current subscription status.',
        ];
    }

    public function shouldEnforceIntentRules(?AccessToken $accessToken = null): bool
    {
        if (! (bool) config('subscription_payments.enforce_intent_rules', false)) {
            return false;
        }

        if (! (bool) config('subscription_payments.enforce_intent_plugin_api_only', true)) {
            return true;
        }

        return $accessToken !== null;
    }
}
