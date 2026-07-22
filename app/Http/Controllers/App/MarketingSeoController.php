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

    public function returnLossCalculator(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->renderCalculatorPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            'return_loss_calculator',
            'Seo/ReturnLossCalculator',
            [
                'roiCalculator' => 'roiCalculator',
                'roiScenarios' => 'roiScenarios',
            ],
        );
    }

    public function courierChargeCalculator(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->renderCalculatorPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            'courier_charge_calculator',
            'Seo/CourierChargeCalculator',
            [
                'courierChargeCalculator' => 'courierChargeCalculator',
            ],
        );
    }

    public function adsRoasCalculator(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        return $this->renderCalculatorPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            'ads_roas_calculator',
            'Seo/AdsRoasCalculator',
            [
                'adsRoasCalculator' => 'adsRoasCalculator',
            ],
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

    /**
     * Hub-and-spoke WooCommerce Bangladesh cluster pages.
     */
    public function clusterGuide(
        Request $request,
        string $seoKey,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $allowed = [
            'woocommerce_bangladesh',
            'steadfast_integration',
            'pathao_courier_guide',
            'redx_courier_guide',
            'woocommerce_mobile_app',
            'customer_verification',
            'cod_return_reduction',
            'woocommerce_notifications',
            'facebook_ads_for_woocommerce',
        ];

        if (! in_array($seoKey, $allowed, true)) {
            abort(404);
        }

        return $this->renderSeoPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            $seoKey,
            'Seo/ClusterGuide',
            'features',
        );
    }

    public function courierIntent(
        Request $request,
        string $courier,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $map = [
            'pathao' => ['name' => 'Pathao', 'seo' => 'pathao_fraud_check'],
            'steadfast' => ['name' => 'Steadfast', 'seo' => 'steadfast_fraud_check'],
            'redx' => ['name' => 'RedX', 'seo' => 'redx_fraud_check'],
        ];

        $key = strtolower($courier);
        if (! isset($map[$key])) {
            abort(404);
        }

        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage($map[$key]['seo']);

        return Inertia::render('Seo/CourierIntent', [
            'courierName' => $map[$key]['name'],
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'fraudCheck' => $payload['fraudCheck'] ?? [],
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                config('landing.whatsapp_default_message'),
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
        ])->withViewData(['seo' => $seoMeta]);
    }

    public function keywordIntent(
        Request $request,
        string $seoKey,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $allowed = [
            'ki_vabe_fake_order_atkabo',
            'fake_customer_check',
            'bd_courier_ratio_checker',
            'fake_order_check',
            'courier_checker',
        ];

        if (! in_array($seoKey, $allowed, true)) {
            abort(404);
        }

        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage($seoKey);

        $stepsByKey = [
            'ki_vabe_fake_order_atkabo' => [
                'মোবাইল নম্বর দিয়ে Courier Fraud Checker BD চালান — হিস্টোরি ও সাকসেস রেট দেখুন।',
                'সাকসেস রেট খারাপ হলে কল করে যাচাই করুন; ভালো হলে কনফার্ম করুন।',
                'চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন।',
                'কনফার্ম হলেই কুরিয়ার অটো এন্ট্রি ব্যবহার করুন — সময় ও ভুল কমান।',
            ],
            'fake_customer_check' => [
                'কাস্টমারের মোবাইল নম্বর দিন।',
                'কুরিয়ার ডেলিভারি ও রিটার্ন হিস্টোরি দেখুন।',
                'ঝুঁকি বেশি হলে অর্ডার আটকান বা আগে কল করুন।',
            ],
            'bd_courier_ratio_checker' => [
                'ফোন নম্বর দিয়ে সার্চ করুন।',
                'ডেলিভারি সাকসেস রেট / রেশিও দেখুন।',
                'কোয়ালিটি খারাপ হলে পার্সেল পাঠাবেন না।',
            ],
            'fake_order_check' => [
                'নম্বর দিয়ে ফেক অর্ডার ঝুঁকি চেক করুন।',
                'হিস্টোরি খারাপ হলে কনফার্ম করবেন না।',
                'পূর্ণ সুরক্ষায় OTP + ব্লক যোগ করুন।',
            ],
            'courier_checker' => [
                'বাংলাদেশি মোবাইল নম্বর দিন।',
                'Pathao / Steadfast / RedX হিস্টোরি দেখুন।',
                'অর্ডার কনফার্মের আগে সিদ্ধান্ত নিন।',
            ],
        ];

        return Inertia::render('Seo/KeywordIntent', [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'fraudCheck' => $payload['fraudCheck'] ?? [],
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'faqs' => $seoMeta['faqs'] ?? [],
            'showChecker' => true,
            'steps' => $stepsByKey[$seoKey] ?? [],
            'headline' => $seoMeta['prerender_h1'] ?? '',
            'lead' => $seoMeta['prerender_lead'] ?? '',
        ])->withViewData(['seo' => $seoMeta]);
    }

    private function renderCalculatorPage(
        Request $request,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
        string $seoPage,
        string $component,
        array $payloadKeys,
    ): Response {
        $payload = $landing->payload($request);
        $whatsapp = $landingSettings->adminWhatsapp();
        $seoMeta = $seo->forPage($seoPage);

        $props = [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'whatsappUrl' => $payload['whatsappUrl'] ?? null,
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                config('landing.whatsapp_default_message'),
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
        ];

        foreach ($payloadKeys as $prop => $payloadKey) {
            $props[$prop] = $payload[$payloadKey] ?? [];
        }

        return Inertia::render($component, $props)->withViewData(['seo' => $seoMeta]);
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
