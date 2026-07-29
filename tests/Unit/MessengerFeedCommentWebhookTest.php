<?php

namespace Tests\Unit;

use App\Http\Controllers\Messenger\MessengerWebhookController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MessengerFeedCommentWebhookTest extends TestCase
{
    private function normalize(array $change, string $pageId = 'PAGE123'): ?array
    {
        $controller = new MessengerWebhookController();
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('normalizeFeedCommentChange');
        $method->setAccessible(true);

        return $method->invoke($controller, $pageId, $change);
    }

    public function test_normalizes_comment_add(): void
    {
        $event = $this->normalize([
            'field' => 'feed',
            'value' => [
                'item' => 'comment',
                'verb' => 'add',
                'comment_id' => '111_222',
                'post_id' => 'PAGE123_999',
                'parent_id' => 'PAGE123_999',
                'message' => 'দাম কত?',
                'created_time' => 1700000000,
                'from' => ['id' => 'USER1', 'name' => 'Test User'],
            ],
        ]);

        $this->assertNotNull($event);
        $this->assertSame('comment', $event['event_type']);
        $this->assertSame('facebook_comment', $event['source']);
        $this->assertSame('PAGE123', $event['page_id']);
        $this->assertSame('111_222', $event['comment_id']);
        $this->assertSame('PAGE123_999', $event['post_id']);
        $this->assertSame('', $event['parent_id']);
        $this->assertSame('USER1', $event['from_id']);
        $this->assertSame('দাম কত?', $event['message']);
        $this->assertSame('add', $event['verb']);
    }

    public function test_ignores_non_feed_and_non_comment(): void
    {
        $this->assertNull($this->normalize(['field' => 'messages', 'value' => ['item' => 'comment', 'verb' => 'add', 'comment_id' => '1']]));
        $this->assertNull($this->normalize([
            'field' => 'feed',
            'value' => ['item' => 'status', 'verb' => 'add', 'comment_id' => '1'],
        ]));
    }

    public function test_ignores_page_own_comment(): void
    {
        $this->assertNull($this->normalize([
            'field' => 'feed',
            'value' => [
                'item' => 'comment',
                'verb' => 'add',
                'comment_id' => '1',
                'post_id' => 'PAGE123_9',
                'from' => ['id' => 'PAGE123', 'name' => 'Shop'],
                'message' => 'Thanks',
            ],
        ]));
    }

    public function test_keeps_nested_parent_comment_id(): void
    {
        $event = $this->normalize([
            'field' => 'feed',
            'value' => [
                'item' => 'comment',
                'verb' => 'add',
                'comment_id' => 'c2',
                'post_id' => 'PAGE123_9',
                'parent_id' => 'c1',
                'from' => ['id' => 'U2', 'name' => 'B'],
                'message' => 'ok',
            ],
        ]);

        $this->assertNotNull($event);
        $this->assertSame('c1', $event['parent_id']);
    }
}
