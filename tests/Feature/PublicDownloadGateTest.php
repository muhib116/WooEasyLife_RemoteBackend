<?php

namespace Tests\Feature;

use App\Services\DomainNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDownloadGateTest extends TestCase
{
    use RefreshDatabase;

    private function mockDnsPass(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $real = new DomainNormalizer();

            $mock->shouldReceive('normalize')
                ->andReturnUsing(fn (?string $input) => $real->normalize($input));
            $mock->shouldReceive('hasDnsARecord')->andReturn(true);
            $mock->shouldReceive('resolvesPublicly')->andReturn(true);
        });
    }

    private function mockDnsFail(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $real = new DomainNormalizer();

            $mock->shouldReceive('normalize')
                ->andReturnUsing(fn (?string $input) => $real->normalize($input));
            $mock->shouldReceive('hasDnsARecord')->andReturn(false);
            $mock->shouldReceive('resolvesPublicly')->andReturn(false);
        });
    }

    public function test_validate_website_accepts_live_domain_with_dns(): void
    {
        $this->mockDnsPass();

        $this->postJson(route('landing.download-gate.validate-website'), [
            'website' => 'https://myshop.com',
        ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'website' => 'myshop.com',
            ]);
    }

    public function test_validate_website_rejects_domain_without_dns_a_record(): void
    {
        $this->mockDnsFail();

        $this->postJson(route('landing.download-gate.validate-website'), [
            'website' => 'no-dns-shop.example',
        ])
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error_field' => 'website',
            ])
            ->assertJsonFragment([
                'message' => 'ডোমেইনের DNS A রেকর্ড পাওয়া যায়নি। লাইভ ওয়েবসাইটের সঠিক ডোমেইন দিন।',
            ]);
    }

    public function test_validate_website_rejects_full_page_urls(): void
    {
        $this->mockDnsPass();

        $this->postJson(route('landing.download-gate.validate-website'), [
            'website' => 'https://console.firebase.google.com/u/3/project/wooeasylifeapp/settings/general',
        ])
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error_field' => 'website',
            ])
            ->assertJsonFragment([
                'message' => 'শুধু ওয়েবসাইটের ডোমেইন দিন (যেমন: myshop.com), পূর্ণ পেজ লিংক নয়।',
            ]);
    }

    public function test_send_otp_rejects_website_without_dns(): void
    {
        $this->mockDnsFail();

        $this->postJson(route('landing.download-gate.send-otp'), [
            'name' => 'Muhibbullah Ansary',
            'phone' => '01770989591',
            'website' => 'dead-shop.example',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_field', 'website')
            ->assertJsonPath('errors.website', 'ডোমেইনের DNS A রেকর্ড পাওয়া যায়নি। লাইভ ওয়েবসাইটের সঠিক ডোমেইন দিন।');
    }

    public function test_send_otp_rejects_invalid_phone_with_phone_field(): void
    {
        $this->mockDnsPass();

        $this->postJson(route('landing.download-gate.send-otp'), [
            'name' => 'Muhibbullah Ansary',
            'phone' => '12345',
            'website' => 'myshop.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_field', 'phone')
            ->assertJsonPath('errors.phone', 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন (01XXXXXXXXX)।');
    }
}
