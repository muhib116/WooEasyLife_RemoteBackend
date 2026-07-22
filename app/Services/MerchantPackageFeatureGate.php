<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Support\PackageCatalogFeatures;
use Illuminate\Http\Request;

class MerchantPackageFeatureGate
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer,
    ) {}

    public function hasFromCurrentRequest(string $featureKey): bool
    {
        $request = request();

        if (! $request instanceof Request) {
            return false;
        }

        return $this->hasFromRequest($request, $featureKey);
    }

    public function hasFromRequest(Request $request, string $featureKey): bool
    {
        $package = $this->resolveActivePackage($request);

        if ($package === null) {
            return false;
        }

        // Legacy pay-per-order plans historically exposed the full plugin surface.
        if (($package->plan_type ?? 'legacy') !== 'catalog') {
            return true;
        }

        $features = PackageCatalogFeatures::normalize(
            is_array($package->features) ? $package->features : []
        );

        return (bool) ($features[$featureKey] ?? false);
    }

    public function resolveActivePackage(Request $request): ?UserPackage
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = AccessToken::findToken($token);

        if (! $accessToken || $accessToken->tokenable_type !== User::class) {
            return null;
        }

        return UserPackage::query()
            ->where('user_id', $accessToken->tokenable_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (UserPackage $package) => $this->domainNormalizer->matches(
                $package->domain,
                $accessToken->domain
            ))
            ->sortByDesc('id')
            ->first();
    }
}
