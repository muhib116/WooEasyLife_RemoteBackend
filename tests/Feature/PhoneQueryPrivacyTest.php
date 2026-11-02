<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneQueryPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_bangla_fraud_checker_keeps_a_query_phone_out_of_seo_head(): void
    {
        $this->assertPhoneStaysOutOfSeoHead('/bd-fraud-checker');
    }

    public function test_english_fraud_checker_keeps_a_query_phone_out_of_seo_head(): void
    {
        $this->assertPhoneStaysOutOfSeoHead('/en/bd-fraud-checker');
    }

    private function assertPhoneStaysOutOfSeoHead(string $path): void
    {
        $phone = '01700000000';

        $response = $this->get($path.'?phone='.$phone.'&utm_source=courierfraudbd');

        $response->assertOk();
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $html = (string) $response->getContent();

        $this->assertSame(1, preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $canonical));
        $this->assertStringNotContainsString($phone, $canonical[1]);
        $this->assertStringNotContainsString('phone=', strtolower($canonical[1]));
        $this->assertStringEndsWith($path, $canonical[1]);

        $this->assertSame(1, preg_match('/<meta property="og:url" content="([^"]+)"/', $html, $og));
        $this->assertStringNotContainsString($phone, $og[1]);
        $this->assertStringNotContainsString('phone=', strtolower($og[1]));

        preg_match_all('/<link rel="alternate" hreflang="[^"]+" href="([^"]+)"/', $html, $hreflang);
        $this->assertNotEmpty($hreflang[1]);

        foreach ($hreflang[1] as $href) {
            $this->assertStringNotContainsString($phone, $href);
            $this->assertStringNotContainsString('phone=', strtolower($href));
        }

        $this->assertSame(1, preg_match('/<title[^>]*>(.*?)<\/title>/s', $html, $title));
        $this->assertStringNotContainsString($phone, $title[1]);

        $this->assertSame(
            1,
            preg_match('/<script id="seo-json-ld" type="application\/ld\+json">(.*?)<\/script>/s', $html, $jsonLd),
        );
        $this->assertStringNotContainsString($phone, $jsonLd[1]);

        $this->assertStringContainsString('utm_source=courierfraudbd', $html);
        $this->assertStringContainsString('value="'.$phone.'"', $html);

        $stripAt = strpos($html, '__fraudCheckPhone');
        $this->assertNotFalse($stripAt);
        $this->assertStringContainsString('window.__cleanLocation', $html);
        $this->assertStringContainsString('history.replaceState(window.history.state', $html);

        foreach (['googletagmanager.com/gtag/js', 'fbevents.js', 'oaiq.min.js'] as $thirdParty) {
            $thirdPartyAt = strpos($html, $thirdParty);

            if ($thirdPartyAt !== false) {
                $this->assertLessThan($thirdPartyAt, $stripAt, $thirdParty);
            }
        }

        $this->assertSame(1, preg_match('/data-page="([^"]+)"/', $html, $pageAttribute));
        $page = json_decode(html_entity_decode($pageAttribute[1], ENT_QUOTES), true);
        $this->assertIsArray($page);
        $this->assertIsString($page['url'] ?? null);
        $this->assertStringNotContainsString($phone, $page['url']);
        $this->assertStringNotContainsString('phone=', strtolower($page['url']));
        $this->assertStringContainsString('utm_source=courierfraudbd', $page['url']);
    }
}
