<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCommentsInboundForward;
use App\Jobs\ProcessMessengerInboundForward;
use App\Models\MessengerPageConnection;
use App\Services\Messenger\MessengerAnonymizedIntentPacks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MessengerWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $expected = trim((string) config('services.messenger.webhook_verify_token', ''));

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        if (! $this->signatureValid($request)) {
            Log::warning('Messenger webhook signature rejected', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 403);
        }

        $payload = $request->all();
        $object = strtolower(trim((string) ($payload['object'] ?? '')));

        // Messenger = "page"; Instagram Messaging = "instagram".
        if (! in_array($object, ['page', 'instagram'], true)) {
            return response()->json([
                'status' => 'success',
                'message' => 'Ignored non-messaging webhook.',
            ]);
        }

        $channel = $object === 'instagram' ? 'instagram' : 'messenger';
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
        $events = [];
        $commentEvents = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entryId = (string) ($entry['id'] ?? '');
            // Page webhooks: entry.id = Facebook Page ID.
            // Instagram webhooks: entry.id = Instagram Business Account ID (map → page later).
            $pageId = $channel === 'messenger' ? $entryId : '';

            $buckets = [];
            if (is_array($entry['messaging'] ?? null)) {
                $buckets[] = $entry['messaging'];
            }
            if (is_array($entry['standby'] ?? null)) {
                $buckets[] = $entry['standby'];
            }

            foreach ($buckets as $messaging) {
                foreach ($messaging as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $normalized = $this->normalizeMessagingEvent($pageId, $item, $channel, $entryId);
                    if ($normalized !== null) {
                        $events[] = $normalized;
                    }
                }
            }

            // Facebook Page feed comments (object=page only).
            if ($channel === 'messenger' && is_array($entry['changes'] ?? null)) {
                foreach ($entry['changes'] as $change) {
                    if (! is_array($change)) {
                        continue;
                    }
                    $normalizedComment = $this->normalizeFeedCommentChange($pageId, $change);
                    if ($normalizedComment !== null) {
                        $commentEvents[] = $normalizedComment;
                    }
                }
            }
        }

        $dispatched = 0;
        $dispatchedComments = 0;

        if ($events !== []) {
            // Group by lookup key: page_id for Messenger, IG business account id for Instagram.
            $byLookup = [];
            foreach ($events as $event) {
                $lookup = $channel === 'instagram'
                    ? (string) ($event['instagram_business_account_id'] ?? '')
                    : (string) ($event['page_id'] ?? '');
                if ($lookup === '') {
                    continue;
                }
                $byLookup[$lookup][] = $event;
            }

            foreach ($byLookup as $lookupId => $pageEvents) {
                $connections = $channel === 'instagram'
                    ? MessengerPageConnection::query()
                        ->connected()
                        ->where('instagram_business_account_id', $lookupId)
                        ->orderByDesc('id')
                        ->get()
                    : MessengerPageConnection::query()
                        ->connected()
                        ->where('page_id', $lookupId)
                        ->orderByDesc('id')
                        ->get();

                if ($connections->isEmpty()) {
                    Log::info('Messenger webhook: no connected page', [
                        'channel' => $channel,
                        'lookup_id' => $lookupId,
                    ]);
                    continue;
                }

                foreach ($connections as $connection) {
                    // Ensure WP always gets Facebook page_id even for Instagram webhooks.
                    $forwardEvents = array_map(static function (array $event) use ($connection, $channel) {
                        $event['page_id'] = (string) $connection->page_id;
                        $event['channel'] = $channel;
                        unset($event['instagram_business_account_id']);

                        return $event;
                    }, $pageEvents);

                    ProcessMessengerInboundForward::dispatchAfterResponse(
                        (int) $connection->id,
                        $forwardEvents
                    );
                    $dispatched++;
                }
            }
        }

        if ($commentEvents !== []) {
            $byPage = [];
            foreach ($commentEvents as $event) {
                $pid = (string) ($event['page_id'] ?? '');
                if ($pid === '') {
                    continue;
                }
                $byPage[$pid][] = $event;
            }

            foreach ($byPage as $lookupId => $pageComments) {
                $connections = MessengerPageConnection::query()
                    ->connected()
                    ->where('page_id', $lookupId)
                    ->orderByDesc('id')
                    ->get();

                if ($connections->isEmpty()) {
                    Log::info('Comments webhook: no connected page', [
                        'page_id' => $lookupId,
                    ]);
                    continue;
                }

                foreach ($connections as $connection) {
                    ProcessCommentsInboundForward::dispatchAfterResponse(
                        (int) $connection->id,
                        $pageComments
                    );
                    $dispatchedComments++;
                }
            }
        }

        if ($dispatched === 0 && $dispatchedComments === 0) {
            return response()->json([
                'status' => 'success',
                'message' => 'No actionable Messenger/comment events.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received successfully.',
            'queued_forwards' => $dispatched,
            'queued_comment_forwards' => $dispatchedComments,
            'channel' => $channel,
        ]);
    }

    /**
     * Optional anonymized intent/guard packs for store sales agents (no PII).
     */
    public function intentPacks(MessengerAnonymizedIntentPacks $packs)
    {
        return response()->json([
            'status' => 'success',
            'data' => $packs->packs(),
        ]);
    }

    /**
     * Verify Meta's X-Hub-Signature-256 against the raw body using the app secret.
     */
    private function signatureValid(Request $request): bool
    {
        $secret = trim((string) (
            config('services.messenger.app_secret')
            ?: config('services.facebook.client_secret')
            ?: ''
        ));

        // If no secret is configured we cannot verify; fail closed in production.
        if ($secret === '') {
            return app()->environment('local');
        }

        $header = (string) (
            $request->header('X-Hub-Signature-256')
            ?: $request->header('X-Hub-Signature')
            ?: ''
        );

        if ($header === '' || ! str_contains($header, '=')) {
            return false;
        }

        [$algo, $hash] = explode('=', $header, 2);
        $algo = strtolower(trim($algo)) === 'sha1' ? 'sha1' : 'sha256';

        $expected = hash_hmac($algo, $request->getContent(), $secret);

        return hash_equals($expected, trim($hash));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  'messenger'|'instagram'  $channel
     * @return array<string, mixed>|null
     */
    private function normalizeMessagingEvent(
        string $pageId,
        array $item,
        string $channel = 'messenger',
        string $entryId = ''
    ): ?array {
        $senderId = (string) ($item['sender']['id'] ?? '');
        $recipientId = (string) ($item['recipient']['id'] ?? '');
        $message = is_array($item['message'] ?? null) ? $item['message'] : null;
        $resolvedPageId = $pageId !== '' ? $pageId : ($channel === 'messenger' ? $recipientId : '');

        // Reactions arrive as a standalone webhook event, not as a message.
        $reaction = is_array($item['reaction'] ?? null) ? $item['reaction'] : null;
        if ($reaction === null && is_array($item['message']['reaction'] ?? null)) {
            // Rare nested shape some Graph payloads use.
            $reaction = $item['message']['reaction'];
        }
        if ($reaction !== null) {
            $targetMid = trim((string) (
                $reaction['mid']
                ?? $reaction['message_id']
                ?? $item['message']['mid']
                ?? ''
            ));
            if ($targetMid === '' || $senderId === '') {
                Log::info('Messenger webhook: reaction ignored (missing mid/sender)', [
                    'page_id' => $resolvedPageId,
                    'channel' => $channel,
                    'sender_id' => $senderId,
                    'reaction' => $reaction,
                ]);

                return null;
            }

            $psid = ($resolvedPageId !== '' && $senderId === $resolvedPageId)
                ? $recipientId
                : $senderId;
            // Instagram: business account id is in entry / recipient, never treat as customer.
            if ($channel === 'instagram' && $entryId !== '' && $senderId === $entryId) {
                $psid = $recipientId;
            }

            $event = [
                'page_id' => $resolvedPageId,
                'psid' => $psid,
                'channel' => $channel,
                'event_type' => 'reaction',
                'is_echo' => false,
                'sender_profile' => [
                    'name' => '',
                    'profile_pic' => '',
                ],
                'reaction' => [
                    'mid' => $targetMid,
                    'action' => (string) ($reaction['action'] ?? 'react'),
                    'reaction' => (string) ($reaction['reaction'] ?? 'other'),
                    'emoji' => (string) ($reaction['emoji'] ?? ''),
                ],
            ];
            if ($channel === 'instagram' && $entryId !== '') {
                $event['instagram_business_account_id'] = $entryId;
            }

            return $event;
        }

        // Page-sent echoes: sender is the Page, recipient is the customer PSID.
        // We forward these so WordPress can replace temporary local media URLs
        // with Facebook CDN URLs (same display path as inbound customer media).
        $isEcho = is_array($message) && ! empty($message['is_echo']);
        if ($isEcho) {
            if ($recipientId === '') {
                return null;
            }
            if ($channel === 'messenger' && $pageId === '') {
                return null;
            }

            $type = 'text';
            $text = (string) ($message['text'] ?? '');
            $attachments = [];
            $replyToMid = '';

            if (! empty($message['reply_to']['mid'])) {
                $replyToMid = (string) $message['reply_to']['mid'];
            }

            if (! empty($message['attachments']) && is_array($message['attachments'])) {
                foreach ($message['attachments'] as $attachment) {
                    if (! is_array($attachment)) {
                        continue;
                    }
                    $attachments[] = [
                        'type' => (string) ($attachment['type'] ?? 'file'),
                        'url' => (string) ($attachment['payload']['url'] ?? ''),
                    ];
                    $type = (string) ($attachment['type'] ?? 'file');
                }
            }

            $event = [
                'page_id' => $resolvedPageId,
                'psid' => $recipientId,
                'channel' => $channel,
                'is_echo' => true,
                'sender_profile' => [
                    'name' => '',
                    'profile_pic' => '',
                ],
                'message' => [
                    'mid' => (string) ($message['mid'] ?? ('echo_' . md5(json_encode($item)))),
                    'type' => $type,
                    'text' => $text,
                    'attachments' => $attachments,
                    'reply_to_mid' => $replyToMid,
                    'is_echo' => true,
                    'referral' => null,
                ],
            ];
            if ($channel === 'instagram' && $entryId !== '') {
                $event['instagram_business_account_id'] = $entryId;
            }

            return $event;
        }

        // Echo / page-as-sender without is_echo payload — ignore.
        if ($senderId === '') {
            return null;
        }
        if ($channel === 'messenger' && $senderId === $pageId) {
            return null;
        }
        if ($channel === 'instagram' && $entryId !== '' && $senderId === $entryId) {
            return null;
        }

        if ($message === null) {
            // Still store postbacks as text for inbox visibility.
            if (isset($item['postback']['payload'])) {
                $payload = (string) $item['postback']['payload'];
                $title = trim((string) ($item['postback']['title'] ?? ''));
                // Keep structured WEL payloads intact for WordPress (product lock / order confirm).
                if (! preg_match('/^WEL_(?:PRODUCT_SELECT:\d+|ORDER_CONFIRM|ORDER_EDIT)$/i', $payload)) {
                    if ($title !== '') {
                        $payload = $title;
                    }
                }
                $message = [
                    'mid' => 'postback_' . md5(json_encode($item)),
                    'text' => $payload,
                    'type' => 'postback',
                ];
            } else {
                return null;
            }
        }

        $type = 'text';
        $text = (string) ($message['text'] ?? '');
        $attachments = [];
        $replyToMid = '';

        // Normalize order-confirm / product-select quick-reply payloads for WordPress.
        $qrPayload = trim((string) ($message['quick_reply']['payload'] ?? ''));
        if ($qrPayload === 'WEL_ORDER_CONFIRM') {
            $text = 'কনফার্ম';
            $type = 'quick_reply';
        } elseif ($qrPayload === 'WEL_ORDER_EDIT') {
            $text = 'ঠিক নেই';
            $type = 'quick_reply';
        } elseif (preg_match('/^WEL_PRODUCT_SELECT:\d+$/i', $qrPayload)) {
            $text = $qrPayload;
            $type = 'quick_reply';
        } elseif ($qrPayload !== '' && $text === '') {
            $text = $qrPayload;
            $type = 'quick_reply';
        }

        if (! empty($message['reply_to']['mid'])) {
            $replyToMid = (string) $message['reply_to']['mid'];
        }

        if (! empty($message['attachments']) && is_array($message['attachments'])) {
            foreach ($message['attachments'] as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }
                $attachments[] = [
                    'type' => (string) ($attachment['type'] ?? 'file'),
                    'url' => (string) ($attachment['payload']['url'] ?? ''),
                ];
                $type = (string) ($attachment['type'] ?? 'file');
            }
            if ($text === '' && $attachments !== []) {
                // Keep body empty — UI renders the attachment. Preview is set on the WP side.
                $text = '';
            }
        }

        $referral = null;
        if (isset($item['referral']) && is_array($item['referral'])) {
            $referral = [
                'ad_id' => $item['referral']['ad_id'] ?? null,
                'ref' => $item['referral']['ref'] ?? null,
            ];
        }

        $event = [
            'page_id' => $resolvedPageId,
            'psid' => $senderId,
            'channel' => $channel,
            'is_echo' => false,
            'sender_profile' => [
                'name' => '',
                'profile_pic' => '',
            ],
            'message' => [
                'mid' => (string) ($message['mid'] ?? ('evt_' . md5(json_encode($item)))),
                'type' => $type,
                'text' => $text,
                'attachments' => $attachments,
                'reply_to_mid' => $replyToMid,
                'is_echo' => false,
                'referral' => $referral,
            ],
        ];
        if ($channel === 'instagram' && $entryId !== '') {
            $event['instagram_business_account_id'] = $entryId;
        }

        return $event;
    }

    /**
     * Normalize Meta Page feed webhook `changes[]` items into comment events.
     *
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>|null
     */
    public function normalizeFeedCommentChange(string $pageId, array $change): ?array
    {
        $field = strtolower(trim((string) ($change['field'] ?? '')));
        if ($field !== 'feed') {
            return null;
        }

        $value = is_array($change['value'] ?? null) ? $change['value'] : [];
        $item = strtolower(trim((string) ($value['item'] ?? '')));
        $verb = strtolower(trim((string) ($value['verb'] ?? '')));

        // Focus on comment create/edit/remove; ignore status/photo/etc.
        if (! in_array($item, ['comment', 'reply'], true)) {
            return null;
        }
        if (! in_array($verb, ['add', 'edited', 'edit', 'remove', 'hide', 'unhide'], true)) {
            return null;
        }

        $commentId = trim((string) ($value['comment_id'] ?? ''));
        if ($commentId === '') {
            return null;
        }

        $from = is_array($value['from'] ?? null) ? $value['from'] : [];
        $fromId = trim((string) ($from['id'] ?? ''));
        $fromName = trim((string) ($from['name'] ?? ''));

        // Ignore Page's own comments (echoes) when from.id matches the Page.
        if ($fromId !== '' && $pageId !== '' && hash_equals($pageId, $fromId)) {
            return null;
        }

        $postId = trim((string) ($value['post_id'] ?? ($value['parent_id'] ?? '')));
        // parent_id may be the post OR a parent comment for nested replies.
        $parentId = trim((string) ($value['parent_id'] ?? ''));
        if ($parentId === $postId) {
            $parentId = '';
        }

        $message = trim((string) ($value['message'] ?? ''));
        $createdTime = trim((string) ($value['created_time'] ?? ''));
        if ($createdTime !== '' && is_numeric($createdTime)) {
            $createdTime = gmdate('c', (int) $createdTime);
        }

        $permalink = trim((string) ($value['post']['permalink_url'] ?? ($value['permalink_url'] ?? '')));

        return [
            'event_type' => 'comment',
            'source' => 'facebook_comment',
            'page_id' => $pageId,
            'post_id' => $postId,
            'comment_id' => $commentId,
            'parent_id' => $parentId,
            'from_id' => $fromId,
            'from_name' => $fromName,
            'message' => $message,
            'created_time' => $createdTime,
            'verb' => $verb === 'edit' ? 'edited' : $verb,
            'item' => $item,
            'permalink' => $permalink,
        ];
    }
}
