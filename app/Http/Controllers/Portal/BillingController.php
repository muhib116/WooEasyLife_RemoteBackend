<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\MerchantPortalContext;
use App\Services\PackagePaymentService;
use App\Services\PackagePlanResolver;
use App\Services\SubscriptionAlertService;
use App\Services\WebsiteAggregatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct(
        protected PackagePaymentService $packagePaymentService,
        protected SubscriptionAlertService $subscriptionAlertService,
        protected WebsiteAggregatorService $websiteAggregator,
        protected PackagePlanResolver $planResolver,
    ) {
    }

    public function index(Request $request, MerchantPortalContext $portalContext)
    {
        $merchant = $portalContext->resolveMerchant($request->user());
        $employee = $portalContext->resolveEmployee($request->user());
        $scopedWebsiteIds = $employee ? $portalContext->assignedWebsiteIds($employee) : [];

        $paymentsQuery = PackagePaymentRequest::query()
            ->with('packageHub:id,title,per_order_rate')
            ->where('user_id', $merchant->id)
            ->orderByDesc('id');

        if ($scopedWebsiteIds !== []) {
            $paymentsQuery->whereIn('website_id', $scopedWebsiteIds);
        }

        $payments = $paymentsQuery->limit(100)->get();

        $packagesQuery = UserPackage::query()
            ->where('user_id', $merchant->id)
            ->where('is_active', true)
            ->orderByDesc('id');

        if ($scopedWebsiteIds !== []) {
            $packagesQuery->whereIn('website_id', $scopedWebsiteIds);
        }

        $packages = $packagesQuery->get();

        $domains = $this->resolveDomains($merchant, $scopedWebsiteIds);
        $alerts = $this->subscriptionAlertService->collectPortalAlerts($merchant, $domains);
        $tab = $request->query('tab', 'payments');

        return Inertia::render('Portal/Billing/Index', [
            'payments' => $payments,
            'packages' => $packages,
            'plans' => $this->planResolver->mapPlansForDisplay(
                $this->packagePaymentService->listActivePlans()
            ),
            'domains' => $domains,
            'alerts' => $alerts,
            'tab' => $tab,
        ]);
    }

    public function store(Request $request, MerchantPortalContext $portalContext)
    {
        $merchant = $portalContext->resolveMerchant($request->user());
        $employee = $portalContext->resolveEmployee($request->user());

        $validated = $request->validate([
            'package_hub_id' => 'required|integer',
            'order_limit' => 'nullable|integer|min:1',
            'domain' => 'required|string',
            'total_amount' => 'nullable|numeric|min:0',
            'transaction_charge' => 'nullable|numeric|min:0',
            'transaction_method' => 'required|string',
            'transaction_id' => 'required|string',
            'account_number' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $allowedDomains = $this->resolveDomains(
            $merchant,
            $employee ? $portalContext->assignedWebsiteIds($employee) : []
        );

        if (
            ! collect($allowedDomains)->contains(
                fn (string $allowedDomain) => app(\App\Services\DomainNormalizer::class)->matches(
                    $allowedDomain,
                    $validated['domain']
                )
            )
        ) {
            abort(403, 'You can only submit payment requests for your assigned websites.');
        }

        try {
            $this->packagePaymentService->createRequest($merchant, $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Payment request submitted successfully.');
    }

    /**
     * @param  array<int, int>  $websiteIds
     * @return array<int, string>
     */
    private function resolveDomains(User $merchant, array $websiteIds): array
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

        return collect($this->websiteAggregator->forUser($merchant))
            ->pluck('domain')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
