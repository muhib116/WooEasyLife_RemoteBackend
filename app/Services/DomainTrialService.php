<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Models\UserPackage;
use Illuminate\Support\Collection;

class DomainTrialService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    public function hasDomainUsedFreeTrial(string $normalizedDomain): bool
    {
        $normalizedDomain = $this->domainNormalizer->normalize($normalizedDomain);
        if (! $normalizedDomain) {
            return false;
        }

        $packages = UserPackage::query()
            ->withTrashed()
            ->tap(fn ($query) => $this->domainNormalizer->constrainMatchingDomain(
                $query,
                'domain',
                $normalizedDomain
            ))
            ->get(['id', 'user_id', 'domain', 'package_duration', 'package_hub_id']);

        if ($packages->isEmpty()) {
            return false;
        }

        $hubIds = $packages
            ->pluck('package_hub_id')
            ->filter()
            ->unique()
            ->values();

        /** @var Collection<int, PackageHub> $hubs */
        $hubs = $hubIds->isEmpty()
            ? collect()
            : PackageHub::withTrashed()
                ->whereIn('id', $hubIds)
                ->get(['id', 'package_duration'])
                ->keyBy('id');

        foreach ($packages as $package) {
            if (! $this->domainNormalizer->matches($package->domain, $normalizedDomain)) {
                continue;
            }

            if ($this->packageUsedFreeTrial($package, $hubs)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, PackageHub>  $hubs
     */
    private function packageUsedFreeTrial(UserPackage $package, Collection $hubs): bool
    {
        if ($package->package_duration === 'free_trial') {
            return true;
        }

        if (! $package->package_hub_id) {
            return false;
        }

        $hub = $hubs->get((int) $package->package_hub_id);

        return $hub !== null && $hub->package_duration === 'free_trial';
    }
}
