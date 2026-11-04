<?php

namespace Tests\Feature;

use App\Services\PublicFraudCheckService;
use App\Support\SeoPrerenderText;
use App\Support\SuccessRateBands;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuccessRateFaqBandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_rate_faq_uses_the_checker_bands(): void
    {
        $good = SuccessRateBands::bangla(PublicFraudCheckService::GOOD_MIN);
        $caution = SuccessRateBands::bangla(PublicFraudCheckService::CAUTION_MIN);

        $response = $this->get('/faq/courier-success-rate-kivabe-bujhbo');
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<h1>কুরিয়ার সাকসেস রেট কীভাবে বুঝব?</h1>', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/faq/courier-success-rate-kivabe-bujhbo').'"', $html);
        $this->assertStringContainsString('<a href="/about">লিখেছেন: মুহিব্বুল্লাহ আনসারী</a>', $html);
        $this->assertSame(1, substr_count($html, 'application/ld+json'));
        $this->assertStringNotContainsString('৭০%+', $html);
        $this->assertStringNotContainsString('৭০ শতাংশ', $html);

        $title = 'কুরিয়ার সাকসেস রেট কত হলে ভালো? '.$good.'%, '.$caution.'% ও No History | WooEasyLife';
        $description = $good.'%+ সাধারণত ভালো, '.$caution.'–'.$good.'% হলে কল করে নিশ্চিত, '.$caution.'%-এর নিচে সাবধান। হিস্টোরি না থাকলে কী করবেন ও কম পার্সেলের ফাঁদ—সহজ টেবিলে।';
        $this->assertLessThanOrEqual(155, mb_strlen($description));
        $this->assertStringContainsString('<title inertia>'.$title.'</title>', $html);
        $this->assertStringContainsString('property="og:title" content="'.$title.'"', $html);
        $this->assertStringContainsString('name="description" content="'.$description.'"', $html);
        $this->assertStringContainsString('property="og:description" content="'.$description.'"', $html);

        $this->assertStringContainsString('<h2>সংখ্যা অনুযায়ী কী করবেন</h2>', $html);
        $this->assertStringContainsString('class="seo-table-scroll"', $html);
        $this->assertStringContainsString('<th scope="col">ফলাফল</th>', $html);
        $this->assertStringContainsString('<th scope="col">কী বোঝায়</th>', $html);
        $this->assertStringContainsString('<th scope="col">কী করবেন</th>', $html);
        $this->assertSame(1, preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $tbody));
        $this->assertSame(4, substr_count($tbody[1], '<tr>'));
        $this->assertStringContainsString($good.'% বা বেশি (সবুজ)', $tbody[1]);
        $this->assertStringContainsString($caution.'% থেকে '.$good.'%-এর নিচে (হলুদ)', $tbody[1]);
        $this->assertStringContainsString($caution.'%-এর নিচে (লাল)', $tbody[1]);
        $this->assertStringContainsString('কোনো হিস্টোরি নেই', $tbody[1]);
        $this->assertStringContainsString($good, $html);
        $this->assertStringContainsString($caution, $html);

        preg_match('/<script id="seo-json-ld" type="application\/ld\+json">(.*?)<\/script>/s', $html, $jsonMatch);
        $json = json_decode($jsonMatch[1] ?? '', true);
        $this->assertIsArray($json);
        $faqPage = collect($json['@graph'])->first(fn ($node) => ($node['@type'] ?? null) === 'FAQPage');
        $this->assertIsArray($faqPage);
        $this->assertCount(5, $faqPage['mainEntity']);

        $safe = collect($faqPage['mainEntity'])->first(
            fn ($node) => ($node['name'] ?? null) === 'সাকসেস রেট কত হলে সাধারণত নিরাপদ?'
        );
        $this->assertIsArray($safe);
        $schemaAnswer = (string) ($safe['acceptedAnswer']['text'] ?? '');
        $this->assertStringContainsString($good.' শতাংশ বা তার বেশি', $schemaAnswer);
        $this->assertStringContainsString($caution.' থেকে '.$good.' শতাংশের নিচে', $schemaAnswer);
        $this->assertStringContainsString($caution.' শতাংশের নিচে', $schemaAnswer);
        $this->assertStringContainsString('/faq/success-rate-kom-hole-ki-korbo', $schemaAnswer);

        $this->assertSame(1, preg_match(
            '/<h3>সাকসেস রেট কত হলে সাধারণত নিরাপদ\?<\/h3>(.*?)<h3>/s',
            $html,
            $visibleMatch
        ));
        $visible = trim(preg_replace('/\s+/u', ' ', strip_tags($visibleMatch[1])) ?? '');
        $expectedVisible = trim(preg_replace(
            '/\s+/u',
            ' ',
            strip_tags(SeoPrerenderText::linkifyHtml($schemaAnswer, false))
        ) ?? '');
        $this->assertSame($expectedVisible, $visible);

        $names = array_column($faqPage['mainEntity'], 'name');
        $this->assertSame([
            'সাকসেস রেট কত হলে সাধারণত নিরাপদ?',
            'শুধু এক কুরিয়ার দেখলেই কি যথেষ্ট?',
            'রেট ও স্কোর কি একই জিনিস?',
            'কাস্টমারের কোনো কুরিয়ার হিস্টোরি নেই, অর্ডার পাঠাব?',
            '২টা পার্সেলের ১টা ফেরত, ৫০% দেখাচ্ছে—এটা কি খারাপ?',
        ], $names);

        $article = collect($json['@graph'])->first(
            fn ($node) => str_ends_with((string) ($node['@id'] ?? ''), '#article')
        );
        $this->assertIsArray($article);
        $this->assertSame('2026-11-05', $article['dateModified'] ?? null);
        $this->assertSame('2026-07-01', $article['datePublished'] ?? null);

        foreach ([
            '/',
            '/bd-fraud-checker',
            '/bd-courier-ratio-checker',
            '/fake-customer-check',
            '/fake-order-protection',
            '/return-loss-calculator',
            '/faq/cod-return-loss-hisab',
            '/faq/success-rate-kom-hole-ki-korbo',
            '/faq/phone-confirm-delivery-guarantee-ki',
            '/faq/customer-delivery-history-check',
            '/faq/customer-fraud-score-ki',
            '/faq/prottek-customer-verify-korbo-ki',
            '/steadfast-fraud-check',
            '/blog/steadfast-delivery-ratio-ki',
            '/blog/kokhon-customer-verify-korbo',
            '/blog/steadfast-fraud-check-common-mistakes',
            '/courier-check-plugin',
            '/courier-auto-entry',
            '/woocommerce-mobile-app',
            '/pricing',
            '/faq',
            '/blog',
            '/about',
            '/en',
        ] as $path) {
            $this->assertStringContainsString('href="'.$path.'"', $html, $path);
        }
    }
}
