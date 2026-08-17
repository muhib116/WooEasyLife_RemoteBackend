<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_includes_seo_meta_in_html(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('Courier Fraud Checker', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome3')
            ->has('seo')
            ->where('seo.canonical_path', '/')
        );
    }

    public function test_pricing_includes_seo_props(): void
    {
        $response = $this->get('/pricing');

        $response->assertOk();
        $response->assertSee('name="description"', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pricing/Index')
            ->has('seo')
            ->where('seo.canonical_path', '/pricing')
        );
    }

    public function test_bd_fraud_checker_page_renders(): void
    {
        $response = $this->get('/bd-fraud-checker');

        $response->assertOk();
        $response->assertSee('Courier Fraud Checker BD', false);
        $response->assertSee('WordPress', false);
        $response->assertSee('WooCommerce', false);
        $response->assertSee('name="description"', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/BdFraudChecker')
            ->has('fraudCheck')
            ->has('seo')
            ->where('seo.canonical_path', '/bd-fraud-checker')
            ->where('seo.ssr_fraud_checker', true)
        );

        $faqs = config('seo.pages.bd_fraud_checker.faqs', []);
        $this->assertGreaterThanOrEqual(10, count($faqs));
        $this->assertTrue(
            collect($faqs)->contains(fn (array $faq) => str_contains((string) ($faq['q'] ?? ''), 'WordPress')),
        );
    }

    public function test_bd_fraud_checker_ssr_exposes_phone_form_in_initial_html(): void
    {
        $response = $this->get('/bd-fraud-checker');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('id="seo-prerender"', $html);
        $this->assertStringContainsString('id="ssr-fraud-phone"', $html);
        $this->assertStringContainsString('name="phone"', $html);
        $this->assertStringContainsString('type="tel"', $html);
        $this->assertStringContainsString('for="ssr-fraud-phone"', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*>/i', $html);
        $this->assertStringContainsString('Pathao', $html);
        $this->assertStringContainsString('Steadfast', $html);
        $this->assertStringContainsString('RedX', $html);
        $this->assertStringContainsString('"@type":"HowTo"', $html);
        $this->assertStringNotContainsString('"@type":"WebApplication"', $html);
        $this->assertStringNotContainsString('"@type":"SoftwareApplication"', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);
    }

    public function test_bn_fraud_money_pages_link_to_faqs_calculators_and_pricing(): void
    {
        $paths = [
            '/faq',
            '/faq/courier-success-rate-kivabe-bujhbo',
            '/faq/success-rate-kom-hole-ki-korbo',
            '/faq/cod-order-otp-kokhon',
            '/faq/woocommerce-customer-blacklist',
            '/faq/duplicate-cod-order-block',
            '/faq/customer-delivery-history-check',
            '/faq/customer-fraud-score-ki',
            '/faq/cod-return-loss-hisab',
            '/return-loss-calculator',
            '/ads-roas-calculator',
            '/pricing',
        ];

        $fraudChecker = $this->get('/bd-fraud-checker');
        $fakeCustomerCheck = $this->get('/fake-customer-check');

        $fraudChecker->assertOk();
        $fakeCustomerCheck->assertOk();

        $combinedHtml = $fraudChecker->getContent().$fakeCustomerCheck->getContent();
        foreach ($paths as $path) {
            $this->assertStringContainsString(
                'href="'.$path.'"',
                $combinedHtml,
                "Expected fraud money pages to render an internal link to {$path}"
            );
        }

        $fakeCustomerCheck->assertSee('কোনো আইনি বা চূড়ান্ত “fraud verdict” নয়', false);
        $fakeCustomerCheck->assertSee('/bd-fraud-checker', false);
        $fakeCustomerCheck->assertSee('/faq/customer-fraud-score-ki', false);
    }

    public function test_courier_fraud_pages_are_differentiated_and_linked(): void
    {
        $pages = [
            '/pathao-fraud-check' => ['Pathao', '/pathao-courier-guide'],
            '/steadfast-fraud-check' => ['Steadfast', '/steadfast-integration'],
            '/redx-fraud-check' => ['RedX', '/redx-courier-guide'],
        ];

        $sharedFaqLinks = [
            '/faq',
            '/faq/courier-success-rate-kivabe-bujhbo',
            '/faq/success-rate-kom-hole-ki-korbo',
            '/faq/customer-delivery-history-check',
            '/bd-fraud-checker',
            '/return-loss-calculator',
            '/pricing',
        ];

        foreach ($pages as $path => [$courier, $apiGuide]) {
            $response = $this->get($path);
            $response->assertOk();
            $html = $response->getContent();

            $response->assertSee($courier, false);
            $this->assertStringContainsString('href="'.$apiGuide.'"', $html, "{$path} should link its courier API guide");

            foreach ($sharedFaqLinks as $link) {
                $this->assertStringContainsString(
                    'href="'.$link.'"',
                    $html,
                    "Expected {$path} to link to {$link}"
                );
            }
        }
    }

    public function test_return_loss_calculator_ssr_exposes_number_inputs(): void
    {
        $response = $this->get('/return-loss-calculator');
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('id="ssr-calculator"', $html);
        $this->assertStringContainsString('name="daily_orders"', $html);
        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('name="return_rate"', $html);
    }

    public function test_faq_hub_and_question_pages_render(): void
    {
        $hub = $this->get('/faq');
        $hub->assertOk();
        $hubHtml = $hub->getContent();
        $this->assertStringContainsString('id="seo-prerender"', $hubHtml);
        $this->assertStringContainsString('FAQ', $hubHtml);
        $this->assertStringContainsString('href="/faq/courier-success-rate-kivabe-bujhbo"', $hubHtml);
        $this->assertStringContainsString('"@type":"FAQPage"', $hubHtml);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $hubHtml);

        $question = $this->get('/faq/courier-success-rate-kivabe-bujhbo');
        $question->assertOk();
        $qHtml = $question->getContent();
        $this->assertStringContainsString('সাকসেস রেট', $qHtml);
        $this->assertStringContainsString('<ol>', $qHtml);
        $this->assertStringContainsString('href="/faq/success-rate-kom-hole-ki-korbo"', $qHtml);
        $this->assertStringContainsString('রেট কম হলে কী করবেন', $qHtml);
        $this->assertStringNotContainsString('হোমরিটার্ন', $qHtml);
        $this->assertStringNotContainsString('হলুদহোম', $qHtml);
        $this->assertStringContainsString('"@type":"FAQPage"', $qHtml);
        $this->assertStringContainsString('href="/faq"', $qHtml);

        $this->get('/faq/not-a-real-slug')->assertNotFound();
    }

    public function test_fake_order_protection_page_renders(): void
    {
        $response = $this->get('/fake-order-protection');

        $response->assertOk();
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/fake-order-protection', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/FakeOrderProtection')
            ->where('seo.canonical_path', '/fake-order-protection')
            ->has('seo.faqs')
        );
    }

    public function test_english_fake_order_protection_page_renders(): void
    {
        $response = $this->get('/en/fake-order-protection');

        $response->assertOk();
        $response->assertSee('Fake Order Protection', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('hreflang="bn-BD"', false);
        $response->assertSee('/fake-order-protection', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnFakeOrderProtection')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/en/fake-order-protection')
            ->where('seo.html_lang', 'en')
        );
    }

    public function test_return_loss_calculator_page_renders(): void
    {
        $response = $this->get('/return-loss-calculator');

        $response->assertOk();
        $response->assertSee('রিটার্ন লস', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/return-loss-calculator', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/ReturnLossCalculator')
            ->has('roiCalculator')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/return-loss-calculator')
        );
    }

    public function test_english_return_loss_calculator_page_renders(): void
    {
        $response = $this->get('/en/return-loss-calculator');

        $response->assertOk();
        $response->assertSee('Return Loss', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('hreflang="bn-BD"', false);
        $response->assertSee('/return-loss-calculator', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnReturnLossCalculator')
            ->has('roiCalculator.inputs.daily_orders')
            ->has('roiScenarios')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/en/return-loss-calculator')
            ->where('seo.html_lang', 'en')
        );
    }

    public function test_courier_charge_calculator_page_renders(): void
    {
        $response = $this->get('/courier-charge-calculator');

        $response->assertOk();
        $response->assertSee('কুরিয়ার চার্জ', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/courier-charge-calculator', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/CourierChargeCalculator')
            ->has('courierChargeCalculator.couriers.pathao')
            ->has('courierChargeCalculator.zones.dhaka')
            ->has('courierChargeCalculator.official_links')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/courier-charge-calculator')
        );
    }

    public function test_ads_roas_calculator_page_renders(): void
    {
        $response = $this->get('/ads-roas-calculator');

        $response->assertOk();
        $response->assertSee('ROAS', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/ads-roas-calculator', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/AdsRoasCalculator')
            ->has('adsRoasCalculator.inputs.ad_spend')
            ->has('adsRoasCalculator.inputs.fake_cancel_rate')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/ads-roas-calculator')
        );
    }

    public function test_english_ads_roas_calculator_page_renders(): void
    {
        $response = $this->get('/en/ads-roas-calculator');

        $response->assertOk();
        $response->assertSee('ROAS', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('hreflang="bn-BD"', false);
        $response->assertSee('/ads-roas-calculator', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnAdsRoasCalculator')
            ->has('adsRoasCalculator.inputs.ad_spend')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/en/ads-roas-calculator')
            ->where('seo.html_lang', 'en')
        );
    }

    public function test_english_courier_charge_calculator_page_renders(): void
    {
        $response = $this->get('/en/courier-charge-calculator');

        $response->assertOk();
        $response->assertSee('Courier', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('hreflang="bn-BD"', false);
        $response->assertSee('/courier-charge-calculator', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnCourierChargeCalculator')
            ->has('courierChargeCalculator.couriers.pathao')
            ->has('courierChargeCalculator.zones.dhaka')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/en/courier-charge-calculator')
            ->where('seo.html_lang', 'en')
        );
    }

    public function test_courier_auto_entry_page_renders(): void
    {
        $response = $this->get('/courier-auto-entry');

        $response->assertOk();
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/courier-auto-entry', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/CourierAutoEntry')
            ->where('seo.canonical_path', '/courier-auto-entry')
            ->has('seo.faqs')
        );

        $faqs = collect(config('seo.pages.courier_auto_entry.faqs', []));
        $this->assertTrue(
            $faqs->contains(fn (array $faq) => str_contains((string) ($faq['q'] ?? ''), 'পার্সেল নোট')),
            'Courier auto-entry SEO FAQs should mention parcel note history.',
        );
    }

    public function test_english_courier_auto_entry_page_renders(): void
    {
        $response = $this->get('/en/courier-auto-entry');

        $response->assertOk();
        $response->assertSee('Courier Auto Entry', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('hreflang="bn-BD"', false);
        $response->assertSee('/courier-auto-entry', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnCourierAutoEntry')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/en/courier-auto-entry')
            ->where('seo.html_lang', 'en')
        );

        $faqs = collect(config('seo.pages.en_courier_auto_entry.faqs', []));
        $this->assertTrue(
            $faqs->contains(fn (array $faq) => str_contains(strtolower((string) ($faq['q'] ?? '')), 'parcel note')),
            'English courier auto-entry SEO FAQs should mention parcel note history.',
        );
    }

    public function test_steadfast_return_hub_page_renders(): void
    {
        $response = $this->get('/steadfast-return-hub');

        $response->assertOk();
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/steadfast-return-hub', false);
        // SSR long-form in #seo-prerender (not Vue-only)
        $response->assertSee('id="seo-prerender"', false);
        $response->assertSee('Confirm cancel', false);
        $response->assertSee('Ask to resend', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/SteadfastReturnHub')
            ->where('seo.canonical_path', '/steadfast-return-hub')
            ->has('seo.faqs')
            ->has('seo.content_sections')
        );

        $sections = config('seo_content.steadfast_return_hub', []);
        $this->assertGreaterThanOrEqual(10, count($sections));
    }

    public function test_english_steadfast_return_hub_page_renders(): void
    {
        $response = $this->get('/en/steadfast-return-hub');

        $response->assertOk();
        $response->assertSee('SteadFast Return', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('/steadfast-return-hub', false);
        $response->assertSee('id="seo-prerender"', false);
        $response->assertSee('Confirm cancel', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnSteadfastReturnHub')
            ->where('seo.canonical_path', '/en/steadfast-return-hub')
            ->where('seo.html_lang', 'en')
            ->has('seo.faqs')
            ->has('seo.content_sections')
        );
    }

    public function test_woocommerce_facebook_messenger_page_renders(): void
    {
        $response = $this->get('/woocommerce-facebook-messenger');

        $response->assertOk();
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/woocommerce-facebook-messenger', false);
        $response->assertSee('id="seo-prerender"', false);
        $response->assertSee('messenger-vs-bubble.jpg', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/WoocommerceFacebookMessenger')
            ->where('seo.canonical_path', '/woocommerce-facebook-messenger')
            ->has('seo.faqs')
            ->has('seo.content_sections')
        );

        $sections = config('seo_content.woocommerce_facebook_messenger', []);
        $this->assertGreaterThanOrEqual(10, count($sections));
        $this->assertGreaterThanOrEqual(8, count(config('seo.pages.woocommerce_facebook_messenger.faqs', [])));
    }

    public function test_english_woocommerce_facebook_messenger_page_renders(): void
    {
        $response = $this->get('/en/woocommerce-facebook-messenger');

        $response->assertOk();
        $response->assertSee('Messenger', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('/woocommerce-facebook-messenger', false);
        $response->assertSee('id="seo-prerender"', false);
        $response->assertSee('messenger-cod-flow.jpg', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnWoocommerceFacebookMessenger')
            ->where('seo.canonical_path', '/en/woocommerce-facebook-messenger')
            ->where('seo.html_lang', 'en')
            ->has('seo.faqs')
            ->has('seo.content_sections')
        );
    }

    public function test_fraudbd_alternative_page_renders(): void
    {
        $response = $this->get('/fraudbd-alternative');

        $response->assertOk();
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('/en/fraudbd-alternative', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/FraudBdAlternative')
            ->where('seo.canonical_path', '/fraudbd-alternative')
            ->has('seo.breadcrumbs')
            ->has('seo.faqs')
        );
    }

    public function test_english_fraudbd_alternative_page_renders(): void
    {
        $response = $this->get('/en/fraudbd-alternative');

        $response->assertOk();
        $response->assertSee('FraudBD', false);
        $response->assertSee('lang="en"', false);
        $response->assertSee('hreflang="bn-BD"', false);
        $response->assertSee('/fraudbd-alternative', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/EnFraudBdAlternative')
            ->where('seo.canonical_path', '/en/fraudbd-alternative')
            ->where('seo.html_lang', 'en')
            ->has('seo.faqs')
        );
    }

    public function test_fraud_bd_alternative_hyphen_redirects(): void
    {
        $this->get('/fraud-bd-alternative')->assertRedirect('/fraudbd-alternative');
        $this->get('/en/fraud-bd-alternative')->assertRedirect('/en/fraudbd-alternative');
    }

    public function test_woocommerce_bangladesh_cluster_pages_render(): void
    {
        $bnPaths = [
            '/woocommerce-bangladesh' => 'woocommerce_bangladesh',
            '/steadfast-integration' => 'steadfast_integration',
            '/pathao-courier-guide' => 'pathao_courier_guide',
            '/redx-courier-guide' => 'redx_courier_guide',
            '/woocommerce-mobile-app' => 'woocommerce_mobile_app',
            '/customer-verification' => 'customer_verification',
            '/cod-return-reduction' => 'cod_return_reduction',
            '/woocommerce-notifications' => 'woocommerce_notifications',
            '/facebook-ads-for-woocommerce' => 'facebook_ads_for_woocommerce',
            '/facebook-page-cod-management' => 'facebook_page_cod_management',
            '/about' => 'about',
        ];

        foreach ($bnPaths as $path => $key) {
            $response = $this->get($path);
            $response->assertOk();
            $response->assertSee('hreflang="en"', false);
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Seo/ClusterGuide')
                ->where('seo.canonical_path', $path)
                ->has('seo.content_sections')
                ->has('seo.faqs')
                ->has('seo.cluster_links')
            );

            $seo = app(\App\Services\SeoMetaService::class)->forPage($key);
            $this->assertGreaterThan(
                200,
                $this->seoPrerenderTokenCount($seo),
                "{$key} should exceed thin-content threshold"
            );
        }

        $hub = $this->get('/woocommerce-bangladesh');
        $hub->assertSee('অংশ', false);
        $hub->assertSee('/bd-fraud-checker', false);
        $hub->assertSee('/fake-order-protection', false);
        $hub->assertSee('/courier-auto-entry', false);

        $pillar = app(\App\Services\SeoMetaService::class)->forPage('woocommerce_bangladesh');
        $this->assertGreaterThanOrEqual(
            30,
            count($pillar['content_sections'] ?? []),
            'Pillar must include all 30 guide parts (+ intro/quick sections)'
        );

        $aiFlagged = [
            'ki_vabe_fake_order_atkabo',
            'redx_courier_guide',
            'woocommerce_bangladesh',
        ];
        $seoService = app(\App\Services\SeoMetaService::class);
        foreach ($aiFlagged as $page) {
            $seo = $seoService->forPage($page);
            $headings = collect($seo['content_sections'] ?? [])->pluck('heading')->map(fn ($h) => (string) $h);
            $this->assertTrue(
                $headings->contains(fn ($h) => str_contains($h, 'দ্রুত') || str_contains(strtolower($h), 'quick')),
                "{$page} needs a visible quick-answer section for Semrush AI content checks"
            );
            $listItems = collect($seo['content_sections'] ?? [])->sum(fn ($s) => count($s['list'] ?? []));
            $this->assertGreaterThanOrEqual(
                4,
                $listItems,
                "{$page} needs extractable takeaway bullets for AI content optimization"
            );
        }
    }

    public function test_keyword_intent_suppresses_duplicate_longform_dump(): void
    {
        $this->get('/ki-vabe-fake-order-atkabo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seo/KeywordIntent')
                ->has('seo.content_sections')
            );
    }

    public function test_english_woocommerce_bangladesh_cluster_pages_render(): void
    {
        $enPaths = [
            '/en/woocommerce-bangladesh',
            '/en/steadfast-integration',
            '/en/pathao-courier-guide',
            '/en/redx-courier-guide',
            '/en/woocommerce-mobile-app',
            '/en/customer-verification',
            '/en/cod-return-reduction',
            '/en/woocommerce-notifications',
            '/en/facebook-ads-for-woocommerce',
            '/en/facebook-page-cod-management',
            '/en/about',
        ];

        foreach ($enPaths as $path) {
            $response = $this->get($path);
            $response->assertOk();
            $response->assertSee('lang="en"', false);
            $response->assertSee('hreflang="bn-BD"', false);
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Seo/ClusterGuide')
                ->where('seo.canonical_path', $path)
                ->where('seo.html_lang', 'en')
                ->has('seo.content_sections')
            );
        }
    }

    public function test_woocommerce_bangladesh_cluster_is_seo_complete(): void
    {
        $seoService = app(\App\Services\SeoMetaService::class);
        $pillar = $seoService->forPage('woocommerce_bangladesh');
        $graph = collect($pillar['json_ld']['@graph'] ?? []);

        $article = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Article');
        $this->assertNotNull($article, 'Pillar should emit Article JSON-LD');
        $this->assertNotEmpty($article['datePublished'] ?? null);
        $this->assertNotEmpty($article['dateModified'] ?? null);

        $faq = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'FAQPage');
        $this->assertNotNull($faq, 'Pillar should emit FAQPage JSON-LD');

        $toc = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'ItemList');
        $this->assertNotNull($toc, 'Pillar should emit ItemList TOC JSON-LD');
        $this->assertGreaterThanOrEqual(30, (int) ($toc['numberOfItems'] ?? 0));

        $firstToc = $toc['itemListElement'][0] ?? null;
        $this->assertIsArray($firstToc);
        $this->assertStringContainsString('অংশ ১/৩০', (string) ($firstToc['name'] ?? ''));
        $this->assertStringEndsWith('#guide-section-1', (string) ($firstToc['url'] ?? ''));
        // Quick-answer must stay out of TOC positions so Vue #guide-section-1 stays on part 1.
        foreach ($toc['itemListElement'] as $item) {
            $name = mb_strtolower((string) ($item['name'] ?? ''));
            $this->assertFalse(
                str_contains($name, 'দ্রুত') || str_contains($name, 'quick'),
                'Quick-answer sections must not occupy ItemList TOC anchors'
            );
        }

        $headings = collect($pillar['content_sections'] ?? [])->pluck('heading');
        $this->assertTrue($headings->contains(fn ($h) => str_contains((string) $h, 'দ্রুত')));
        $this->assertSame(
            0,
            $headings->filter(fn ($h) => (bool) preg_match('/^অংশ\s+\d+\/৩০\)$/u', (string) $h))->count(),
            'BN pillar headings must not be bare part numbers'
        );

        $bn = $this->get('/woocommerce-bangladesh');
        $bn->assertOk();
        $bn->assertSee('"@type":"Article"', false);
        $bn->assertSee('datePublished', false);
        $bn->assertSee('seo-prerender', false);
        $bn->assertSee('/steadfast-integration', false);
        $bn->assertSee($pillar['faqs'][0]['a'] ?? 'missing-faq', false);
        $bn->assertDontSee('$$\text{Net Profit}', false);
        $bn->assertDontSee('$$\text{CAC}', false);
        $bn->assertSee('/images/seo/cluster/cod-loss-math.jpg', false);
        $bn->assertSee('/images/seo/cluster/pixel-vs-capi.jpg', false);
        $bn->assertSee('সফল ডেলিভারি vs রিটার্ন', false);

        $en = $this->get('/en/woocommerce-bangladesh');
        $en->assertOk();
        $en->assertSee('lang="en"', false);
        $enSeo = $seoService->forPage('en_woocommerce_bangladesh');
        $this->assertSame('en', $enSeo['html_lang']);
        $enArticle = collect($enSeo['json_ld']['@graph'] ?? [])
            ->first(fn (array $node) => ($node['@type'] ?? null) === 'Article');
        $this->assertNotNull($enArticle);
        $this->assertSame('en', $enArticle['inLanguage'] ?? null);

        $llms = $this->get('/llms.txt');
        $llms->assertOk();
        $llms->assertSee('/en/steadfast-integration', false);
        $llms->assertSee('/en/facebook-ads-for-woocommerce', false);
        $llms->assertSee('/facebook-page-cod-management', false);
        $llms->assertSee('/en/facebook-page-cod-management', false);
    }

    public function test_facebook_page_cod_management_pillar_is_seo_complete(): void
    {
        $seoService = app(\App\Services\SeoMetaService::class);
        $pillar = $seoService->forPage('facebook_page_cod_management');
        $graph = collect($pillar['json_ld']['@graph'] ?? []);

        $this->assertTrue((bool) ($pillar['is_pillar'] ?? false));
        $this->assertGreaterThan(
            200,
            $this->seoPrerenderTokenCount($pillar),
            'Facebook page COD pillar should exceed thin-content threshold'
        );

        $article = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Article');
        $this->assertNotNull($article, 'Pillar should emit Article JSON-LD');
        $this->assertNotEmpty($article['datePublished'] ?? null);
        $this->assertNotEmpty($article['dateModified'] ?? null);

        $faq = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'FAQPage');
        $this->assertNotNull($faq, 'Pillar should emit FAQPage JSON-LD');

        $toc = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'ItemList');
        $this->assertNotNull($toc, 'Pillar should emit ItemList TOC JSON-LD');
        $this->assertGreaterThanOrEqual(10, (int) ($toc['numberOfItems'] ?? 0));

        $bn = $this->get('/facebook-page-cod-management');
        $bn->assertOk();
        $bn->assertSee('hreflang="en"', false);
        $bn->assertSee('"@type":"Article"', false);
        $bn->assertSee('seo-prerender', false);
        $bn->assertSee('/bd-fraud-checker', false);
        $bn->assertSee('/courier-auto-entry', false);
        $bn->assertSee('/images/seo/cluster/omnichannel-inbox.jpg', false);
        $bn->assertSee($pillar['faqs'][0]['a'] ?? 'missing-faq', false);

        $en = $this->get('/en/facebook-page-cod-management');
        $en->assertOk();
        $en->assertSee('lang="en"', false);
        $en->assertSee('hreflang="bn-BD"', false);
        $enSeo = $seoService->forPage('en_facebook_page_cod_management');
        $this->assertSame('en', $enSeo['html_lang']);
        $this->assertTrue((bool) ($enSeo['is_pillar'] ?? false));
    }

    public function test_about_founder_page_has_person_image_schema(): void
    {
        $seo = app(\App\Services\SeoMetaService::class)->forPage('about');
        $graph = collect($seo['json_ld']['@graph'] ?? []);

        $person = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Person');
        $this->assertNotNull($person);
        $this->assertSame('Muhibbullah Ansary', $person['name'] ?? null);
        $this->assertStringContainsString(
            'founder-headshot',
            (string) (is_array($person['image'] ?? null) ? ($person['image']['url'] ?? '') : ($person['image'] ?? ''))
        );
        $this->assertContains('https://www.linkedin.com/in/dev-muhib', $person['sameAs'] ?? []);
        $this->assertContains('https://www.facebook.com/muhib116', $person['sameAs'] ?? []);
        $this->assertContains('https://www.instagram.com/muhibbullah611/', $person['sameAs'] ?? []);
        $this->assertSame('Founder & CEO, WPSaleHub · Creator of WooEasyLife', $person['jobTitle'] ?? null);
        $this->assertStringContainsString('WooEasyLife', (string) ($person['description'] ?? ''));
        $this->assertContains('WooEasyLife', $person['knowsAbout'] ?? []);
        $this->assertIsArray($person['image'] ?? null);
        $this->assertStringContainsString('founder-headshot', (string) ($person['image']['contentUrl'] ?? $person['image']['url'] ?? ''));

        $org = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Organization');
        $this->assertNotNull($org);
        $this->assertSame('WPSaleHub', $org['name'] ?? null);
        $this->assertNotNull($org['founder'] ?? null);
        $this->assertNotNull($org['brand'] ?? null);
        $this->assertContains('WooEasyLife', $org['alternateName'] ?? []);
        $this->assertContains('https://www.facebook.com/wooeasylife', $org['sameAs'] ?? []);
        $this->assertNotContains('https://www.linkedin.com/in/dev-muhib', $org['sameAs'] ?? []);

        $brand = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Brand');
        $this->assertNotNull($brand);
        $this->assertSame('WooEasyLife', $brand['name'] ?? null);
        $this->assertNotNull($brand['founder'] ?? null);

        $product = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Product');
        $this->assertNull($product, 'About must not emit bare Product (GSC Product enhancement errors)');

        $faq = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'FAQPage');
        $this->assertNull($faq, 'About must not emit FAQPage (GSC FAQ enhancement not eligible)');

        $aboutPage = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'AboutPage');
        $this->assertNotNull($aboutPage);
        $this->assertStringEndsWith('#webpage', (string) ($aboutPage['@id'] ?? ''));
        $this->assertNull($graph->first(fn (array $node) => ($node['@id'] ?? null) === ($seo['canonical'] ?? '').'#article'));
        $this->assertSame('+8801770989591', $person['telephone'] ?? null);
        $this->assertIsArray($person['image'] ?? null);
        $this->assertStringContainsString('founder-headshot', (string) ($person['image']['url'] ?? ''));

        $crumb = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'BreadcrumbList');
        $this->assertNotNull($crumb, 'About should keep BreadcrumbList');

        $this->assertSame(1200, (int) ($seo['og_image_width'] ?? 0));
        $this->assertSame(630, (int) ($seo['og_image_height'] ?? 0));
        $this->assertStringContainsString('founder-headshot-og.jpg', (string) ($seo['og_image'] ?? ''));

        $bn = $this->get('/about');
        $bn->assertOk();
        $bn->assertSee('/images/seo/about/founder-headshot.jpg', false);
        $bn->assertSee('Muhibbullah Ansary', false);
        $bn->assertSee('Founder & CEO, WPSaleHub · Creator of WooEasyLife', false);
        $bn->assertSee('dev.muhibbullah@gmail.com', false);
        $bn->assertSee('Automating Business. Empowering People.', false);
        $bn->assertSee('WPSaleHub হলো একটি automation-first technology company', false);
        $bn->assertSee('WooCommerce মার্চেন্টদের জন্য তৈরি', false);
        $bn->assertSee('WooEasyLife বা WPSaleHub-এর owner কে?', false);
        $bn->assertDontSee('https:Bangla home', false);
        $bn->assertDontSee('/images/seo/about/founder-work.png', false);
        $bn->assertDontSee('/images/seo/about/founder-hero.png', false);
        // First about photo in prerender HTML must be the Person headshot (not product/workspace shots).
        $bnHtml = $bn->getContent();
        $firstAboutImg = null;
        if (preg_match('/<img[^>]+src="([^"]*seo\/about\/[^"]+)"/', $bnHtml, $m)) {
            $firstAboutImg = $m[1];
        }
        $this->assertNotNull($firstAboutImg);
        $this->assertStringContainsString('founder-headshot.jpg', $firstAboutImg);

        $en = $this->get('/en/about');
        $en->assertOk();
        $en->assertSee('Muhibbullah Ansary', false);
        $en->assertSee('Founder & CEO, WPSaleHub · Creator of WooEasyLife', false);
        $en->assertSee('/images/seo/about/founder-headshot.jpg', false);
        $en->assertSee('WooCommerce merchant solution', false);
        $en->assertSee('About WPSaleHub | WooEasyLife founder Muhibbullah Ansary', false);
        $en->assertSee('Who owns WooEasyLife or WPSaleHub?', false);
        $en->assertSee('The fastest way is email', false);
        $en->assertDontSee('https:Bangla home', false);
        $en->assertDontSee('/images/seo/about/founder-work.png', false);
        $enHtml = $en->getContent();
        $firstEnAboutImg = null;
        if (preg_match('/<img[^>]+src="([^"]*seo\/about\/[^"]+)"/', $enHtml, $m)) {
            $firstEnAboutImg = $m[1];
        }
        $this->assertNotNull($firstEnAboutImg);
        $this->assertStringContainsString('founder-headshot.jpg', $firstEnAboutImg);

        $llms = $this->get('/llms.txt');
        $llms->assertOk();
        $llms->assertSee('founder-headshot.jpg', false);
        $llms->assertSee('Owner / Founder & CEO of WPSaleHub', false);
    }

    public function test_home_prerenders_h1_for_crawlers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="seo-prerender"', false);
        $response->assertSee('<h1>', false);
        $response->assertSee('lang="bn-BD"', false);
        $response->assertSee('/images/seo/og-default.jpg', false);
        $response->assertSee('<noscript>', false);
    }

    public function test_tool_page_includes_breadcrumb_schema(): void
    {
        $response = $this->get('/bd-fraud-checker');

        $response->assertOk();
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('id="seo-prerender"', false);
    }

    public function test_marketing_404_is_helpful(): void
    {
        $response = $this->get('/this-page-definitely-does-not-exist-seo');

        $response->assertNotFound();
        $response->assertSee('পেজ পাওয়া যায়নি', false);
        $response->assertSee('/bd-fraud-checker', false);
        $response->assertSee('noindex', false);
    }

    public function test_competitor_keyword_intent_pages(): void
    {
        foreach ([
            '/ki-vabe-fake-order-atkabo',
            '/en/ki-vabe-fake-order-atkabo',
            '/fake-customer-check',
            '/en/fake-customer-check',
            '/bd-courier-ratio-checker',
            '/fake-order-check',
            '/courier-checker',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('Courier Fraud Checker BD', false);
        $home->assertSee('কিভাবে ফেক অর্ডার আটকাবো', false);
    }

    public function test_blog_and_courier_intent_pages(): void
    {
        $this->get('/blog')->assertOk();
        // /blog/fake-order-komano is not guaranteed in local DBs — skipped on purpose.
        $this->get('/blog/ki-vabe-fake-order-atkabo')->assertOk();
        $this->get('/pathao-fraud-check')->assertOk();
        $this->get('/steadfast-fraud-check')->assertOk();
        $this->get('/redx-fraud-check')->assertOk();
    }

    public function test_steadfast_fraud_check_pillar_is_seo_complete(): void
    {
        $seoService = app(\App\Services\SeoMetaService::class);
        $pillar = $seoService->forPage('steadfast_fraud_check');
        $graph = collect($pillar['json_ld']['@graph'] ?? []);

        $this->assertTrue((bool) ($pillar['is_pillar'] ?? false));
        $this->assertSame('Muhibbullah Ansary', $pillar['author_name'] ?? null);
        $this->assertNotEmpty($pillar['last_updated_label'] ?? null);
        $this->assertNotEmpty($pillar['honesty_line'] ?? null);
        $this->assertNotEmpty($pillar['external_links'] ?? []);
        $this->assertIsArray($pillar['trust_signals'] ?? null);
        $this->assertNotEmpty($pillar['trust_signals']['examples'] ?? []);
        $this->assertNotEmpty($pillar['trust_signals']['cannot_do'] ?? []);
        $this->assertNotEmpty($pillar['trust_signals']['decision_tips'] ?? []);

        $article = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Article');
        $this->assertNotNull($article, 'SteadFast pillar should emit Article JSON-LD');
        $this->assertSame('2026-07-30', $article['datePublished'] ?? null);
        $this->assertSame('2026-07-30', $article['dateModified'] ?? null);
        $this->assertSame('SteadFast Fraud Check', $article['articleSection'] ?? null);

        $faq = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'FAQPage');
        $this->assertNotNull($faq, 'SteadFast pillar should emit FAQPage JSON-LD');
        $faqBlob = json_encode($faq, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('better-informed decision', (string) $faqBlob);
        $this->assertStringContainsString('does not guarantee', (string) $faqBlob);

        $toc = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'ItemList');
        $this->assertNotNull($toc, 'SteadFast pillar should emit ItemList TOC JSON-LD');
        $this->assertGreaterThanOrEqual(8, (int) ($toc['numberOfItems'] ?? 0));
        $firstToc = $toc['itemListElement'][0] ?? null;
        $this->assertIsArray($firstToc);
        $this->assertStringEndsWith('#guide-section-1', (string) ($firstToc['url'] ?? ''));

        $response = $this->get('/steadfast-fraud-check');
        $response->assertOk();
        $response->assertSee('"@type":"Article"', false);
        $response->assertSee('datePublished', false);
        $response->assertSee('better-informed decision', false);
        $response->assertSee('https://steadfast.com.bd/pricing', false);
        $response->assertSee('/images/seo/cluster/fraud-layers.jpg', false);
        $response->assertSee('/images/seo/cluster/cod-loss-math.jpg', false);
        $response->assertSee($pillar['faqs'][0]['q'] ?? 'missing-faq', false);
        $response->assertDontSee('aggregateRating', false);
        $response->assertDontSee('AggregateRating', false);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Seo/CourierIntent')
            ->where('seo.canonical_path', '/steadfast-fraud-check')
            ->where('seo.is_pillar', true)
            ->where('seo.author_name', 'Muhibbullah Ansary')
            ->has('seo.external_links')
            ->has('seo.honesty_line')
            ->has('seo.last_updated_label')
            ->has('seo.trust_signals.examples')
            ->has('seo.trust_signals.cannot_do')
            ->has('seo.trust_signals.decision_tips')
        );
    }

    public function test_english_blog_post_does_not_inherit_bn_blog_hub_prerender(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-hreflang-'.uniqid().'@example.com',
            'phone' => '01700000111',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        BlogPost::create([
            'title' => 'COD Fraud Checker Monthly Savings EN',
            'slug' => 'cod-fraud-checker-monthly-savings-en',
            'locale' => 'en',
            'status' => 'published',
            'meta_description' => 'English guide on monthly COD savings from fraud checks in Bangladesh.',
            'body_html' => '<p>English sellers can cut monthly COD return loss with courier history checks before confirm.</p>',
            'published_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->get('/blog/cod-fraud-checker-monthly-savings-en');
        $response->assertOk();
        $html = $response->getContent();

        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="x-default"', false);
        $response->assertDontSee('hreflang="bn-BD"', false);
        $response->assertDontSee('hreflang="en" href="'.url('/en/blog').'"', false);
        $this->assertStringContainsString('lang="en"', $html);
        // BN blog hub long-form must never pollute EN post prerender (Semrush hreflang mismatch).
        $this->assertStringNotContainsString('WooEasyLife ব্লগ বাংলাদেশি WooCommerce', $html);
        $this->assertStringNotContainsString('এই ব্লগে কী ধরনের গাইড পাবেন', $html);
        $this->assertStringNotContainsString('ব্লগ, FAQ হাব ও টুল একসাথে', $html);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Blog/Show')
            ->where('seo.html_lang', 'en')
            ->where('seo.canonical_path', '/blog/cod-fraud-checker-monthly-savings-en')
            ->where('seo.content_sections', [])
        );
    }

    public function test_blog_post_title_differs_from_h1_when_meta_title_matches(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-dupe-'.uniqid().'@example.com',
            'phone' => '01700000112',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        BlogPost::create([
            'title' => 'ফেক অর্ডার কীভাবে কমাবেন? WooCommerce মার্চেন্টদের জন্য সম্পূর্ণ গাইড',
            'slug' => 'fake-order-reduction-guide',
            'locale' => 'bn',
            'status' => 'published',
            // Merchant set meta_title identical to the title (Semrush duplicate H1/title cause).
            'meta_title' => 'ফেক অর্ডার কীভাবে কমাবেন? WooCommerce মার্চেন্টদের জন্য সম্পূর্ণ গাইড',
            'meta_description' => 'ফেক অর্ডার কমানোর কার্যকর কৌশল WooCommerce সেলারদের জন্য।',
            'body_html' => '<p>অর্ডার কনফার্মের আগে কুরিয়ার হিস্টোরি যাচাই করুন।</p>',
            'published_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->get('/blog/fake-order-reduction-guide');
        $response->assertOk();
        $html = $response->getContent();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Blog/Show')
            ->where('seo.title', fn ($title) => $title !== $page->toArray()['props']['seo']['prerender_h1'])
            ->where('seo.title', 'ফেক অর্ডার কীভাবে কমাবেন? WooCommerce মার্চেন্টদের জন্য সম্পূর্ণ গাইড | WooEasyLife ব্লগ')
            ->where('seo.prerender_h1', 'ফেক অর্ডার কীভাবে কমাবেন? WooCommerce মার্চেন্টদের জন্য সম্পূর্ণ গাইড')
        );

        $this->assertStringContainsString(
            '<title inertia>ফেক অর্ডার কীভাবে কমাবেন? WooCommerce মার্চেন্টদের জন্য সম্পূর্ণ গাইড | WooEasyLife ব্লগ</title>',
            $html
        );
        $this->assertStringContainsString(
            '<h1>ফেক অর্ডার কীভাবে কমাবেন? WooCommerce মার্চেন্টদের জন্য সম্পূর্ণ গাইড</h1>',
            $html
        );
    }

    public function test_english_hreflang_pages(): void
    {
        $home = $this->get('/en');
        $home->assertOk();
        $home->assertSee('hreflang="en"', false);
        $home->assertSee('hreflang="bn-BD"', false);
        $home->assertInertia(fn (Assert $page) => $page
            ->component('Welcome3')
            ->where('locale', 'en')
            ->where('seo.canonical_path', '/en')
            ->has('hero.headline')
            ->has('fraudCheck')
        );

        $this->get('/en/bd-fraud-checker')->assertOk();
        $this->get('/en/fake-order-protection')->assertOk();
        $this->get('/en/return-loss-calculator')->assertOk();
        $this->get('/en/ads-roas-calculator')->assertOk();
        $this->get('/en/courier-auto-entry')->assertOk();
        $this->get('/en/steadfast-return-hub')->assertOk();
        $this->get('/en/woocommerce-facebook-messenger')->assertOk();
        $this->get('/en/woocommerce-bangladesh')->assertOk();
        $this->get('/en/steadfast-integration')->assertOk();
        $this->get('/en/customer-verification')->assertOk();
        $this->get('/en/blog')->assertOk();
    }

    public function test_og_image_is_compressed_jpg(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('/images/seo/og-default.jpg', false);
        $response->assertSee('og:image:width', false);
        $this->assertFileExists(public_path('images/seo/og-default.jpg'));
        $this->assertFileExists(public_path('images/seo/og-default.webp'));
    }

    public function test_fb_app_id_meta_when_configured(): void
    {
        config(['seo.facebook_app_id' => '1234567890']);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('property="fb:app_id"', false);
        $response->assertSee('content="1234567890"', false);
    }

    public function test_home_json_ld_omits_ineligible_product_and_software_schema(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $html = $response->getContent();
        // Product and SoftwareApplication rich results require real commercial
        // eligibility data. Do not invent offers, reviews, or ratings.
        $this->assertStringNotContainsString('"@type":"SoftwareApplication"', $html);
        $this->assertStringNotContainsString('"@type":"Product"', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('"name":"WPSaleHub"', $html);
        $this->assertStringContainsString('"@type":"Person"', $html);
        $this->assertStringContainsString('Muhibbullah Ansary', $html);
        $this->assertStringContainsString('"@type":"WebPage"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
    }

    public function test_tool_landing_pages_keep_webpage_json_ld(): void
    {
        foreach (['/pricing', '/return-loss-calculator', '/courier-charge-calculator', '/ads-roas-calculator'] as $path) {
            $response = $this->get($path);
            $response->assertOk();
            $this->assertStringNotContainsString(
                '"@type":"SoftwareApplication"',
                $response->getContent(),
                "Unexpected SoftwareApplication on {$path}"
            );
            $this->assertStringContainsString('"@type":"WebPage"', $response->getContent());
        }
    }

    public function test_sitemap_lists_marketing_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/bd-fraud-checker', false);
        $response->assertSee('/faq', false);
        $response->assertSee('/faq/courier-success-rate-kivabe-bujhbo', false);
        $response->assertSee('/faq/cod-return-loss-hisab', false);
        $response->assertSee('/ki-vabe-fake-order-atkabo', false);
        $response->assertSee('/fake-customer-check', false);
        $response->assertSee('/en/fake-customer-check', false);
        $response->assertSee('/bd-courier-ratio-checker', false);
        $response->assertSee('/fake-order-check', false);
        $response->assertSee('/courier-checker', false);
        $response->assertSee('/fake-order-protection', false);
        $response->assertSee('/return-loss-calculator', false);
        $response->assertSee('/courier-charge-calculator', false);
        $response->assertSee('/ads-roas-calculator', false);
        $response->assertSee('/courier-auto-entry', false);
        $response->assertSee('/fraudbd-alternative', false);
        $response->assertSee('/pathao-fraud-check', false);
        $response->assertSee('/blog', false);
        $response->assertSee('/en/bd-fraud-checker', false);
        $response->assertSee('/en/courier-charge-calculator', false);
        $response->assertSee('/en/fraudbd-alternative', false);
        $response->assertSee('/woocommerce-bangladesh', false);
        $response->assertSee('/en/woocommerce-bangladesh', false);
        $response->assertSee('/steadfast-integration', false);
        $response->assertSee('/customer-verification', false);
        $response->assertSee('/facebook-ads-for-woocommerce', false);
        $response->assertSee('/facebook-page-cod-management', false);
        $response->assertSee('/pricing', false);
        $response->assertDontSee('/woodnutsbolts/privacy-policy', false);
        $response->assertDontSee('/woodnutsbolts/terms-of-service', false);
    }

    public function test_home_prerender_links_every_sitemap_path(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('aria-label="Site pages"', $html);

        foreach (config('seo.sitemap.paths', []) as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path === '' || $path === '/') {
                continue;
            }
            $this->assertStringContainsString('href="'.$path.'"', $html, "Missing internal link for sitemap path {$path}");
        }
    }

    public function test_home_prerender_links_every_sitemap_blog_post(): void
    {
        $blog = app(\App\Services\BlogService::class);
        $posts = $blog->all();
        $this->assertNotEmpty($posts, 'Need at least one published blog post to assert orphan prevention');

        $response = $this->get('/');
        $response->assertOk();
        $html = $response->getContent();

        foreach ($posts as $post) {
            $slug = (string) ($post['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $path = '/blog/'.$slug;
            $this->assertStringContainsString(
                'href="'.$path.'"',
                $html,
                "Orphan risk: sitemap blog {$path} missing from home internal links"
            );
        }

        $navLinks = collect(\App\Support\SeoPrerenderText::sitemapNavLinks(false))->pluck('href');
        foreach ($posts as $post) {
            $slug = (string) ($post['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $this->assertTrue(
                $navLinks->contains('/blog/'.$slug),
                'sitemapNavLinks (footer) must include /blog/'.$slug
            );
        }
    }

    public function test_ai_content_prerender_uses_semantic_hierarchy(): void
    {
        foreach (['/ki-vabe-fake-order-atkabo', '/woocommerce-bangladesh'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/id="seo-prerender"[^>]*>[\s\S]*<article>/', $html);
            $this->assertMatchesRegularExpression('/id="seo-prerender"[^>]*>[\s\S]*<section>/', $html);
            $this->assertStringContainsString('<h2>যা জানতে চান</h2>', $html);
            $this->assertMatchesRegularExpression('/<h2>যা জানতে চান<\/h2>[\s\S]*<h3>/', $html);
        }

        $pillarHtml = $this->get('/woocommerce-bangladesh')->assertOk()->getContent();
        $this->assertStringContainsString('<h2>গাইড পর্বসমূহ</h2>', $pillarHtml);
        $this->assertMatchesRegularExpression('/<h2>গাইড পর্বসমূহ<\/h2>[\s\S]*<h3>অংশ ১\/৩০/', $pillarHtml);

        $chunks = \App\Support\SeoPrerenderText::readableParagraphs(
            str_repeat('এটি একটি পরীক্ষামূলক বাক্য যা যথেষ্ট লম্বা যাতে স্প্লিট হয়। ', 10)
        );
        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_robots_includes_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->has('Set-Cookie'), 'robots.txt must not set session cookies');
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Disallow: /woodnutsbolts/privacy-policy', false);
        $response->assertSee('Disallow: /woodnutsbolts/terms-of-service', false);
        $response->assertSee('Sitemap:', false);
        $response->assertSee('/sitemap.xml', false);
        $this->assertFileExists(public_path('robots.txt'));
        $this->assertStringContainsString('Sitemap:', (string) file_get_contents(public_path('robots.txt')));
    }

    public function test_robots_disallows_authenticated_admin_and_portal_paths(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $body = $response->getContent();

        foreach ([
            '/dashboard',
            '/portal',
            '/muhib',
            '/marchent',
            '/blog-posts',
            '/visitors',
            '/maintenance',
            '/profile',
            '/forgot-password',
            '/reset-password',
            '/telescope',
            '/deploy',
        ] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $body);
        }

        // Public marketing URLs must remain crawlable (no blanket Disallow: /).
        $this->assertStringNotContainsString("Disallow: /\n", $body."\n");
        foreach (['/blog', '/pricing', '/bd-fraud-checker', '/faq'] as $publicPath) {
            $this->assertDoesNotMatchRegularExpression(
                '/^Disallow:\s*'.preg_quote($publicPath, '/').'\s*$/m',
                $body
            );
        }
    }

    public function test_robots_is_cacheable_and_does_not_start_a_session(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertNotEquals('X-Inertia', (string) $response->headers->get('Vary'));
    }

    public function test_robots_is_reachable_during_maintenance(): void
    {
        $this->artisan('down');

        try {
            $this->get('/robots.txt')->assertOk()->assertSee('User-agent: *', false);
        } finally {
            $this->artisan('up');
        }
    }

    public function test_public_robots_txt_uses_canonical_sitemap(): void
    {
        $path = public_path('robots.txt');
        $original = is_file($path) ? (string) file_get_contents($path) : '';

        try {
            config(['app.url' => 'https://app.wpsalehub.com']);
            $this->artisan('seo:write-robots')->assertSuccessful();

            $this->assertFileExists($path);
            $body = (string) file_get_contents($path);
            $this->assertStringContainsString('User-agent: *', $body);
            $this->assertStringContainsString('Sitemap: https://app.wpsalehub.com/sitemap.xml', $body);
            $this->assertStringNotContainsString('localhost', $body);
            $this->assertStringNotContainsString("Disallow: /\n", $body."\n");
        } finally {
            if ($original !== '') {
                file_put_contents($path, $original);
            }
        }
    }

    public function test_seo_write_robots_command_refreshes_public_file(): void
    {
        $path = public_path('robots.txt');
        $original = (string) file_get_contents($path);

        try {
            config(['app.url' => 'https://app.wpsalehub.com']);
            $this->artisan('seo:write-robots')->assertSuccessful();
            $body = (string) file_get_contents($path);
            $this->assertStringContainsString('Sitemap: https://app.wpsalehub.com/sitemap.xml', $body);
            $this->assertStringContainsString('Disallow: /dashboard', $body);
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_robots_cache_miss_rewrites_public_file(): void
    {
        $path = public_path('robots.txt');
        $original = (string) file_get_contents($path);

        try {
            config(['app.url' => 'https://app.wpsalehub.com']);
            \Illuminate\Support\Facades\Cache::forget(\App\Http\Controllers\App\RobotsController::CACHE_KEY);
            file_put_contents($path, "User-agent: *\nDisallow: /\n");

            $this->get('/robots.txt')->assertOk();

            $body = (string) file_get_contents($path);
            $this->assertStringContainsString('Sitemap: https://app.wpsalehub.com/sitemap.xml', $body);
            $this->assertStringNotContainsString("Disallow: /\n", $body."\n");
        } finally {
            file_put_contents($path, $original);
            \Illuminate\Support\Facades\Cache::forget(\App\Http\Controllers\App\RobotsController::CACHE_KEY);
        }
    }

    public function test_publishing_blog_post_forgets_sitemap_nav_cache(): void
    {
        \App\Support\SeoPrerenderText::sitemapNavLinks(false);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(\App\Support\SeoPrerenderText::SITEMAP_NAV_CACHE_PREFIX.'bn'));

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-nav-'.uniqid().'@example.com',
            'phone' => '01700000999',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        BlogPost::create([
            'title' => 'Nav cache bust post',
            'slug' => 'nav-cache-bust-post',
            'locale' => 'bn',
            'status' => 'published',
            'body_html' => '<p>Test</p>',
            'published_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->assertFalse(\Illuminate\Support\Facades\Cache::has(\App\Support\SeoPrerenderText::SITEMAP_NAV_CACHE_PREFIX.'bn'));
    }

    public function test_home_includes_ga4_gtag_when_measurement_id_configured(): void
    {
        config(['seo.ga.measurement_id' => 'G-V3TDVR7ED9']);
        app(\App\Services\Seo\GaCredentialStore::class)->clearMeasurementId();
        app(\App\Services\Seo\GaCredentialStore::class)->clearMeasurementEnabled();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-V3TDVR7ED9', false);
        $response->assertSee("gtag('config', \"G-V3TDVR7ED9\")", false);
    }

    public function test_home_omits_ga4_gtag_when_measurement_id_empty(): void
    {
        config(['seo.ga.measurement_id' => '']);
        app(\App\Services\Seo\GaCredentialStore::class)->clearMeasurementId();
        app(\App\Services\Seo\GaCredentialStore::class)->clearMeasurementEnabled();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com/gtag/js', false);
    }

    public function test_home_omits_ga4_gtag_when_admin_disables_public_tag(): void
    {
        config(['seo.ga.measurement_id' => 'G-V3TDVR7ED9']);
        $store = app(\App\Services\Seo\GaCredentialStore::class);
        $store->putMeasurementId('G-V3TDVR7ED9');
        $store->putMeasurementEnabled(false);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com/gtag/js', false);
    }

    public function test_home_prefers_admin_saved_measurement_id_over_env(): void
    {
        config(['seo.ga.measurement_id' => 'G-ENVDEFAULT1']);
        $store = app(\App\Services\Seo\GaCredentialStore::class);
        $store->putMeasurementId('G-V3TDVR7ED9');
        $store->putMeasurementEnabled(true);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-V3TDVR7ED9', false);
        $response->assertDontSee('G-ENVDEFAULT1', false);
    }

    public function test_llms_txt_is_public_and_follows_spec(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('# WooEasyLife', false);
        $response->assertSee('> WPSaleHub is a business automation company', false);
        $response->assertSee('Organization: WPSaleHub', false);
        $response->assertSee('Product: WooEasyLife', false);
        $response->assertSee('## Primary tools', false);
        $response->assertSee('/bd-fraud-checker', false);
        $response->assertSee('/pricing', false);
        $response->assertSee('/woocommerce-bangladesh', false);
        $response->assertSee('/sitemap.xml', false);
        $response->assertSee('## Optional', false);
        $response->assertSee('/fake-order-check', false);
        $response->assertSee('/courier-checker', false);
        $response->assertSee('/en/ki-vabe-fake-order-atkabo', false);
        $response->assertDontSee('/woodnutsbolts/privacy-policy', false);
        $response->assertDontSee('/woodnutsbolts/terms-of-service', false);
    }

    public function test_home_prerender_includes_longform_content(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('বাংলাদেশি COD সেলারদের জন্য WooEasyLife', false);
        $response->assertSee('id="seo-prerender"', false);

        $seo = app(\App\Services\SeoMetaService::class)->forPage('home');
        $this->assertNotEmpty($seo['content_sections']);

        $tokens = $this->seoPrerenderTokenCount($seo);
        $this->assertGreaterThan(
            200,
            $tokens,
            'Home prerender copy should exceed Semrush 200-word threshold.'
        );
    }

    public function test_flagged_thin_pages_have_content_sections(): void
    {
        $pages = [
            'home',
            'pricing',
            'bd_fraud_checker',
            'fake_order_protection',
            'return_loss_calculator',
            'courier_charge_calculator',
            'ads_roas_calculator',
            'courier_auto_entry',
            'en_home',
            'en_bd_fraud_checker',
            'en_fake_order_protection',
            'en_return_loss_calculator',
            'en_ads_roas_calculator',
            'en_courier_charge_calculator',
            'en_fraudbd_alternative',
            'en_courier_auto_entry',
            'fraudbd_alternative',
            'woocommerce_bangladesh',
            'steadfast_integration',
            'pathao_courier_guide',
            'redx_courier_guide',
            'woocommerce_mobile_app',
            'customer_verification',
            'cod_return_reduction',
            'woocommerce_notifications',
            'facebook_ads_for_woocommerce',
            'en_woocommerce_bangladesh',
            'en_steadfast_integration',
            'en_customer_verification',
            'en_facebook_ads_for_woocommerce',
            'fake_customer_check',
            'courier_checker',
            'fake_order_check',
            'bd_courier_ratio_checker',
            'pathao_fraud_check',
            'steadfast_fraud_check',
            'redx_fraud_check',
            'ki_vabe_fake_order_atkabo',
            'en_ki_vabe_fake_order_atkabo',
            'en_fake_customer_check',
            'blog_index',
            'en_blog_index',
        ];

        $seoService = app(\App\Services\SeoMetaService::class);

        foreach ($pages as $page) {
            $seo = $seoService->forPage($page);
            $this->assertNotEmpty($seo['content_sections'], "Missing content_sections for {$page}");
            $this->assertGreaterThan(
                200,
                $this->seoPrerenderTokenCount($seo),
                "Thin content risk for {$page}"
            );
        }
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function seoPrerenderTokenCount(array $seo): int
    {
        $parts = [
            (string) ($seo['prerender_h1'] ?? ''),
            (string) ($seo['prerender_lead'] ?? ''),
        ];

        foreach ($seo['content_sections'] ?? [] as $section) {
            $parts[] = (string) ($section['heading'] ?? '');
            foreach ($section['paragraphs'] ?? [] as $paragraph) {
                $parts[] = (string) $paragraph;
            }
            foreach ($section['list'] ?? [] as $item) {
                $parts[] = (string) $item;
            }
        }

        foreach ($seo['faqs'] ?? [] as $faq) {
            $parts[] = (string) ($faq['q'] ?? '');
            $parts[] = (string) ($faq['a'] ?? '');
        }

        $tokens = preg_split('/\s+/u', trim(implode(' ', $parts))) ?: [];

        return count(array_filter($tokens));
    }
}
