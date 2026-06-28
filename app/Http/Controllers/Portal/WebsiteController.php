<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\MerchantPortalContext;
use App\Services\WebsiteAggregatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteController extends Controller
{
    public function index(
        Request $request,
        MerchantPortalContext $portalContext,
        WebsiteAggregatorService $websiteAggregator
    ) {
        $merchant = $portalContext->resolveMerchant($request->user());
        $websites = $portalContext->filterWebsitesForUser(
            $request->user(),
            $websiteAggregator->forUser($merchant)
        );

        return Inertia::render('Portal/Websites/Index', [
            'websites' => $websites,
        ]);
    }
}
