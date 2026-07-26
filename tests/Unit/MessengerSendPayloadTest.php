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
}
