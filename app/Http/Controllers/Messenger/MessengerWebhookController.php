<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
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

        if (($payload['object'] ?? '') !== 'page') {
            return response()->json([
                'status' => 'success',
                'message' => 'Ignored non-page webhook.',
            ]);
        }

        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
        $events = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $pageId = (string) ($entry['id'] ?? '');
            // Primary inbox events + standby (handover protocol) can both carry reactions.
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

                    $normalized = $this->normalizeMessagingEvent($pageId, $item);
                    if ($normalized !== null) {
                        $events[] = $normalized;
                    }
                }
            }
        }

        if ($events === []) {
            return response()->json([
                'status' => 'success',
                'message' => 'No actionable Messenger events.',
            ]);
        }

        // Group by page_id for forwarding.
        $byPage = [];
        foreach ($events as $event) {
            $byPage[$event['page_id']][] = $event;
        }

        $dispatched = 0;
        foreach ($byPage as $pageId => $pageEvents) {
            $connections = MessengerPageConnection::query()
                ->connected()
                ->where('page_id', $pageId)
                ->orderByDesc('id')
                ->get();

            if ($connections->isEmpty()) {
                Log::info('Messenger webhook: no connected page', ['page_id' => $pageId]);
                continue;
            }

            // ACK Meta quickly: enrich + WP forward run after the response (or via queue workers).
            foreach ($connections as $connection) {
                ProcessMessengerInboundForward::dispatchAfterResponse(
                    (int) $connection->id,
                    $pageEvents
                );
                $dispatched++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received successfully.',
            'queued_forwards' => $dispatched,
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
     * @return array<string, mixed>|null
     */
    private function normalizeMessagingEvent(string $pageId, array $item): ?array
    {
        $senderId = (string) ($item['sender']['id'] ?? '');
        $recipientId = (string) ($item['recipient']['id'] ?? '');
        $message = is_array($item['message'] ?? null) ? $item['message'] : null;

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
                    'page_id' => $pageId,
                    'sender_id' => $senderId,
                    'reaction' => $reaction,
                ]);

                return null;
            }

            Log::info('Messenger webhook: reaction event', [
                'page_id' => $pageId !== '' ? $pageId : $recipientId,
                'psid' => $senderId === $pageId ? $recipientId : $senderId,
                'mid' => $targetMid,
                'action' => (string) ($reaction['action'] ?? 'react'),
                'reaction' => (string) ($reaction['reaction'] ?? 'other'),
                'emoji' => (string) ($reaction['emoji'] ?? ''),
            ]);

            return [
                'page_id' => $pageId !== '' ? $pageId : $recipientId,
                'psid' => $senderId === $pageId ? $recipientId : $senderId,
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
        }

        // Page-sent echoes: sender is the Page, recipient is the customer PSID.
        // We forward these so WordPress can replace temporary local media URLs
        // with Facebook CDN URLs (same display path as inbound customer media).
        $isEcho = is_array($message) && ! empty($message['is_echo']);
        if ($isEcho) {
            if ($recipientId === '' || $pageId === '') {
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

            return [
                'page_id' => $pageId,
                'psid' => $recipientId,
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
        }

        // Echo / page-as-sender without is_echo payload — ignore.
        if ($senderId === '' || $senderId === $pageId) {
            return null;
        }

        if ($message === null) {
            // Still store postbacks as text for inbox visibility.
            if (isset($item['postback']['payload'])) {
                $payload = (string) $item['postback']['payload'];
                $title = trim((string) ($item['postback']['title'] ?? ''));
                if ($payload === 'WEL_ORDER_CONFIRM') {
                    $payload = 'কনফার্ম';
                } elseif ($payload === 'WEL_ORDER_EDIT') {
                    $payload = 'ঠিক নেই';
                } elseif ($title !== '') {
                    $payload = $title;
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

        // Normalize order-confirm quick-reply payloads so WordPress confirm parser stays stable.
        $qrPayload = trim((string) ($message['quick_reply']['payload'] ?? ''));
        if ($qrPayload === 'WEL_ORDER_CONFIRM') {
            $text = 'কনফার্ম';
            $type = 'quick_reply';
        } elseif ($qrPayload === 'WEL_ORDER_EDIT') {
            $text = 'ঠিক নেই';
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

        return [
            'page_id' => $pageId !== '' ? $pageId : $recipientId,
            'psid' => $senderId,
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
    }
}
