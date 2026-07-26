<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Models\MessengerPageConnection;
use App\Services\Courier\CourierAccountService;
use App\Services\Messenger\MessengerPageOAuthService;
use Illuminate\Http\Request;

class MessengerSendController extends Controller
{
    public function send(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $psid = trim((string) $request->input('psid', ''));
        $text = trim((string) $request->input('text', $request->input('body', '')));
        $tag = trim((string) $request->input('tag', ''));

        $attachmentType = strtolower(trim((string) $request->input('attachment.type', '')));
        $attachmentUrl = trim((string) $request->input('attachment.url', ''));
        // Meta attachment_id from /messenger/upload-attachment (preferred for local/HTTP sites).
        $metaAttachmentId = trim((string) $request->input('attachment.attachment_id', ''));

        if ($psid === '') {
            return $this->errorResponse('psid is required.', 422);
        }

        $hasMetaId = $attachmentType !== '' && $metaAttachmentId !== '';
        $hasUrl = $attachmentType !== '' && $attachmentUrl !== '';
        $hasAttachment = $hasMetaId || $hasUrl;

        if ($text === '' && ! $hasAttachment) {
            return $this->errorResponse('Message text or attachment is required.', 422);
        }

        if ($hasAttachment) {
            $allowedTypes = ['image', 'audio', 'video', 'file'];
            if (! in_array($attachmentType, $allowedTypes, true)) {
                $attachmentType = 'file';
            }

            // Prefer Meta attachment_id (works without a public store URL).
            if (! $hasMetaId && $hasUrl) {
                if (! filter_var($attachmentUrl, FILTER_VALIDATE_URL) || ! str_starts_with($attachmentUrl, 'https://')) {
                    return $this->errorResponse('Attachment URL must be a public HTTPS link.', 422);
                }

                // Block obvious private / local hosts Meta cannot fetch.
                $host = strtolower((string) (parse_url($attachmentUrl, PHP_URL_HOST) ?: ''));
                if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
                    return $this->errorResponse('Attachment URL host is not publicly reachable by Facebook.', 422);
                }
            }
        }

        $query = MessengerPageConnection::query()
            ->connected()
            ->where('access_token_id', $accessToken->id);

        if ($pageId !== '') {
            $query->where('page_id', $pageId);
        }

        /** @var MessengerPageConnection|null $connection */
        $connection = $query->orderByDesc('id')->first();

        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $options = [];
        if ($tag !== '') {
            $options['tag'] = $tag;
        }

        if ($hasAttachment) {
            if ($hasMetaId) {
                $options['attachment'] = [
                    'type' => $attachmentType,
                    'payload' => [
                        'attachment_id' => $metaAttachmentId,
                    ],
                ];
            } else {
                $options['attachment'] = [
                    'type' => $attachmentType,
                    'payload' => [
                        'url' => $attachmentUrl,
                        'is_reusable' => true,
                    ],
                ];
            }
        }

        $replyToMid = trim((string) $request->input('reply_to_mid', ''));
        if ($replyToMid !== '') {
            $options['reply_to_mid'] = $replyToMid;
        }

        $result = $oauth->sendMessage($connection, $psid, $text, $options);

        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to send Messenger message.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'mid' => (string) ($result['mid'] ?? ''),
            'page_id' => $connection->page_id,
            'psid' => $psid,
        ], 'Message sent.');
    }

    /**
     * Forward a sender_action (typing_on/typing_off/mark_seen) to Meta so the
     * customer sees the "typing…" indicator while an operator composes a reply.
     */
    public function senderAction(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $psid = trim((string) $request->input('psid', ''));
        $action = strtolower(trim((string) $request->input('action', 'typing_on')));

        if ($psid === '') {
            return $this->errorResponse('psid is required.', 422);
        }

        if (! in_array($action, ['mark_seen', 'typing_on', 'typing_off'], true)) {
            return $this->errorResponse('Invalid sender action.', 422);
        }

        $query = MessengerPageConnection::query()
            ->connected()
            ->where('access_token_id', $accessToken->id);

        if ($pageId !== '') {
            $query->where('page_id', $pageId);
        }

        /** @var MessengerPageConnection|null $connection */
        $connection = $query->orderByDesc('id')->first();

        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->sendSenderAction($connection, $psid, $action);

        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to send sender action.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse(['action' => $action], 'ok');
    }
}
