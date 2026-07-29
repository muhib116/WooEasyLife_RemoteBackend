<?php

namespace App\Services\Messenger;

use App\Models\MessengerPageConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pull recent Messenger threads from Graph and forward them to WordPress
 * using the same normalized inbound shape as live webhooks.
 */
class MessengerConversationHistorySync
{
    public function __construct(
        protected MessengerPageOAuthService $oauth,
        protected WordPressMessengerForwarder $forwarder,
    ) {
    }

    /**
     * @param  array{
     *   max_conversations?:int,
     *   max_messages_per_conversation?:int,
     *   channel?:string
     * }  $options
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
                'conversations' => 0,
                'messages' => 0,
                'forwarded' => 0,
            ];
        }

        $channel = strtolower(trim((string) ($options['channel'] ?? 'messenger')));
        if (! in_array($channel, ['messenger', 'instagram'], true)) {
            $channel = 'messenger';
        }
        $platform = $channel === 'instagram' ? 'instagram' : 'messenger';
        $label = $channel === 'instagram' ? 'Instagram' : 'Messenger';

        $igAccountId = trim((string) ($connection->instagram_business_account_id ?? ''));
        if ($channel === 'instagram' && $igAccountId === '') {
            try {
                $this->oauth->syncInstagramLinkage($connection, true);
                $connection->refresh();
                $igAccountId = trim((string) ($connection->instagram_business_account_id ?? ''));
            } catch (\Throwable) {
                // Continue — Graph may still return IG conversations for the Page.
            }
        }

        $businessIds = array_values(array_filter(array_unique([
            $pageId,
            $igAccountId,
        ])));

        $maxConversations = max(1, min(50, (int) ($options['max_conversations'] ?? 25)));
        $maxMessages = max(1, min(100, (int) ($options['max_messages_per_conversation'] ?? 40)));

        $list = $this->fetchConversations($pageId, $token, $maxConversations, $platform);
        if (! empty($list['error'])) {
            return [
                'ok' => false,
                'message' => (string) $list['error'],
                'conversations' => 0,
                'messages' => 0,
                'forwarded' => 0,
                'channel' => $channel,
            ];
        }

        $conversations = is_array($list['rows'] ?? null) ? $list['rows'] : [];
        $messageCount = 0;
        $forwarded = 0;
        $profileCache = [];

        foreach ($conversations as $conversation) {
            $conversationId = trim((string) ($conversation['id'] ?? ''));
            if ($conversationId === '') {
                continue;
            }

            $psid = $this->resolveCustomerPsid($conversation, $businessIds);
            if ($psid === '') {
                continue;
            }

            $participantName = $this->resolveParticipantName($conversation, $psid);
            $messages = $this->fetchMessages($conversationId, $token, $maxMessages);
            // Graph returns newest-first; store oldest→newest so list previews land correctly.
            $messages = array_reverse($messages);

            $events = [];
            foreach ($messages as $message) {
                $event = $this->normalizeHistoryMessage(
                    $pageId,
                    $psid,
                    $participantName,
                    $message,
                    $channel,
                    $businessIds
                );
                if ($event === null) {
                    continue;
                }
                $events[] = $event;
                $messageCount++;
            }

            if ($events === []) {
                continue;
            }

            // Enrich this thread's inbound profile once (best effort).
            if (! array_key_exists($psid, $profileCache)) {
                $profileCache[$psid] = $this->oauth->fetchSenderProfile($psid, $token, $channel);
            }
            $profile = $profileCache[$psid];
            $profileName = (string) ($profile['name'] ?? '');
            $profilePic = (string) ($profile['profile_pic'] ?? '');
            $profileUsername = (string) ($profile['username'] ?? '');
            if ($profileName === '' && $participantName !== '') {
                $profileName = $participantName;
            }
            if ($profilePic === '' && $channel === 'instagram') {
                $profilePic = $this->oauth->instagramPublicAvatarUrl(
                    $profileUsername !== '' ? $profileUsername : $participantName
                );
            }
            if ($profileName !== '' || $profilePic !== '') {
                foreach ($events as &$event) {
                    if (! empty($event['is_echo'])) {
                        continue;
                    }
                    $event['sender_profile'] = [
                        'name' => $profileName !== ''
                            ? $profileName
                            : (string) ($event['sender_profile']['name'] ?? ''),
                        'profile_pic' => $profilePic,
                        'username' => $profileUsername,
                    ];
                }
                unset($event);
            }

            foreach (array_chunk($events, 25) as $chunk) {
                $result = $this->forwarder->forwardInbound($connection, [
                    'events' => $chunk,
                    'source' => 'history_sync',
                ]);
                if (! empty($result['success'])) {
                    $forwarded += count($chunk);
                } else {
                    Log::warning('Messenger history sync forward failed', [
                        'page_id' => $pageId,
                        'channel' => $channel,
                        'conversation_id' => $conversationId,
                        'chunk_size' => count($chunk),
                        'result' => $result,
                    ]);
                }
            }
        }

        if ($messageCount === 0) {
            return [
                'ok' => true,
                'message' => "No {$label} conversations found to import.",
                'conversations' => count($conversations),
                'messages' => 0,
                'forwarded' => 0,
                'channel' => $channel,
            ];
        }

        return [
            'ok' => $forwarded > 0,
            'message' => $forwarded > 0
                ? (
                    $forwarded < $messageCount
                        ? "Imported {$label} history with some forwarding gaps."
                        : "Imported {$label} conversation history."
                )
                : 'Fetched history but could not reach WordPress.',
            'conversations' => count($conversations),
            'messages' => $messageCount,
            'forwarded' => $forwarded,
            'partial' => $forwarded > 0 && $forwarded < $messageCount,
            'channel' => $channel,
        ];
    }

    /**
     * @param  'messenger'|'instagram'  $platform
     * @return array{rows?:array<int, array<string, mixed>>, error?:string}
     */
    private function fetchConversations(string $pageId, string $token, int $limit, string $platform = 'messenger'): array
    {
        $url = 'https://graph.facebook.com/' . $this->oauth->graphVersion() . '/' . $pageId . '/conversations';
        $rows = [];
        $after = null;
        $firstError = '';
        $fields = $platform === 'instagram'
            ? 'id,updated_time,participants{id,username,name}'
            : 'id,updated_time,participants{id,name}';

        while (count($rows) < $limit) {
            $query = [
                'platform' => $platform,
                'fields' => $fields,
                'limit' => min(25, $limit - count($rows)),
                'access_token' => $token,
            ];
            if ($after) {
                $query['after'] = $after;
            }

            $response = Http::timeout(30)->get($url, $query);
            if (! $response->successful()) {
                $graphMessage = (string) (
                    $response->json('error.message')
                    ?: $response->json('error.error_user_msg')
                    ?: ''
                );
                $firstError = $graphMessage !== ''
                    ? $graphMessage
                    : ('Facebook conversations API failed (HTTP ' . $response->status() . ').');

                Log::warning('Messenger conversations list failed', [
                    'page_id' => $pageId,
                    'platform' => $platform,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json('data');
            if (! is_array($data) || $data === []) {
                break;
            }

            foreach ($data as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
                if (count($rows) >= $limit) {
                    break;
                }
            }

            $after = $response->json('paging.cursors.after');
            if (! is_string($after) || $after === '') {
                break;
            }
        }

        // Only treat as hard failure when we got nothing from Graph.
        if ($rows === [] && $firstError !== '') {
            return ['error' => $firstError];
        }

        return ['rows' => $rows];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMessages(string $conversationId, string $token, int $limit): array
    {
        $url = 'https://graph.facebook.com/' . $this->oauth->graphVersion() . '/' . $conversationId . '/messages';
        $response = Http::timeout(30)->get($url, [
            'fields' => 'id,message,from,created_time,sticker,attachments{mime_type,file_url,image_data,video_data}',
            'limit' => $limit,
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            Log::warning('Messenger conversation messages failed', [
                'conversation_id' => $conversationId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json('data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $conversation
     * @param  array<int, string>  $businessIds  Page ID and/or IG business account ID to skip
     */
    private function resolveCustomerPsid(array $conversation, array $businessIds): string
    {
        $participants = $conversation['participants']['data'] ?? null;
        if (! is_array($participants)) {
            return '';
        }

        $skip = array_fill_keys(array_filter(array_map('strval', $businessIds)), true);

        foreach ($participants as $participant) {
            if (! is_array($participant)) {
                continue;
            }
            $id = trim((string) ($participant['id'] ?? ''));
            if ($id !== '' && ! isset($skip[$id])) {
                return $id;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $conversation
     */
    private function resolveParticipantName(array $conversation, string $psid): string
    {
        $participants = $conversation['participants']['data'] ?? null;
        if (! is_array($participants)) {
            return '';
        }

        foreach ($participants as $participant) {
            if (! is_array($participant)) {
                continue;
            }
            if (trim((string) ($participant['id'] ?? '')) === $psid) {
                $name = trim((string) ($participant['name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }

                return trim((string) ($participant['username'] ?? ''));
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  'messenger'|'instagram'  $channel
     * @param  array<int, string>  $businessIds
     * @return array<string, mixed>|null
     */
    private function normalizeHistoryMessage(
        string $pageId,
        string $psid,
        string $participantName,
        array $message,
        string $channel = 'messenger',
        array $businessIds = []
    ): ?array {
        $mid = trim((string) ($message['id'] ?? ''));
        if ($mid === '') {
            return null;
        }

        $fromId = trim((string) ($message['from']['id'] ?? ''));
        $skip = array_fill_keys(array_filter(array_map('strval', $businessIds ?: [$pageId])), true);
        // Missing `from` still belongs to this thread — treat as customer inbound.
        $isEcho = $fromId !== '' && isset($skip[$fromId]);
        $text = trim((string) ($message['message'] ?? ''));
        $createdTime = trim((string) ($message['created_time'] ?? ''));
        $attachments = $this->normalizeAttachments($message);
        $type = 'text';
        if ($attachments !== []) {
            $type = (string) ($attachments[0]['type'] ?? 'file');
        } elseif (! empty($message['sticker'])) {
            $type = 'image';
            $attachments[] = [
                'type' => 'image',
                'url' => (string) $message['sticker'],
            ];
        }

        // Shares / templates sometimes only expose a title-like payload.
        if ($text === '' && $attachments === [] && ! empty($message['shares'])) {
            $text = '[Shared content]';
        }

        if ($text === '' && $attachments === []) {
            return null;
        }

        return [
            'page_id' => $pageId,
            'psid' => $psid,
            'channel' => $channel === 'instagram' ? 'instagram' : 'messenger',
            'is_echo' => $isEcho,
            'source' => 'history_sync',
            'created_time' => $createdTime,
            'sender_profile' => [
                'name' => $isEcho ? '' : $participantName,
                'profile_pic' => '',
            ],
            'message' => [
                'mid' => $mid,
                'type' => $type,
                'text' => $text,
                'attachments' => $attachments,
                'reply_to_mid' => '',
                'is_echo' => $isEcho,
                'created_time' => $createdTime,
                'referral' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array{type:string,url:string}>
     */
    private function normalizeAttachments(array $message): array
    {
        $rows = $message['attachments']['data'] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mime = strtolower((string) ($row['mime_type'] ?? ''));
            $url = '';
            $type = 'file';

            if (! empty($row['image_data']['url'])) {
                $url = (string) $row['image_data']['url'];
                $type = 'image';
            } elseif (! empty($row['video_data']['url'])) {
                $url = (string) $row['video_data']['url'];
                $type = 'video';
            } elseif (! empty($row['file_url'])) {
                $url = (string) $row['file_url'];
                if (str_starts_with($mime, 'image/')) {
                    $type = 'image';
                } elseif (str_starts_with($mime, 'audio/')) {
                    $type = 'audio';
                } elseif (str_starts_with($mime, 'video/')) {
                    $type = 'video';
                }
            }

            if ($url === '') {
                continue;
            }

            $out[] = [
                'type' => $type,
                'url' => $url,
            ];
        }

        return $out;
    }
}
