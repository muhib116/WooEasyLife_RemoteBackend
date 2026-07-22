<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\BlogService;
use App\Services\LandingPageService;
use App\Services\LandingSettingsService;
use App\Services\PublicSubscriptionService;
use App\Services\SeoMetaService;
use App\Services\SubscriptionPaymentConfigService;
use App\Support\WhatsappLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class EnglishMarketingController extends Controller
{
    public function home(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
        PublicSubscriptionService $publicSubscriptions,
        SubscriptionPaymentConfigService $paymentConfig,
    ): Response {
        $payload = $landing->payload($request, 'en');
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage('en_home');

        $pendingInquiry = $publicSubscriptions->resolvePendingForVisitor(
            $request->user(),
            $request->session()->get(PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY),
        );

        if (! $pendingInquiry) {
            $request->session()->forget(PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY);
        }

        return Inertia::render('Welcome3', array_merge($payload, [
            'canLogin' => Route::has('merchant.login'),
            'canRegister' => Route::has('register'),
            'domains' => [],
            'subscriptionWizard' => config('landing.subscription_wizard', []),
            'subscriptionPaymentMethods' => $paymentConfig->forApi(),
            'whatsappSupportUrl' => WhatsappLink::url(
                $whatsapp,
                'Hi, I want a WooEasyLife subscription.',
            ),
            'whatsappDisplayPhone' => $payload['whatsappDisplayPhone'] ?? $whatsapp,
            'pendingSubscriptionInquiry' => $pendingInquiry,
            'seo' => $seoMeta,
            'locale' => 'en',
        ]))->withViewData(['seo' => $seoMeta]);
    }

    public function bdFraudChecker(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->page($request, $landing, $seo, $landingSettings, 'en_bd_fraud_checker', 'Seo/EnBdFraudChecker');
    }

    public function fakeOrderProtection(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->page($request, $landing, $seo, $landingSettings, 'en_fake_order_protection', 'Seo/EnFakeOrderProtection');
    }

    public function returnLossCalculator(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage('en_return_loss_calculator');

        return Inertia::render('Seo/EnReturnLossCalculator', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'roiCalculator' => config('landing.roi_calculator_en', []),
            'roiScenarios' => config('landing.roi_scenarios_en', []),
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                'Hi, I want a WooEasyLife subscription.',
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
        ])->withViewData(['seo' => $seoMeta]);
    }

    public function courierAutoEntry(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->page($request, $landing, $seo, $landingSettings, 'en_courier_auto_entry', 'Seo/EnCourierAutoEntry');
    }

    public function fraudBdAlternative(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->page($request, $landing, $seo, $landingSettings, 'en_fraudbd_alternative', 'Seo/EnFraudBdAlternative');
    }

    public function adsRoasCalculator(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage('en_ads_roas_calculator');

        return Inertia::render('Seo/EnAdsRoasCalculator', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'adsRoasCalculator' => $payload['adsRoasCalculatorEn'] ?? config('landing.ads_roas_calculator_en', []),
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                'Hi, I want a WooEasyLife subscription.',
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
        ])->withViewData(['seo' => $seoMeta]);
    }

    public function courierChargeCalculator(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage('en_courier_charge_calculator');

        return Inertia::render('Seo/EnCourierChargeCalculator', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'courierChargeCalculator' => app(\App\Services\Marketing\CourierPublicRatesService::class)
                ->calculatorConfig('en'),
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                'Hi, I want a WooEasyLife subscription.',
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
        ])->withViewData(['seo' => $seoMeta]);
    }

    public function blogIndex(
        BlogService $blog,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $seoMeta = $seo->forPage('en_blog_index');
        $posts = collect($blog->all('en'))
            ->map(fn (array $post) => [
                'title' => $post['title'],
                'description' => $post['description'],
                'date' => $post['date'],
                'slug' => $post['slug'],
                'locale' => $post['locale'],
            ])
            ->all();

        return Inertia::render('Blog/Index', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'posts' => $posts,
            'whatsappUrl' => WhatsappLink::url($landingSettings->adminWhatsapp()),
            'locale' => 'en',
            'indexPath' => '/en/blog',
        ])->withViewData(['seo' => $seoMeta]);
    }

    private function page(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
        string $seoPage,
        string $component,
    ): Response {
        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage($seoPage);

        return Inertia::render($component, [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'fraudCheck' => $payload['fraudCheck'] ?? [],
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                'Hi, I want a WooEasyLife subscription.',
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
        ])->withViewData(['seo' => $seoMeta]);
    }
}
