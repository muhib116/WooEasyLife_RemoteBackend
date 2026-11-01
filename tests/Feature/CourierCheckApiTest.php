<?php

namespace Tests\Feature;

use App\Models\CourierCheckSite;
use App\Services\CourierCheck\CourierCheckApiService;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CourierCheckApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $configured = (string) ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE'));
        if ($configured !== 'API_Backend_testing') {
            $this->fail('Refusing to run courier check tests against '.$configured);
        }

        parent::setUp();

        $database = (string) config('database.connections.mysql.database');
        if ($database !== 'API_Backend_testing') {
            $this->fail('Refusing to run courier check tests against '.$database);
        }

        Cache::flush();
        config(['landing.fraud_check.enabled' => true]);
        config(['landing.fraud_check.daily_free_limit' => 5]);
    }

    public function test_valid_check_returns_the_plugin_contract(): void
    {
        $this->mockReport([
            'total_order' => 12,
            'confirmed' => 10,
            'cancel' => 2,
            'success_rate' => '83.3%',
            'courier' => [
                [
                    'title' => 'Pathao',
                    'report' => [
                        'total_order' => 12,
                        'confirmed' => 10,
                        'cancel' => 2,
                    ],
                ],
            ],
        ]);

        $site = $this->registerSite();

        $response = $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01712345678',
            'site_url' => 'https://www.Example.com/shop',
            'site_key' => $site['site_key'],
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame(
            ['couriers', 'summary', 'label', 'label_text', 'remaining_today'],
            array_keys($payload)
        );
        $this->assertSame(
            ['name', 'total', 'delivered', 'returned'],
            array_keys($payload['couriers'][0])
        );
        $this->assertSame(
            ['total', 'delivered', 'returned', 'success_rate'],
            array_keys($payload['summary'])
        );
        $response->assertJsonPath('couriers.0.name', 'Pathao')
            ->assertJsonPath('couriers.0.total', 12)
            ->assertJsonPath('couriers.0.delivered', 10)
            ->assertJsonPath('couriers.0.returned', 2)
            ->assertJsonPath('summary.total', 12)
            ->assertJsonPath('summary.delivered', 10)
            ->assertJsonPath('summary.returned', 2)
            ->assertJsonPath('summary.success_rate', 83.3)
            ->assertJsonPath('label', 'low')
            ->assertJsonPath('label_text', 'নিরাপদ গ্রাহক')
            ->assertJsonPath('remaining_today', 4);
        $this->assertSame('example.com', $site['domain']);
        $this->assertStringNotContainsString('01712345678', $response->getContent());
    }

    public function test_label_bands_follow_the_public_checker(): void
    {
        $cases = [
            ['75%', 4, 3, 1, 'low', 'নিরাপদ গ্রাহক', 75],
            ['45%', 20, 9, 11, 'medium', 'সতর্কতা প্রয়োজন', 45],
            ['44%', 100, 44, 56, 'high', 'ঝুঁকিপূর্ণ গ্রাহক', 44],
        ];

        $this->mock(FraudCheckCoordinator::class, function ($mock) use ($cases) {
            $mock->shouldReceive('checkSingle')->times(count($cases))->andReturn(
                ...array_map(fn (array $case) => [
                    'total_order' => $case[1],
                    'confirmed' => $case[2],
                    'cancel' => $case[3],
                    'success_rate' => $case[0],
                    'courier' => [],
                ], $cases)
            );
        });

        foreach ($cases as [$rate, $total, $delivered, $returned, $label, $text, $number]) {
            $host = 'band-'.md5($rate).'.example.com';
            $site = $this->registerSite('https://'.$host);

            $this->postJson('/api/courier-check/v1/check', [
                'phone' => '01812345678',
                'site_url' => 'https://'.$host,
                'site_key' => $site['site_key'],
            ])->assertOk()
                ->assertJsonPath('label', $label)
                ->assertJsonPath('label_text', $text)
                ->assertJsonPath('summary.success_rate', $number);
        }
    }

    public function test_no_history_is_unknown_with_null_success_rate(): void
    {
        $this->mockReport([
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
            'courier' => [],
        ]);
        $site = $this->registerSite();

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01912345678',
            'site_url' => 'https://example.com',
            'site_key' => $site['site_key'],
        ])->assertOk()
            ->assertJsonPath('label', 'unknown')
            ->assertJsonPath('label_text', 'ইতিহাস নেই')
            ->assertJsonPath('summary.success_rate', null);
    }

    public function test_invalid_phone_returns_422_and_does_not_call_the_checker(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')->never();
        });
        $site = $this->registerSite();

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '12345',
            'site_url' => 'https://example.com',
            'site_key' => $site['site_key'],
        ])->assertStatus(422)
            ->assertExactJson(['error' => 'invalid_phone']);

        $this->assertSame(0, $this->pluginUsage($site['site_key'], 'example.com'));
    }

    public function test_daily_limit_returns_429_and_stops_further_lookups(): void
    {
        config(['landing.fraud_check.daily_free_limit' => 1]);
        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->historyReport('80%'));
        });
        $site = $this->registerSite();
        $body = [
            'phone' => '01612345678',
            'site_url' => 'https://example.com',
            'site_key' => $site['site_key'],
        ];

        $this->postJson('/api/courier-check/v1/check', $body)
            ->assertOk()
            ->assertJsonPath('remaining_today', 0);

        $this->postJson('/api/courier-check/v1/check', $body)
            ->assertStatus(429)
            ->assertExactJson([
                'error' => 'daily_limit',
                'resets_at' => now(CourierCheckApiService::TIMEZONE)->addDay()->startOfDay()->toIso8601String(),
            ]);
    }

    public function test_unknown_and_revoked_site_keys_return_401(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')->never();
        });

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01712345678',
            'site_url' => 'https://example.com',
            'site_key' => '00000000-0000-4000-8000-000000000000',
        ])->assertStatus(401)
            ->assertExactJson(['error' => 'invalid_site_key']);

        $site = $this->registerSite();
        CourierCheckSite::query()->where('site_key', $site['site_key'])->update(['revoked_at' => now()]);

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01712345678',
            'site_url' => 'https://example.com',
            'site_key' => $site['site_key'],
        ])->assertStatus(401)
            ->assertExactJson(['error' => 'invalid_site_key']);
    }

    public function test_a_new_key_on_the_same_domain_does_not_reset_the_allowance(): void
    {
        config(['landing.fraud_check.daily_free_limit' => 1]);
        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')->once()->andReturn($this->historyReport('80%'));
        });

        $first = $this->registerSite();
        $second = $this->registerSite();
        $this->assertNotSame($first['site_key'], $second['site_key']);

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01512345678',
            'site_url' => 'https://example.com',
            'site_key' => $first['site_key'],
        ])->assertOk();

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01512345678',
            'site_url' => 'https://example.com',
            'site_key' => $second['site_key'],
        ])->assertStatus(429)
            ->assertJsonPath('error', 'daily_limit');
    }

    public function test_plugin_checks_do_not_consume_the_website_allowance(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')->times(3)->andReturn($this->historyReport('80%'));
        });
        $site = $this->registerSite();

        $this->getJson(route('landing.fraud-check.stats'))
            ->assertOk()
            ->assertJsonPath('used_searches', 0)
            ->assertJsonPath('remaining_searches', 5);

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01712345678',
            'site_url' => 'https://example.com',
            'site_key' => $site['site_key'],
        ])->assertOk()->assertJsonPath('remaining_today', 4);

        $this->getJson(route('landing.fraud-check.stats'))
            ->assertJsonPath('used_searches', 0)
            ->assertJsonPath('remaining_searches', 5);

        $this->postJson(route('landing.fraud-check.check'), [
            'phone' => '01712345678',
        ])->assertOk()
            ->assertJsonPath('meta.used_searches', 1)
            ->assertJsonPath('meta.remaining_searches', 4);

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01712345679',
            'site_url' => 'https://example.com',
            'site_key' => $site['site_key'],
        ])->assertOk()->assertJsonPath('remaining_today', 3);

        $publicKey = 'landing_fraud_check:ip:'.now()->toDateString().':127.0.0.1';
        $this->assertSame(1, (int) Cache::get($publicKey, 0));
        $this->assertSame(2, $this->pluginUsage($site['site_key'], 'example.com'));
    }

    public function test_legal_pages_are_public_and_listed_in_the_sitemap(): void
    {
        $privacy = $this->get('/wooeasylife/courier-check/privacy-policy');
        $privacy->assertOk();
        $privacy->assertSee('billing phone', false);
        $privacy->assertSee('dev.muhibbullah@gmail.com', false);
        $privacy->assertSee('/wooeasylife/courier-check/terms-of-service', false);
        $this->assertStringNotContainsString('fake', strtolower($privacy->getContent()));

        $terms = $this->get('/wooeasylife/courier-check/terms-of-service');
        $terms->assertOk();
        $terms->assertSee('not a statement', false);
        $this->assertStringNotContainsString('fake', strtolower($terms->getContent()));

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/wooeasylife/courier-check/privacy-policy', false)
            ->assertSee('/wooeasylife/courier-check/terms-of-service', false)
            ->assertSee('/wooeasylife/app/privacy-policy', false);
    }

    public function test_site_key_from_another_domain_is_rejected(): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')->never();
        });
        $site = $this->registerSite('https://shop.example.com');

        $this->postJson('/api/courier-check/v1/check', [
            'phone' => '01712345678',
            'site_url' => 'https://other.example.com',
            'site_key' => $site['site_key'],
        ])->assertStatus(401)
            ->assertExactJson(['error' => 'invalid_site_key']);
    }

    public function test_register_rejects_a_url_without_a_host(): void
    {
        $this->postJson('/api/courier-check/v1/register', [
            'site_url' => 'not a url',
        ])->assertStatus(422)
            ->assertExactJson(['error' => 'invalid_site_url']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function mockReport(array $report): void
    {
        $this->mock(FraudCheckCoordinator::class, function ($mock) use ($report) {
            $mock->shouldReceive('checkSingle')->once()->andReturn($report);
        });
    }

    /**
     * @return array{site_key: string, domain: string}
     */
    private function registerSite(string $siteUrl = 'https://example.com'): array
    {
        $response = $this->postJson('/api/courier-check/v1/register', [
            'site_url' => $siteUrl,
        ]);
        $response->assertOk();
        $payload = $response->json();
        $this->assertSame(['site_key', 'domain'], array_keys($payload));

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function historyReport(string $rate): array
    {
        return [
            'total_order' => 10,
            'confirmed' => 8,
            'cancel' => 2,
            'success_rate' => $rate,
            'courier' => [],
        ];
    }

    private function pluginUsage(string $siteKey, string $domain): int
    {
        $day = now(CourierCheckApiService::TIMEZONE)->toDateString();

        return max(
            (int) Cache::get('courier_check_api:key:'.$siteKey.':'.$day, 0),
            (int) Cache::get('courier_check_api:domain:'.$domain.':'.$day, 0),
        );
    }
}
