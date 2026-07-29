<?php

namespace Tests\Unit;

use App\Services\Messenger\MessengerPageOAuthService;
use Tests\TestCase;

class MessengerPageOAuthServiceTest extends TestCase
{
    public function test_business_login_uses_config_id_without_raw_scopes(): void
    {
        config()->set([
            'services.messenger.app_id' => '123456',
            'services.messenger.app_secret' => 'secret',
            'services.messenger.redirect' => 'https://app.wpsalehub.com/api/messenger/oauth/callback',
            'services.messenger.graph_version' => 'v21.0',
            'services.messenger.login_config_id' => '987654',
            'services.messenger.scopes' => 'pages_show_list,pages_messaging',
        ]);

        $query = $this->queryFor(app(MessengerPageOAuthService::class)->buildConnectUrl([
            'access_token_id' => 1,
            'site_url' => 'https://shop.example',
            'return_url' => 'https://shop.example/wp-admin/',
        ]));

        $this->assertSame('123456', $query['client_id'] ?? null);
        $this->assertSame('987654', $query['config_id'] ?? null);
        $this->assertSame('true', $query['override_default_response_type'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertArrayNotHasKey('scope', $query);
        $this->assertNotEmpty($query['state'] ?? null);
    }

    public function test_legacy_login_uses_scopes_when_config_id_is_missing(): void
    {
        config()->set([
            'services.messenger.app_id' => '123456',
            'services.messenger.app_secret' => 'secret',
            'services.messenger.redirect' => 'https://app.wpsalehub.com/api/messenger/oauth/callback',
            'services.messenger.graph_version' => 'v21.0',
            'services.messenger.login_config_id' => null,
            'services.messenger.scopes' => 'pages_show_list,pages_messaging',
        ]);

        $query = $this->queryFor(app(MessengerPageOAuthService::class)->buildConnectUrl([
            'access_token_id' => 1,
            'site_url' => 'https://shop.example',
            'return_url' => 'https://shop.example/wp-admin/',
        ]));

        $this->assertSame('pages_show_list,pages_messaging', $query['scope'] ?? null);
        $this->assertArrayNotHasKey('config_id', $query);
        $this->assertArrayNotHasKey('override_default_response_type', $query);
    }

    public function test_normalize_sender_gender(): void
    {
        $oauth = app(MessengerPageOAuthService::class);

        $this->assertSame('male', $oauth->normalizeSenderGender('male'));
        $this->assertSame('male', $oauth->normalizeSenderGender('Male'));
        $this->assertSame('female', $oauth->normalizeSenderGender('female'));
        $this->assertSame('female', $oauth->normalizeSenderGender('F'));
        $this->assertSame('', $oauth->normalizeSenderGender('unknown'));
        $this->assertSame('', $oauth->normalizeSenderGender(null));
    }

    public function test_fetch_sender_profile_requests_gender_for_messenger(): void
    {
        config()->set('services.messenger.graph_version', 'v21.0');
        \Illuminate\Support\Facades\Cache::flush();
        \Illuminate\Support\Facades\Http::fake([
            'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response([
                'name' => 'Sadia Khan',
                'profile_pic' => 'https://cdn.example.com/sadia.jpg',
                'gender' => 'female',
            ], 200),
        ]);

        $profile = app(MessengerPageOAuthService::class)->fetchSenderProfile(
            'PSID123',
            'PAGE_TOKEN',
            'messenger'
        );

        $this->assertSame('Sadia Khan', $profile['name']);
        $this->assertSame('https://cdn.example.com/sadia.jpg', $profile['profile_pic']);
        $this->assertSame('female', $profile['gender']);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/PSID123')
                && str_contains((string) ($data['fields'] ?? ''), 'gender')
                && str_contains((string) ($data['fields'] ?? ''), 'profile_pic');
        });
    }

    public function test_fetch_sender_profile_omits_gender_field_for_instagram(): void
    {
        config()->set('services.messenger.graph_version', 'v21.0');
        \Illuminate\Support\Facades\Cache::flush();
        \Illuminate\Support\Facades\Http::fake([
            'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response([
                'name' => 'shop_customer',
                'username' => 'shop_customer',
                'profile_pic' => 'https://cdn.example.com/ig.jpg',
            ], 200),
        ]);

        $profile = app(MessengerPageOAuthService::class)->fetchSenderProfile(
            'IGSID123',
            'PAGE_TOKEN',
            'instagram'
        );

        $this->assertSame('shop_customer', $profile['name']);
        $this->assertSame('', $profile['gender']);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $data = $request->data();
            $fields = (string) ($data['fields'] ?? '');

            return str_contains($request->url(), '/IGSID123')
                && str_contains($fields, 'username')
                && ! str_contains($fields, 'gender');
        });
    }

    /**
     * @return array<string, string>
     */
    private function queryFor(string $url): array
    {
        $this->assertStringStartsWith('https://www.facebook.com/v21.0/dialog/oauth?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return array_map('strval', $query);
    }
}
