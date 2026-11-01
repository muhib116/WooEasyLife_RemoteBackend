<?php

namespace Tests\Feature;

use App\Support\SeoPrerenderText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response as SsrResponse;
use Tests\TestCase;

class CourierCheckPluginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_bangla_and_english_pages_are_crawlable_without_a_live_download(): void
    {
        $this->assertFalse(filter_var(config('courier_check.wporg_live'), FILTER_VALIDATE_BOOLEAN));

        $bn = $this->get('/courier-check-plugin');
        $en = $this->get('/en/courier-check-plugin');

        $bn->assertOk();
        $en->assertOk();

        $this->assertLockedPage($bn->getContent(), false);
        $this->assertLockedPage($en->getContent(), true);

        $bn->assertDontSee('wordpress.org/plugins/easylife-courier-check', false);
        $en->assertDontSee('wordpress.org/plugins/easylife-courier-check', false);
        $en->assertDontSee('/faq/courier-success-rate-kivabe-bujhbo', false);
        $bn->assertSee('aria-disabled="true"', false);
        $bn->assertSee('শীঘ্রই WordPress.org-এ আসছে', false);
        $en->assertSee('Coming soon on WordPress.org', false);
        $bn->assertDontSee('orders.webp', false);
        $bn->assertDontSee('details.webp', false);
        $en->assertDontSee('orders.webp', false);
        $en->assertDontSee('details.webp', false);

        $this->assertSame(10, substr_count($this->footerNav($bn->getContent()), '<a '));
        $this->assertSame(10, substr_count($this->footerNav($en->getContent()), '<a '));
        $this->assertSame(count(SeoPrerenderText::compactFooterLinks(false)), 10);
    }

    public function test_live_flag_adds_the_wordpress_org_link_and_download_url(): void
    {
        Config::set('courier_check.wporg_live', true);

        $response = $this->get('/courier-check-plugin');

        $response->assertOk();
        $response->assertSee('href="https://wordpress.org/plugins/easylife-courier-check/"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener"', false);
        $response->assertSee('ফ্রি ডাউনলোড — WordPress.org', false);
        $response->assertSee('"downloadUrl":"https://wordpress.org/plugins/easylife-courier-check/"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_string_false_keeps_the_download_off(): void
    {
        Config::set('courier_check.wporg_live', 'false');

        $this->get('/en/courier-check-plugin')
            ->assertOk()
            ->assertDontSee('wordpress.org/plugins/easylife-courier-check', false)
            ->assertSee('Coming soon on WordPress.org', false);
    }

    public function test_present_screenshots_are_measured_and_absent_files_are_not_named(): void
    {
        $dir = public_path('images/courier-check-plugin');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $orders = $dir.'/orders.webp';
        $details = $dir.'/details.webp';

        try {
            $this->writeWebp($orders, 12, 8);
            $this->writeWebp($details, 9, 5);

            $bn = $this->get('/courier-check-plugin');
            $en = $this->get('/en/courier-check-plugin');
            $bn->assertOk();
            $en->assertOk();

            $bn->assertSee('src="/images/courier-check-plugin/orders.webp"', false);
            $bn->assertSee('alt="WooCommerce order list-এ courier delivery history"', false);
            $bn->assertSee('alt="Courier-wise fraud history popup"', false);
            $bn->assertSee('loading="lazy"', false);
            $bn->assertSee('width="12"', false);
            $bn->assertSee('height="8"', false);
            $bn->assertSee('width="9"', false);
            $bn->assertSee('height="5"', false);
            $en->assertSee('alt="Courier delivery history in the WooCommerce order list"', false);
            $bn->assertSee('"@type":"ImageObject"', false);
        } finally {
            @unlink($orders);
            @unlink($details);
            @rmdir($dir);
        }

        $after = $this->get('/courier-check-plugin');
        $after->assertOk();
        $after->assertDontSee('orders.webp', false);
        $after->assertDontSee('details.webp', false);
    }

    public function test_ssr_body_does_not_add_a_second_h1(): void
    {
        $this->app->instance(Gateway::class, new class implements Gateway
        {
            public function dispatch(array $page): ?SsrResponse
            {
                $heading = (string) ($page['props']['seo']['prerender_h1'] ?? '');

                return new SsrResponse('', '<h1>'.e($heading).'</h1>');
            }
        });

        $html = $this->get('/courier-check-plugin')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<p>WooCommerce-এর জন্য ফ্রি Courier Ratio ও Fraud Checker Plugin</p>', $html);
    }

    public function test_sitemap_lists_both_pages_and_related_pages_keep_their_heads(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/courier-check-plugin', false)
            ->assertSee('/en/courier-check-plugin', false);

        $pages = [
            '/fake-order-protection' => '/fake-order-protection',
            '/en/fake-order-protection' => '/en/fake-order-protection',
            '/bd-fraud-checker' => '/bd-fraud-checker',
            '/pricing' => '/pricing',
            '/' => '/',
        ];

        foreach ($pages as $path => $canonicalPath) {
            $response = $this->get($path);
            $response->assertOk();
            $canonical = $canonicalPath === '/'
                ? rtrim((string) config('app.url'), '/').'/'
                : url($canonicalPath);
            $response->assertSee('rel="canonical" href="'.$canonical.'"', false);
            if ($path !== '/pricing') {
                $response->assertSee('hreflang="bn-BD"', false);
            }
        }

        $faq = $this->get('/faq/courier-success-rate-kivabe-bujhbo');
        $faq->assertOk();
        $faq->assertSee('ফ্রি WordPress প্লাগইন: EasyLife Courier Check →', false);
        $faq->assertDontSee('SoftwareApplication', false);
        $faq->assertDontSee('#easylife-courier-check', false);

        $ratio = $this->get('/bd-courier-ratio-checker');
        $ratio->assertOk();
        $ratio->assertSee('ফ্রি WordPress প্লাগইন: EasyLife Courier Check →', false);
        $this->assertStringNotContainsString('SoftwareApplication', $this->jsonLd($ratio->getContent()));

        $protection = $this->get('/fake-order-protection');
        $this->assertStringNotContainsString('#easylife-courier-check', $this->jsonLd($protection->getContent()));
        $this->assertStringNotContainsString('trailing_text_link', $this->jsonLd($protection->getContent()));
    }

    private function assertLockedPage(string $html, bool $english): void
    {
        $path = $english ? '/en/courier-check-plugin' : '/courier-check-plugin';
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'application/ld+json'));
        $this->assertStringContainsString('rel="canonical" href="'.url($path).'"', $html);
        $this->assertStringContainsString('hreflang="bn-BD" href="'.url('/courier-check-plugin').'"', $html);
        $this->assertStringContainsString('hreflang="en" href="'.url('/en/courier-check-plugin').'"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/courier-check-plugin').'"', $html);
        $this->assertStringContainsString('property="og:locale" content="'.($english ? 'en_US' : 'bn_BD').'"', $html);
        $this->assertStringContainsString('name="robots" content="index,follow"', $html);
        $this->assertStringContainsString('#easylife-courier-check', $html);
        $this->assertStringNotContainsString('#software', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertSame(6, substr_count($this->jsonLd($html), '"@type":"Question"'));
        $this->assertStringContainsString('"price":"0"', $html);
        $this->assertStringContainsString('"priceCurrency":"BDT"', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);

        $withoutLd = preg_replace('/<script id="seo-json-ld" type="application\/ld\+json">.*?<\/script>/s', '', $html) ?: $html;
        $this->assertStringNotContainsString('৳', $html);
        $bdtOutsideOffer = str_replace(
            ['"priceCurrency":"BDT"', '\\"priceCurrency\\":\\"BDT\\"', '&quot;priceCurrency&quot;:&quot;BDT&quot;'],
            '',
            $withoutLd,
        );
        $this->assertStringNotContainsString('BDT', $bdtOutsideOffer);

        foreach ($this->lockedStrings($english) as $needle) {
            $expected = str_contains($needle, 'href=') ? $needle : e($needle);
            $this->assertStringContainsString($expected, $html, $needle);
        }
    }

    /**
     * @return list<string>
     */
    private function lockedStrings(bool $english): array
    {
        if ($english) {
            return [
                'Free Courier Ratio Checker Plugin for WooCommerce | WooEasyLife',
                'See a customer\'s Pathao, Steadfast, RedX, Paperfly and Carrybee courier success ratio inside WooCommerce orders. Free WordPress plugin, no API token needed.',
                'Free Courier Ratio & COD Fraud Checker Plugin for WooCommerce',
                'EasyLife Courier Check is a free WordPress plugin that shows a customer\'s previous courier history next to the WooCommerce order: total parcels, delivered, returned and success ratio. It covers Pathao, Steadfast, RedX, Paperfly and Carrybee. No API token or account is needed, and a number of checks every day are free.',
                'Why check courier history before you confirm an order',
                'How it works (3 steps)',
                'What\'s included',
                'Free plugin vs WooEasyLife',
                'FAQ',
                'A low success ratio does not mean the customer is fake. It is one signal. Confirm with a call, OTP or an advance payment.',
                'Install the plugin and turn on lookups in Courier Check → Settings.',
                'Open Courier Check → Orders and click "Check history" on any order.',
                'Look at Total, Delivered, Cancel and Rate, then confirm, call, or ask for an advance.',
                'A Delivery history column on every order and a box on the order screen',
                'Check any phone number on its own (Fraud Checker)',
                'Earlier orders from the same number in your own store: how many delivered, how many cancelled',
                'A warning for duplicate orders from the same number within 24 hours',
                'Product images, delivery charge and order source at a glance',
                'One-click call, WhatsApp or copy number',
                'Optional automatic check for new orders (checkout is not slowed down)',
                'HPOS support, privacy-first: nothing is sent until you turn lookups on',
                'Courier history on orders | ✓ | ✓',
                'Check any phone number | ✓ | ✓',
                'Bulk history check for many orders at once | — | ✓',
                'Courier auto entry (Pathao, Steadfast, RedX, Paperfly) | — | ✓',
                'Checkout phone OTP, duplicate block, daily order limit | — | ✓',
                'Blacklist by phone, email, IP or device | — | ✓',
                'Mobile app | — | ✓',
                'href="/en/courier-auto-entry"',
                'href="/en/fake-order-protection"',
                'Do I need an API token or an account?',
                'No. Just turn on lookups in Settings.',
                'Which couriers are covered?',
                'Pathao, Steadfast, RedX, Paperfly and Carrybee. Some numbers may have no data.',
                'What is courier success ratio?',
                'The share of a customer\'s parcels that were delivered. For example, 8 out of 10 = 80%.',
                'What happens when the daily limit is reached?',
                'You can check again the next day. Earlier results stay on the order.',
                'What customer data is sent?',
                'Only the billing phone number and your site URL.',
                'href="/wooeasylife/courier-check/privacy-policy"',
                'Is the free plugin the same as the free web fraud checker?',
                'Same data source. The plugin shows the result inside the WooCommerce order, so you don\'t have to copy and paste numbers.',
                'When a COD parcel comes back, you lose the delivery charge, the return charge and your time. Seeing how often the customer accepted earlier parcels makes the decision easier.',
                'Free fraud checker on the web',
                'See plans and free trial',
                'href="/pricing"',
                'Built by Muhibbullah Ansary, Founder, WPSaleHub',
                'href="/en/about"',
            ];
        }

        return [
            'ফ্রি Courier Ratio Checker Plugin — WooCommerce | WooEasyLife',
            'WooCommerce order-এর ভেতরেই কাস্টমারের Pathao, Steadfast, RedX, Paperfly, Carrybee courier success ratio দেখুন। ফ্রি WordPress plugin, API token লাগে না।',
            'WooCommerce-এর জন্য ফ্রি Courier Ratio ও Fraud Checker Plugin',
            'EasyLife Courier Check একটি ফ্রি WordPress plugin। এটা WooCommerce order-এর পাশেই কাস্টমারের আগের courier history দেখায়: মোট পার্সেল, ডেলিভার্ড, রিটার্ন আর success ratio। Pathao, Steadfast, RedX, Paperfly ও Carrybee-এর তথ্য থাকে। কোনো API token বা অ্যাকাউন্ট লাগে না। প্রতিদিন নির্দিষ্ট সংখ্যক check ফ্রি।',
            'কেন অর্ডার কনফার্মের আগে courier history দেখবেন',
            'কীভাবে কাজ করে (৩ ধাপ)',
            'Plugin-এ যা আছে',
            'ফ্রি plugin বনাম WooEasyLife',
            'প্রশ্নোত্তর (FAQ)',
            'কম success ratio মানেই কাস্টমার ভুয়া নয়। এটা একটা সংকেত মাত্র। কল, OTP বা অগ্রিম দিয়ে নিশ্চিত হোন।',
            'Plugin ইনস্টল করে Courier Check → Settings-এ lookup চালু করুন।',
            'Courier Check → Orders-এ যেকোনো order-এ "Check history" চাপুন।',
            'Total, Delivered, Cancel আর Rate দেখে কনফার্ম করুন, কল করুন বা অগ্রিম চান।',
            'প্রতিটি order-এ Delivery history কলাম ও order পেজে বক্স',
            'যেকোনো নম্বর আলাদাভাবে চেক (Fraud Checker)',
            'আপনার নিজের স্টোরে এই নম্বরের আগের order: কয়টা ডেলিভার্ড, কয়টা ক্যানসেল',
            '২৪ ঘণ্টার মধ্যে একই নম্বরের ডুপ্লিকেট order-এর সতর্কতা',
            'প্রোডাক্টের ছবি, ডেলিভারি চার্জ, order কোথা থেকে এসেছে (source)',
            'এক ক্লিকে কল, WhatsApp বা নম্বর কপি',
            'চাইলে নতুন order-এ অটো চেক (চেকআউট ধীর হয় না)',
            'HPOS সাপোর্ট, প্রাইভেসি-ফার্স্ট: আপনি চালু না করা পর্যন্ত কিছুই পাঠানো হয় না',
            'Order-এ courier history | ✓ | ✓',
            'যেকোনো নম্বর চেক | ✓ | ✓',
            'একসাথে অনেক order-এর bulk history check | — | ✓',
            'Courier auto entry (Pathao, Steadfast, RedX, Paperfly) | — | ✓',
            'চেকআউটে phone OTP, duplicate block, দৈনিক order limit | — | ✓',
            'ফোন, ইমেইল, IP বা ডিভাইস blacklist | — | ✓',
            'মোবাইল অ্যাপ | — | ✓',
            'href="/courier-auto-entry"',
            'href="/fake-order-protection"',
            'API token বা অ্যাকাউন্ট লাগে?',
            'না। Settings-এ lookup চালু করলেই চলবে।',
            'কোন কোন courier?',
            'Pathao, Steadfast, RedX, Paperfly, Carrybee। কিছু নম্বরে তথ্য না-ও থাকতে পারে।',
            'Courier success ratio কী?',
            'মোট পার্সেলের মধ্যে কত শতাংশ ডেলিভার্ড হয়েছে। যেমন ১০টার মধ্যে ৮টা = ৮০%।',
            'href="/faq/courier-success-rate-kivabe-bujhbo"',
            'দৈনিক limit শেষ হলে?',
            'পরদিন আবার check করা যাবে। আগের ফলাফল order-এ থেকে যায়।',
            'কাস্টমারের কী তথ্য পাঠানো হয়?',
            'শুধু billing phone নম্বর আর আপনার সাইটের URL।',
            'href="/wooeasylife/courier-check/privacy-policy"',
            'ফ্রি plugin আর ওয়েবের ফ্রি ফ্রড চেকার কি একই?',
            'একই তথ্যসূত্র। Plugin-এ ফলাফল সরাসরি WooCommerce order-এ আসে, নম্বর কপি-পেস্ট করতে হয় না।',
            'COD পার্সেল ফেরত এলে ডেলিভারি চার্জ, রিটার্ন চার্জ আর সময়, তিনটাই নষ্ট হয়। কাস্টমারের আগের পার্সেলগুলো কতবার গ্রহণ করা হয়েছে, সেটা দেখে সিদ্ধান্ত নেওয়া সহজ হয়।',
            'ওয়েবে ফ্রি ফ্রড চেকার',
            'প্ল্যান ও ফ্রি ট্রায়াল দেখুন',
            'href="/pricing"',
            'তৈরি করেছেন মুহিব্বুল্লাহ আনসারী, Founder, WPSaleHub',
            'href="/about"',
        ];
    }

    private function footerNav(string $html): string
    {
        preg_match('/<nav aria-label="Site pages">\s*<ul>(.*?)<\/ul>/s', $html, $match);

        return $match[1] ?? '';
    }

    private function jsonLd(string $html): string
    {
        preg_match('/<script id="seo-json-ld" type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);

        return $match[1] ?? '';
    }

    private function writeWebp(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);
        imagewebp($image, $path);
        imagedestroy($image);
        $this->assertFileExists($path);
    }
}
