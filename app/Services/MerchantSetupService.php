<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;

class MerchantSetupService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * @return array<int, array{key: string, label: string, complete: bool, hint: string|null, action_route: string|null}>
     */
    public function checklist(User $user): array
    {
        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->get();

        $tokens = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->get();

        $activePackages = $packages->where('is_active', true);
        $hasActivePlan = $activePackages->isNotEmpty();
        $hasLicense = $tokens->isNotEmpty();
        $domainsAligned = $this->domainsAligned($activePackages, $tokens);
        $pluginConnected = $tokens->contains(fn ($token) => $token->last_used_at !== null);

        $accountActive = (bool) $user->status && ! $user->trashed();

        return [
            [
                'key' => 'account',
                'label' => 'Merchant account active',
                'complete' => $accountActive,
                'hint' => $accountActive ? null : 'Enable the merchant account status.',
                'action_route' => null,
            ],
            [
                'key' => 'plan',
                'label' => 'Subscription plan assigned',
                'complete' => $hasActivePlan,
                'hint' => $hasActivePlan ? null : 'Assign a plan with order quota for a website domain.',
                'action_route' => 'users.websites',
                'action_query' => ['action' => 'assign'],
            ],
            [
                'key' => 'license',
                'label' => 'License key generated',
                'complete' => $hasLicense,
                'hint' => $hasLicense ? null : 'Generate an API license key for the plugin.',
                'action_route' => 'users.websites',
                'action_query' => ['action' => 'license'],
            ],
            [
                'key' => 'domains',
                'label' => 'Plan and license domains match',
                'complete' => ! $hasLicense || ! $hasActivePlan ? false : $domainsAligned,
                'hint' => $domainsAligned
                    ? null
                    : 'Ensure license domain matches an active plan domain (use the same hostname).',
                'action_route' => 'users.websites',
                'action_query' => null,
            ],
            [
                'key' => 'plugin',
                'label' => 'Plugin connected',
                'complete' => $pluginConnected,
                'hint' => $pluginConnected ? null : 'Waiting for the plugin to call the API with this license.',
                'action_route' => null,
            ],
        ];
    }

    public function progress(User $user): array
    {
        $steps = $this->checklist($user);
        $stepsCollection = collect($steps);
        $required = $stepsCollection->whereIn('key', ['account', 'plan', 'license', 'domains']);
        $complete = $required->where('complete', true)->count();
        $total = $required->count();
        $pluginStep = $stepsCollection->firstWhere('key', 'plugin');
        $pluginConnected = (bool) ($pluginStep['complete'] ?? false);

        return [
            'complete' => $complete,
            'total' => $total,
            'ready_for_plugin' => $complete === $total
                && (bool) $user->status
                && $pluginConnected,
            'configured_for_plugin' => $complete === $total && (bool) $user->status,
            'needs_wizard' => $complete < $total && (bool) $user->status && ! $user->trashed(),
            'steps' => $steps,
        ];
    }

    private function domainsAligned($activePackages, $tokens): bool
    {
        if ($tokens->isEmpty() || $activePackages->isEmpty()) {
            return false;
        }

        $packageDomains = $activePackages
            ->map(fn ($p) => $this->domainNormalizer->normalize($p->domain))
            ->filter()
            ->unique()
            ->values();

        foreach ($tokens as $token) {
            $tokenDomain = $this->domainNormalizer->normalize($token->domain);

            if (! $tokenDomain || ! $packageDomains->contains($tokenDomain)) {
                return false;
            }
        }

        return true;
    }
}
