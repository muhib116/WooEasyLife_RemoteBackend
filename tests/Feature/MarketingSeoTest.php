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
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/FakeOrderProtection')
            ->where('seo.canonical_path', '/fake-order-protection')
        );
    }

    public function test_return_loss_calculator_page_renders(): void
    {
        $response = $this->get('/return-loss-calculator');

        $response->assertOk();
        $response->assertSee('রিটার্ন লস', false);
        $response->assertSee('name="description"', false);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/ReturnLossCalculator')
            ->has('roiCalculator')
            ->has('seo')
            ->where('seo.canonical_path', '/return-loss-calculator')
        );
    }

    public function test_courier_charge_calculator_page_renders(): void
    {
        $response = $this->get('/courier-charge-calculator');

        $response->assertOk();
        $response->assertSee('কুরিয়ার চার্জ', false);
        $response->assertSee('BreadcrumbList', false);
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
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/AdsRoasCalculator')
            ->has('adsRoasCalculator.inputs.ad_spend')
            ->has('adsRoasCalculator.inputs.fake_cancel_rate')
            ->has('seo.faqs')
            ->where('seo.canonical_path', '/ads-roas-calculator')
        );
    }

    public function test_courier_auto_entry_page_renders(): void
    {
        $response = $this->get('/courier-auto-entry');

        $response->assertOk();
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

    public function test_fraudbd_alternative_page_renders(): void
    {
        $response = $this->get('/fraudbd-alternative');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/FraudBdAlternative')
            ->where('seo.canonical_path', '/fraudbd-alternative')
            ->has('seo.breadcrumbs')
        );
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
            '/fake-customer-check',
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
        $post = $this->get('/blog/fake-order-komano');
        $post->assertOk();
        $post->assertSee('BlogPosting', false);
        $post->assertSee('"@type":"Person"', false);
        $post->assertSee('hreflang="bn-BD"', false);
        $post->assertSee('/blog/fake-order-komano', false);
        // Individual posts must not advertise the blog index EN alternate.
        $this->assertStringNotContainsString('hreflang="en"', $post->getContent());
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

        $this->get('/en/bd-fraud-checker')->assertOk();
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

    public function test_home_includes_software_application_json_ld(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('"@type":"SoftwareApplication"', $html);
        $this->assertStringContainsString('"price":0', $html);
        $this->assertStringContainsString('"availability":"https://schema.org/InStock"', $html);
        $this->assertStringContainsString('#software', $html);
    }

    public function test_tool_landing_pages_omit_software_application_json_ld(): void
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
        $response->assertSee('/pricing', false);
    }

    public function test_robots_includes_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Sitemap:', false);
        $response->assertSee('/sitemap.xml', false);
    }
}
