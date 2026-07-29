<?php

namespace Tests\Unit;

use App\Services\Messenger\MessengerConversationHistorySync;
use App\Services\Messenger\MessengerPageOAuthService;
use App\Services\Messenger\WordPressMessengerForwarder;
use ReflectionMethod;
use Tests\TestCase;

class MessengerConversationHistorySyncTest extends TestCase
{
    private function invoke(object $service, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    private function service(): MessengerConversationHistorySync
    {
        return new MessengerConversationHistorySync(
            app(MessengerPageOAuthService::class),
            app(WordPressMessengerForwarder::class),
        );
    }

    public function test_normalize_history_marks_page_messages_as_echo(): void
    {
        $event = $this->invoke($this->service(), 'normalizeHistoryMessage', [
            'page_123',
            'psid_456',
            'Customer',
            [
                'id' => 'm_abc',
                'message' => 'Hello from page',
                'from' => ['id' => 'page_123'],
                'created_time' => '2026-07-27T10:00:00+0000',
            ],
        ]);

        $this->assertIsArray($event);
        $this->assertTrue($event['is_echo']);
        $this->assertSame('history_sync', $event['source']);
        $this->assertSame('m_abc', $event['message']['mid']);
        $this->assertSame('Hello from page', $event['message']['text']);
    }

    public function test_normalize_history_keeps_customer_inbound(): void
    {
        $event = $this->invoke($this->service(), 'normalizeHistoryMessage', [
            'page_123',
            'psid_456',
            'Customer',
            [
                'id' => 'm_in',
                'message' => 'Need price',
                'from' => ['id' => 'psid_456'],
                'created_time' => '2026-07-27T10:01:00+0000',
            ],
        ]);

        $this->assertIsArray($event);
        $this->assertFalse($event['is_echo']);
        $this->assertSame('Customer', $event['sender_profile']['name']);
        $this->assertSame('psid_456', $event['psid']);
    }

    public function test_normalize_attachments_maps_image_data(): void
    {
        $attachments = $this->invoke($this->service(), 'normalizeAttachments', [[
            'attachments' => [
                'data' => [
                    [
                        'mime_type' => 'image/jpeg',
                        'image_data' => ['url' => 'https://cdn.example/photo.jpg'],
                    ],
                ],
            ],
        ]]);

        $this->assertSame([
            ['type' => 'image', 'url' => 'https://cdn.example/photo.jpg'],
        ], $attachments);
    }

    public function test_resolve_customer_psid_skips_page_participant(): void
    {
        $psid = $this->invoke($this->service(), 'resolveCustomerPsid', [[
            'participants' => [
                'data' => [
                    ['id' => 'page_123', 'name' => 'Shop'],
                    ['id' => 'user_999', 'name' => 'Buyer'],
                ],
            ],
        ], ['page_123']]);

        $this->assertSame('user_999', $psid);
    }

    public function test_normalize_history_sets_instagram_channel(): void
    {
        $event = $this->invoke($this->service(), 'normalizeHistoryMessage', [
            'page_123',
            'igsid_456',
            'hulk__1829',
            [
                'id' => 'm_ig',
                'message' => 'Price koto',
                'from' => ['id' => 'igsid_456'],
                'created_time' => '2026-07-27T10:01:00+0000',
            ],
            'instagram',
            ['page_123', 'ig_biz_1'],
        ]);

        $this->assertIsArray($event);
        $this->assertSame('instagram', $event['channel']);
        $this->assertFalse($event['is_echo']);
        $this->assertSame('hulk__1829', $event['sender_profile']['name']);
    }

    public function test_resolve_customer_psid_skips_instagram_business_id(): void
    {
        $psid = $this->invoke($this->service(), 'resolveCustomerPsid', [[
            'participants' => [
                'data' => [
                    ['id' => 'ig_biz_1', 'username' => 'halalfabric'],
                    ['id' => 'igsid_999', 'username' => 'hulk__1829'],
                ],
            ],
        ], ['page_123', 'ig_biz_1']]);

        $this->assertSame('igsid_999', $psid);
    }
}
