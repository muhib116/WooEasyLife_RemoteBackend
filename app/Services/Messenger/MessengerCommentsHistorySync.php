<?php

namespace App\Services\Messenger;

use App\Models\MessengerPageConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pull recent Page post comments from Graph and forward them to WordPress
 * using the same comments/inbound shape as live feed webhooks.
 */
class MessengerCommentsHistorySync
{
    public function __construct(
        protected MessengerPageOAuthService $oauth,
        protected WordPressMessengerForwarder $forwarder,
    ) {
    }

    /**
     * @param  array{max_posts?:int,max_comments_per_post?:int}  $options
     * @return array<string, mixed>
     */
    public function sync(MessengerPageConnection $connection, array $options = []): array
    {
        $pageId = trim((string) $connection->page_id);
        $token = (string) $connection->page_access_token;
        if ($pageId === '' || $token === '') {
            return [
                'ok' => false,
                'message' => 'Page connection is missing a page access token.',
                'posts' => 0,
                'comments' => 0,
                'forwarded' => 0,
            ];
        }

        // Ensure feed is still subscribed (idempotent).
        try {
            $this->oauth->subscribePageToWebhook($connection);
        } catch (\Throwable) {
            // Non-fatal — sync can still pull history if token has read scopes.
        }

        $maxPosts = max(1, min(50, (int) ($options['max_posts'] ?? 20)));
        $maxComments = max(1, min(100, (int) ($options['max_comments_per_post'] ?? 40)));

        $feed = $this->fetchFeedWithComments($pageId, $token, $maxPosts, $maxComments);
        if (! empty($feed['error'])) {
            return [
                'ok' => false,
                'message' => (string) $feed['error'],
                'posts' => 0,
                'comments' => 0,
                'forwarded' => 0,
            ];
        }

        $posts = is_array($feed['rows'] ?? null) ? $feed['rows'] : [];
        $events = [];
        $commentCount = 0;

        foreach ($posts as $post) {
            if (! is_array($post)) {
                continue;
            }
            $postId = trim((string) ($post['id'] ?? ''));
            if ($postId === '') {
                continue;
            }
            $permalink = (string) ($post['permalink_url'] ?? '');
            $postMessage = (string) ($post['message'] ?? '');
            $postStory = (string) ($post['story'] ?? '');
            $postPicture = (string) ($post['full_picture'] ?? ($post['picture'] ?? ''));
            $postCreated = (string) ($post['created_time'] ?? '');
            $comments = $post['comments']['data'] ?? [];
            if (! is_array($comments)) {
                continue;
            }
            // Graph newest-first; store oldest→newest.
            $comments = array_reverse($comments);
            foreach ($comments as $comment) {
                if (! is_array($comment)) {
                    continue;
                }
                $event = $this->normalizeComment($pageId, $postId, $permalink, $comment, [
                    'post_message' => $postMessage,
                    'post_story' => $postStory,
                    'post_picture' => $postPicture,
                    'post_created_time' => $postCreated,
                ]);
                if ($event === null) {
                    continue;
                }
                $events[] = $event;
                $commentCount++;
            }
        }

        $forwarded = 0;
        if ($events !== []) {
            // Chunk to keep WP payloads reasonable.
            foreach (array_chunk($events, 40) as $chunk) {
                $result = $this->forwarder->forwardCommentsInbound($connection, [
                    'events' => $chunk,
                    'source' => 'facebook_comment_history',
                ]);
                if (! empty($result['success'])) {
                    $forwarded += count($chunk);
                } else {
                    Log::warning('Comments history forward failed', [
                        'connection_id' => $connection->id,
                        'page_id' => $pageId,
                        'result' => $result,
                    ]);
                }
            }
        }

        $ok = $forwarded > 0 || $commentCount === 0;
        if ($commentCount === 0) {
            $message = 'No recent comments found on Page posts (or permission still missing — reconnect Page).';
        } elseif ($forwarded <= 0) {
            $ok = false;
            $message = "Found {$commentCount} comment(s) but could not save them to the store. Check site URL / hub→WP connection.";
        } elseif ($forwarded < $commentCount) {
            $message = "Synced {$forwarded} of {$commentCount} comment(s) from " . count($posts) . ' post(s).';
        } else {
            $message = "Synced {$forwarded} comment(s) from " . count($posts) . ' post(s).';
        }

        return [
            'ok' => $ok,
            'message' => $message,
            'posts' => count($posts),
            'comments' => $commentCount,
            'forwarded' => $forwarded,
        ];
    }

    /**
     * @return array{rows?:array<int,array<string,mixed>>,error?:string}
     */
    private function fetchFeedWithComments(string $pageId, string $token, int $maxPosts, int $maxComments): array
    {
        $version = $this->oauth->graphVersion();
        $fields = 'id,message,story,created_time,permalink_url,full_picture,picture,comments.limit('
            . $maxComments
            . '){id,from{id,name},message,created_time,permalink_url,parent{id}}';

        try {
            $response = Http::timeout(60)->get(
                'https://graph.facebook.com/' . $version . '/' . rawurlencode($pageId) . '/feed',
                [
                    'fields' => $fields,
                    'limit' => $maxPosts,
                    'access_token' => $token,
                ]
            );
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage() ?: 'Graph feed request failed.'];
        }

        if (! $response->successful()) {
            $fbMessage = (string) ($response->json('error.message') ?? '');
            Log::warning('Comments history feed failed', [
                'page_id' => $pageId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'error' => $fbMessage !== ''
                    ? $fbMessage
                    : 'Could not read Page feed. Reconnect the Page with comment permissions.',
            ];
        }

        $rows = $response->json('data');

        return [
            'rows' => is_array($rows) ? $rows : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $comment
     * @param  array{post_message?:string,post_story?:string,post_picture?:string,post_created_time?:string}  $postMeta
     * @return array<string, mixed>|null
     */
    private function normalizeComment(string $pageId, string $postId, string $postPermalink, array $comment, array $postMeta = []): ?array
    {
        $commentId = trim((string) ($comment['id'] ?? ''));
        if ($commentId === '') {
            return null;
        }

        $from = is_array($comment['from'] ?? null) ? $comment['from'] : [];
        $fromId = trim((string) ($from['id'] ?? ''));
        // Skip Page's own comments.
        if ($fromId !== '' && $fromId === $pageId) {
            return null;
        }

        $parentId = '';
        if (is_array($comment['parent'] ?? null)) {
            $parentId = trim((string) ($comment['parent']['id'] ?? ''));
        }

        $permalink = trim((string) ($comment['permalink_url'] ?? ''));
        if ($permalink === '') {
            $permalink = $postPermalink;
        }

        return [
            'event_type' => 'comment',
            'source' => 'facebook_comment_history',
            'page_id' => $pageId,
            'post_id' => $postId,
            'comment_id' => $commentId,
            'parent_id' => $parentId,
            'from_id' => $fromId,
            'from_name' => trim((string) ($from['name'] ?? '')),
            'message' => (string) ($comment['message'] ?? ''),
            'created_time' => (string) ($comment['created_time'] ?? ''),
            'verb' => 'add',
            'item' => $parentId !== '' ? 'reply' : 'comment',
            'permalink' => $permalink,
            'post_message' => (string) ($postMeta['post_message'] ?? ''),
            'post_story' => (string) ($postMeta['post_story'] ?? ''),
            'post_picture' => (string) ($postMeta['post_picture'] ?? ''),
            'post_created_time' => (string) ($postMeta['post_created_time'] ?? ''),
            'skip_jobs' => true,
        ];
    }
}
