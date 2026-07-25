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
            'facebook_page_cod_management',
            'about',
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

    public function faqHub(
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
            'faq',
            'Seo/FaqHub',
            'faq',
        );
    }

    public function faqQuestion(
        Request $request,
        string $slug,
        LandingPageService $landing,
        SeoMetaService $seo,
        LandingSettingsService $landingSettings,
    ): Response {
        $seoKey = $this->faqSeoKeyForSlug($slug);
        if ($seoKey === null) {
            abort(404);
        }

        return $this->renderSeoPage(
            $request,
            $landing,
            $seo,
            $landingSettings,
            $seoKey,
            'Seo/FaqQuestion',
            'faq',
        );
    }

    /**
     * Inventory-driven allowlist: only live planned_faq slugs.
     */
    private function faqSeoKeyForSlug(string $slug): ?string
    {
        $slug = trim($slug);
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }

        foreach (config('seo_keyword_inventory.entries', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['type'] ?? '') !== 'planned_faq') {
                continue;
            }
            if (($row['status'] ?? '') !== 'live') {
                continue;
            }
            if (($row['slug'] ?? '') !== $slug) {
                continue;
            }

            return 'faq_'.str_replace('-', '_', $slug);
        }

        return null;
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
                'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে /bd-fraud-checker বা এই পেজের ফ্রি টুল চালান।',
                'সাকসেস রেট/জোন দেখে সিদ্ধান্ত নিন—সবুজ কনফার্ম, হলুদ কল/OTP, লাল হোল্ড বা অগ্রিম চার্জ।',
                'চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন (/fake-order-protection)।',
                'কনফার্ম হলে /courier-auto-entry + ট্র্যাকিং নোটিফিকেশন—ম্যানুয়াল প্যানেল কপি-পেস্ট বাদ।',
            ],
            'fake_customer_check' => [
                'অর্ডার থেকে কাস্টমারের বাংলাদেশি মোবাইল নম্বর নিন (কনফার্মের আগে)।',
                'নিচের ফ্রি টুলে নম্বর দিয়ে Pathao / Steadfast / RedX হিস্টোরি ও সাকসেস রেট দেখুন।',
                'সবুজ/উচ্চ সাকসেস হলে কনফার্ম; হলুদ হলে কল বা OTP; লাল/কম সাকসেস হলে হোল্ড বা অগ্রিম চার্জ।',
                'বারবার ফেক আটকাতে Fake Order Protection (OTP, ব্ল্যাকলিস্ট) চালু রাখুন—তারপর কুরিয়ার অটো এন্ট্রি।',
            ],
            'bd_courier_ratio_checker' => [
                'অর্ডার থেকে মোবাইল নম্বর নিন (কনফার্মের আগে)।',
                'নিচের Ratio Checker টুলে নম্বর দিয়ে সাকসেস রেট ও রিটার্ন রেশিও দেখুন।',
                'সবুজ/উচ্চ রেট → কনফার্ম; হলুদ → কল/OTP; লাল → হোল্ড বা অগ্রিম চার্জ।',
                'বারবার ফেক আটকাতে /fake-order-protection — কনফার্মের পর /courier-auto-entry।',
            ],
            'fake_order_check' => [
                'কাস্টমারের বাংলাদেশি মোবাইল নম্বর নিন।',
                'নিচের Fake Order Check টুলে Pathao / Steadfast / RedX হিস্টোরি দেখুন।',
                'সাকসেস রেট খারাপ হলে কনফার্ম করবেন না—কল, OTP বা হোল্ড।',
                'পূর্ণ সুরক্ষায় /fake-order-protection চালু রাখুন; কনফার্মের পর /courier-auto-entry।',
            ],
            'courier_checker' => [
                'অর্ডার/মেসেজ থেকে বাংলাদেশি মোবাইল নম্বর নিন (কনফার্ম বা কুরিয়ার বুকিংয়ের আগে)।',
                'নিচের Courier Checker টুলে নম্বর দিয়ে Pathao / Steadfast / RedX হিস্টোরি ও সাকসেস রেট দেখুন।',
                'সবুজ/উচ্চ সাকসেস → কনফার্ম; হলুদ → কল বা OTP; লাল/কম সাকসেস → হোল্ড বা অগ্রিম চার্জ।',
                'বারবার ফেক আটকাতে /fake-order-protection চালু রাখুন—কনফার্মের পর /courier-auto-entry।',
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
