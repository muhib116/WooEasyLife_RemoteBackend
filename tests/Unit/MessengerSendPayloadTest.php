<?php

namespace Tests\Unit;

use App\Models\MessengerPageConnection;
use App\Services\Messenger\MessengerPageOAuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessengerSendPayloadTest extends TestCase
{
    private function connection(): MessengerPageConnection
    {
        $connection = new MessengerPageConnection();
        $connection->page_id = '1234567890';
        $connection->page_access_token = 'test-page-token';
        $connection->status = 'connected';

        return $connection;
    }

    private function fakeGraph(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.test123'], 200),
        ]);
    }

    public function test_reply_to_is_sent_at_payload_root_not_inside_message(): void
    {
        $this->fakeGraph();

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendMessage($this->connection(), 'PSID123', 'Quoted reply', [
            'reply_to_mid' => 'm_realMetaMid123',
        ]);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            // Must be a sibling of message/recipient, per Meta Send API.
            $this->assertArrayHasKey('reply_to', $body, 'reply_to must be at payload root');
            $this->assertSame('m_realMetaMid123', $body['reply_to']['mid']);
            $this->assertArrayNotHasKey('reply_to', $body['message'], 'reply_to must NOT be nested in message');

            return true;
        });
    }

    public function test_synthetic_local_mids_are_not_sent_as_reply_to(): void
    {
        $this->fakeGraph();

        $service = app(MessengerPageOAuthService::class);
        $service->sendMessage($this->connection(), 'PSID123', 'No quote', [
            'reply_to_mid' => 'out_localsynthetic',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $this->assertArrayNotHasKey('reply_to', $body);

            return true;
        });
    }

    public function test_text_and_attachment_are_split_into_two_graph_calls(): void
    {
        $this->fakeGraph();

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendMessage($this->connection(), 'PSID123', 'Caption here', [
            'attachment' => [
                'type' => 'image',
                'payload' => ['url' => 'https://example.com/a.jpg', 'is_reusable' => true],
            ],
        ]);

        $this->assertTrue($result['ok']);
        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $message = $body['message'] ?? [];

            // Meta rejects text + attachment in one message payload.
            $this->assertFalse(
                isset($message['text']) && isset($message['attachment']),
                'text and attachment must never share one payload'
            );

            return true;
        });
    }

    public function test_attachment_only_send_omits_text(): void
    {
        $this->fakeGraph();

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendMessage($this->connection(), 'PSID123', '', [
            'attachment' => [
                'type' => 'audio',
                'payload' => ['url' => 'https://example.com/v.m4a', 'is_reusable' => true],
            ],
        ]);

        $this->assertTrue($result['ok']);
        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            $message = $request->data()['message'] ?? [];
            $this->assertArrayNotHasKey('text', $message);
            $this->assertSame('audio', $message['attachment']['type']);

            return true;
        });
    }

    public function test_meta_attachment_id_is_preferred_over_url(): void
    {
        $this->fakeGraph();

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendMessage($this->connection(), 'PSID123', '', [
            'attachment' => [
                'type' => 'image',
                'payload' => ['attachment_id' => '9876543210'],
            ],
        ]);

        $this->assertTrue($result['ok']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $message = $request->data()['message'] ?? [];
            $this->assertSame('9876543210', $message['attachment']['payload']['attachment_id'] ?? null);
            $this->assertArrayNotHasKey('url', $message['attachment']['payload'] ?? []);

            return true;
        });
    }

    public function test_empty_message_is_rejected_before_calling_graph(): void
    {
        $this->fakeGraph();

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendMessage($this->connection(), 'PSID123', '', []);

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }

    public function test_sender_action_posts_only_recipient_and_action(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['recipient_id' => 'PSID123'], 200),
        ]);

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendSenderAction($this->connection(), 'PSID123', 'typing_on');

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            $body = $request->data();
            $this->assertSame('typing_on', $body['sender_action'] ?? null);
            $this->assertSame('PSID123', $body['recipient']['id'] ?? null);
            // Sender actions must not carry a message payload.
            $this->assertArrayNotHasKey('message', $body);

            return true;
        });
    }

    public function test_sender_action_normalizes_unknown_action_to_typing_on(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['recipient_id' => 'PSID123'], 200),
        ]);

        $service = app(MessengerPageOAuthService::class);
        $service->sendSenderAction($this->connection(), 'PSID123', 'dance');

        Http::assertSent(function ($request) {
            return ($request->data()['sender_action'] ?? null) === 'typing_on';
        });
    }

    public function test_quick_replies_are_attached_to_text_message(): void
    {
        $this->fakeGraph();

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendMessage($this->connection(), 'PSID123', 'Order summary', [
            'quick_replies' => [
                ['title' => 'কনফার্ম', 'payload' => 'WEL_ORDER_CONFIRM'],
                ['title' => 'ঠিক নেই', 'payload' => 'WEL_ORDER_EDIT'],
                ['title' => '', 'payload' => 'SKIP'],
            ],
        ]);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $replies = $body['message']['quick_replies'] ?? null;
            $this->assertIsArray($replies);
            $this->assertCount(2, $replies);
            $this->assertSame('text', $replies[0]['content_type']);
            $this->assertSame('কনফার্ম', $replies[0]['title']);
            $this->assertSame('WEL_ORDER_CONFIRM', $replies[0]['payload']);
            $this->assertSame('ঠিক নেই', $replies[1]['title']);

            return true;
        });
    }

    public function test_generic_template_attachment_is_preserved_in_graph_body(): void
    {
        $this->fakeGraph();

        $template = [
            'type' => 'template',
            'payload' => [
                'template_type' => 'generic',
                'elements' => [
                    [
                        'title' => 'Daily Care Oil',
                        'image_url' => 'https://cdn.example.com/oil.jpg',
                        'subtitle' => '৳ 450',
                        'buttons' => [
                            [
                                'type' => 'postback',
                                'title' => 'নিতে চাই',
                                'payload' => 'WEL_PRODUCT_SELECT:42',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $service = app(MessengerPageOAuthService::class);
        $result = $service->sendMessage($this->connection(), 'PSID123', '', [
            'attachment' => $template,
        ]);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) use ($template) {
            $body = $request->data();
            $attachment = $body['message']['attachment'] ?? null;
            $this->assertIsArray($attachment);
            $this->assertSame('template', $attachment['type'] ?? null);
            $this->assertSame('generic', $attachment['payload']['template_type'] ?? null);
            $this->assertSame(
                'WEL_PRODUCT_SELECT:42',
                $attachment['payload']['elements'][0]['buttons'][0]['payload'] ?? null
            );
            $this->assertSame($template['payload']['elements'][0]['image_url'], $attachment['payload']['elements'][0]['image_url'] ?? null);
            $this->assertArrayNotHasKey('text', $body['message'] ?? []);

            return true;
        });
    }

    public function test_delete_message_issues_graph_delete(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);

        $service = app(MessengerPageOAuthService::class);
        $result = $service->deleteMessage($this->connection(), 'm_realMetaMid123');

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/m_realMetaMid123');
        });
    }

    public function test_delete_message_rejects_synthetic_mids(): void
    {
        Http::fake();

        $service = app(MessengerPageOAuthService::class);
        $result = $service->deleteMessage($this->connection(), 'out_local');

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }
}
