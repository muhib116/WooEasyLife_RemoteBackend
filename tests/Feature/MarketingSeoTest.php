<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertSee('name="description"', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/BdFraudChecker')
            ->has('fraudCheck')
            ->has('seo')
            ->where('seo.canonical_path', '/bd-fraud-checker')
        );
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
            'founder-portrait',
            (string) (is_array($person['image'] ?? null) ? ($person['image']['url'] ?? '') : ($person['image'] ?? ''))
        );
        $this->assertContains('https://www.linkedin.com/in/dev-muhib', $person['sameAs'] ?? []);
        $this->assertContains('https://www.facebook.com/muhib116', $person['sameAs'] ?? []);
        $this->assertContains('https://www.instagram.com/muhibbullah611/', $person['sameAs'] ?? []);
        $this->assertSame('Founder & CEO, WPSaleHub', $person['jobTitle'] ?? null);

        $org = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'Organization');
        $this->assertNotNull($org);
        $this->assertSame('WPSaleHub', $org['name'] ?? null);
        $this->assertNotNull($org['founder'] ?? null);
        $this->assertContains('https://www.facebook.com/wooeasylife', $org['sameAs'] ?? []);
        $this->assertNotContains('https://www.linkedin.com/in/dev-muhib', $org['sameAs'] ?? []);

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
        $this->assertStringContainsString('founder-portrait', (string) ($person['image']['url'] ?? ''));

        $crumb = $graph->first(fn (array $node) => ($node['@type'] ?? null) === 'BreadcrumbList');
        $this->assertNotNull($crumb, 'About should keep BreadcrumbList');

        $this->assertSame(1200, (int) ($seo['og_image_width'] ?? 0));
        $this->assertSame(630, (int) ($seo['og_image_height'] ?? 0));
        $this->assertStringContainsString('founder-hero-og.jpg', (string) ($seo['og_image'] ?? ''));

        $bn = $this->get('/about');
        $bn->assertOk();
        $bn->assertSee('/images/seo/about/founder-hero.png', false);
        $bn->assertSee('Muhibbullah Ansary', false);
        $bn->assertSee('Founder & CEO, WPSaleHub', false);
        $bn->assertSee('dev.muhibbullah@gmail.com', false);
        $bn->assertSee('Automating Business. Empowering People.', false);
        $bn->assertSee('WPSaleHub হলো একটি automation-first technology company', false);
        $bn->assertSee('WooCommerce মার্চেন্টদের জন্য তৈরি', false);
        $bn->assertDontSee('https:Bangla home', false);

        $en = $this->get('/en/about');
        $en->assertOk();
        $en->assertSee('Muhibbullah Ansary', false);
        $en->assertSee('Founder & CEO, WPSaleHub', false);
        $en->assertSee('/images/seo/about/founder-portrait.png', false);
        $en->assertSee('WooCommerce merchant solution', false);
        $en->assertSee('About WPSaleHub | WooEasyLife founder Muhibbullah Ansary', false);
        $en->assertSee('The fastest way is email', false);
        $en->assertDontSee('https:Bangla home', false);
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

    public function test_home_json_ld_omits_software_application_without_ratings(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $html = $response->getContent();
        // Google requires aggregateRating/review for SoftwareApplication; we omit it
        // rather than invent ratings (Semrush flagged / and /en as invalid).
        $this->assertStringNotContainsString('"@type":"SoftwareApplication"', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('"name":"WPSaleHub"', $html);
        $this->assertStringContainsString('"@type":"Product"', $html);
        $this->assertStringContainsString('"name":"WooEasyLife"', $html);
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

    public function test_robots_includes_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Disallow: /woodnutsbolts/privacy-policy', false);
        $response->assertSee('Disallow: /woodnutsbolts/terms-of-service', false);
        $response->assertSee('Sitemap:', false);
        $response->assertSee('/sitemap.xml', false);
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
            'ki_vabe_fake_order_atkabo',
            'en_ki_vabe_fake_order_atkabo',
            'en_fake_customer_check',
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
        }

        foreach ($seo['faqs'] ?? [] as $faq) {
            $parts[] = (string) ($faq['q'] ?? '');
            $parts[] = (string) ($faq['a'] ?? '');
        }

        $tokens = preg_split('/\s+/u', trim(implode(' ', $parts))) ?: [];

        return count(array_filter($tokens));
    }
}
