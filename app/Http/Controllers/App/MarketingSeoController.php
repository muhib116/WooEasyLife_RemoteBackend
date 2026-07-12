<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\LandingPageService;
use App\Services\LandingSettingsService;
use App\Services\SeoMetaService;
use App\Support\WhatsappLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class MarketingSeoController extends Controller
{
    public function bdFraudChecker(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->renderSeoPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            'bd_fraud_checker',
            'Seo/BdFraudChecker',
            'fraud-check',
        );
    }

    public function fakeOrderProtection(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->renderSeoPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            'fake_order_protection',
            'Seo/FakeOrderProtection',
            'features',
        );
    }

    public function courierAutoEntry(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->renderSeoPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            'courier_auto_entry',
            'Seo/CourierAutoEntry',
            'features',
        );
    }

    public function fraudBdAlternative(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->renderSeoPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            'fraudbd_alternative',
            'Seo/FraudBdAlternative',
            'fraud-check',
        );
    }

    private function renderSeoPage(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
        string $seoPage,
        string $component,
        string $activeNav,
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
                config('landing.whatsapp_default_message'),
            ),
            'activeNav' => $activeNav,
            'faqs' => $seoMeta['faqs'] ?? [],
        ])->withViewData(['seo' => $seoMeta]);
    }
}
