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
        $response->assertSee('BD Fraud Checker', false);
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
        $response->assertSee('BD Fraud Checker', false);
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

    public function test_courier_auto_entry_page_renders(): void
    {
        $response = $this->get('/courier-auto-entry');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seo/CourierAutoEntry')
            ->where('seo.canonical_path', '/courier-auto-entry')
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
        $response->assertSee('/images/seo/og-default.png', false);
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

    public function test_sitemap_lists_marketing_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/bd-fraud-checker', false);
        $response->assertSee('/fake-order-protection', false);
        $response->assertSee('/courier-auto-entry', false);
        $response->assertSee('/fraudbd-alternative', false);
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
