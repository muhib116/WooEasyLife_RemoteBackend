<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use App\Services\LandingPageService;
use App\Services\LandingSettingsService;
use App\Services\MerchantPortalContext;
use App\Services\PublicSubscriptionService;
use App\Services\RbacService;
use App\Services\SeoMetaService;
use App\Services\WebsiteAggregatorService;
use App\Support\WhatsappLink;
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
        RbacService $rbac,
        PublicSubscriptionService $subscriptionService,
        SeoMetaService $seoMeta,
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

        $payload = $landing->payload();
        $whatsapp = app(LandingSettingsService::class)->adminWhatsapp();
        $pendingInquiry = $subscriptionService->resolvePendingForVisitor(
            $user,
            $request->session()->get(PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY),
        );

        if (! $pendingInquiry) {
            $request->session()->forget(PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY);
        }

        $seo = $seoMeta->forPage('pricing');

        return Inertia::render('Pricing/Index', array_merge($payload, [
            'canLogin' => Route::has('merchant.login'),
            'domains' => $domains,
            'canPurchase' => $canPurchase,
            'preselectedPlanId' => $request->integer('plan') ?: null,
            'paymentMethods' => $payload['subscriptionPaymentMethods'] ?? [],
            'subscriptionWizard' => config('landing.subscription_wizard', []),
            'whatsappSupportUrl' => WhatsappLink::url(
                $whatsapp,
                config('landing.whatsapp_default_message'),
            ),
            'whatsappDisplayPhone' => $payload['whatsappDisplayPhone'] ?? null,
            'pendingSubscriptionInquiry' => $pendingInquiry,
            'seo' => $seo,
        ]))->withViewData(['seo' => $seo]);
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
