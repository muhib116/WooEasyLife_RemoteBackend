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
