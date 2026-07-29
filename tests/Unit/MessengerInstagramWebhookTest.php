<?php

namespace Tests\Unit;

use App\Http\Controllers\Messenger\MessengerWebhookController;
use ReflectionMethod;
use Tests\TestCase;

class MessengerInstagramWebhookTest extends TestCase
{
    public function test_normalize_instagram_event_sets_channel_and_ig_account(): void
    {
        $controller = app(MessengerWebhookController::class);
        $method = new ReflectionMethod($controller, 'normalizeMessagingEvent');
        $method->setAccessible(true);

        $event = $method->invoke(
            $controller,
            '',
            [
                'sender' => ['id' => 'IGSID_CUSTOMER'],
                'recipient' => ['id' => 'IG_BUSINESS_123'],
                'timestamp' => 1710000000000,
                'message' => [
                    'mid' => 'mid.ig.test',
                    'text' => 'hello from ig',
                ],
            ],
            'instagram',
            'IG_BUSINESS_123'
        );

        $this->assertIsArray($event);
        $this->assertSame('instagram', $event['channel']);
        $this->assertSame('IGSID_CUSTOMER', $event['psid']);
        $this->assertSame('IG_BUSINESS_123', $event['instagram_business_account_id']);
        $this->assertSame('hello from ig', $event['message']['text']);
    }

    public function test_receive_accepts_instagram_object_type(): void
    {
        $source = file_get_contents(
            base_path('app/Http/Controllers/Messenger/MessengerWebhookController.php')
        );
        $this->assertIsString($source);
        $this->assertStringContainsString("['page', 'instagram']", $source);
        $this->assertStringContainsString("\$object === 'instagram' ? 'instagram' : 'messenger'", $source);
        $this->assertStringContainsString("where('instagram_business_account_id'", $source);
    }

    public function test_page_object_still_routes_as_messenger_channel(): void
    {
        $controller = app(MessengerWebhookController::class);
        $method = new ReflectionMethod($controller, 'normalizeMessagingEvent');
        $method->setAccessible(true);

        $event = $method->invoke(
            $controller,
            'PAGE_FB_1',
            [
                'sender' => ['id' => 'PSID_CUSTOMER'],
                'recipient' => ['id' => 'PAGE_FB_1'],
                'timestamp' => 1710000000000,
                'message' => [
                    'mid' => 'mid.fb.test',
                    'text' => 'hello from fb',
                ],
            ],
            'messenger',
            'PAGE_FB_1'
        );

        $this->assertIsArray($event);
        $this->assertSame('messenger', $event['channel']);
        $this->assertSame('PAGE_FB_1', $event['page_id']);
        $this->assertArrayNotHasKey('instagram_business_account_id', $event);
    }
}
