<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use App\Services\LandingPageService;
use App\Services\MerchantPortalContext;
use App\Services\RbacService;
use App\Services\WebsiteAggregatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function __invoke(
        Request $request,
        LandingPageService $landing,
        MerchantPortalContext $portalContext,
        WebsiteAggregatorService $websiteAggregator,
        RbacService $rbac
    ) {
        $user = $request->user();
        $domains = [];
        $canPurchase = false;

        if ($user && $portalContext->canAccessPortal($user)) {
            $merchant = $portalContext->resolveMerchant($user);
            $employee = $portalContext->resolveEmployee($user);
            $scopedWebsiteIds = $employee ? $portalContext->assignedWebsiteIds($employee) : [];
            $domains = $this->resolveDomains($merchant, $scopedWebsiteIds, $websiteAggregator);
            $canPurchase = $rbac->hasPermission($user, 'billing.manage');
        }

        return Inertia::render('Pricing/Index', array_merge($landing->payload(), [
            'canLogin' => Route::has('merchant.login'),
            'domains' => $domains,
            'canPurchase' => $canPurchase,
            'preselectedPlanId' => $request->integer('plan') ?: null,
        ]));
    }

    /**
     * @param  array<int, int>  $websiteIds
     * @return array<int, string>
     */
    private function resolveDomains(User $merchant, array $websiteIds, WebsiteAggregatorService $aggregator): array
    {
        if ($websiteIds !== []) {
            return Website::query()
                ->where('user_id', $merchant->id)
                ->whereIn('id', $websiteIds)
                ->pluck('domain')
                ->filter()
                ->values()
                ->all();
        }

        return collect($aggregator->forUser($merchant))
            ->pluck('domain')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
