<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PackagePaymentRequest;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\MerchantPortalContext;
use App\Services\SubscriptionAlertService;
use App\Services\WebsiteAggregatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        MerchantPortalContext $portalContext,
        WebsiteAggregatorService $websiteAggregator,
        SubscriptionAlertService $subscriptionAlertService
    ) {
        $merchant = $portalContext->resolveMerchant($request->user());
        $employee = $portalContext->resolveEmployee($request->user());
        $websites = $portalContext->filterWebsitesForUser(
            $request->user(),
            $websiteAggregator->forUser($merchant)
        );

        $domains = collect($websites)->pluck('domain')->filter()->unique()->values()->all();
        if ($employee?->website_id) {
            $scopedDomain = Website::query()
                ->where('id', $employee->website_id)
                ->value('domain');
            $domains = $scopedDomain ? [$scopedDomain] : [];
        }

        $alerts = $subscriptionAlertService->collectPortalAlerts($merchant, $domains);

        $packages = UserPackage::query()
            ->where('user_id', $merchant->id)
            ->where('is_active', true)
            ->get();

        $pendingPayments = PackagePaymentRequest::query()
            ->where('user_id', $merchant->id)
            ->where('status', 'pending')
            ->count();

        return Inertia::render('Portal/Dashboard', [
            'summary' => [
                'websites' => count($websites),
                'remaining_orders' => (int) $packages->sum('remaining_order'),
                'active_plans' => $packages->count(),
                'pending_payments' => $pendingPayments,
                'billing_alerts' => count($alerts),
            ],
            'websites' => collect($websites)->take(4)->values(),
            'alerts' => collect($alerts)->take(5)->values(),
        ]);
    }
}
