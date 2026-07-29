<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Services\Courier\CourierAccountService;
use App\Services\Messenger\MessengerPageConnectionResolver;
use App\Services\Messenger\MessengerPageOAuthService;
use Illuminate\Http\Request;

class MessengerSendController extends Controller
{
    public function send(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
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
        $templatePayload = $request->input('attachment.payload');

        if ($psid === '') {
            return $this->errorResponse('psid is required.', 422);
        }

        $hasTemplate = $attachmentType === 'template' && is_array($templatePayload);
        $hasMetaId = $attachmentType !== '' && $metaAttachmentId !== '' && ! $hasTemplate;
        $hasUrl = $attachmentType !== '' && $attachmentUrl !== '' && ! $hasTemplate;
        $hasAttachment = $hasMetaId || $hasUrl || $hasTemplate;

        if ($text === '' && ! $hasAttachment) {
            return $this->errorResponse('Message text or attachment is required.', 422);
        }

        if ($hasTemplate) {
            $templateType = strtolower(trim((string) ($templatePayload['template_type'] ?? '')));
            $elements = is_array($templatePayload['elements'] ?? null) ? $templatePayload['elements'] : [];
            if ($templateType !== 'generic' || $elements === []) {
                return $this->errorResponse('Generic template requires template_type=generic and elements.', 422);
            }
        } elseif ($hasAttachment) {
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

        $connection = $resolver->resolve($accessToken, $pageId);

        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $options = [];
        if ($tag !== '') {
            $options['tag'] = $tag;
        }

        if ($hasTemplate) {
            $options['attachment'] = [
                'type' => 'template',
                'payload' => $templatePayload,
            ];
        } elseif ($hasAttachment) {
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

        $quickReplies = $request->input('quick_replies', []);
        if (is_array($quickReplies) && $quickReplies !== []) {
            $options['quick_replies'] = $quickReplies;
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
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
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

        $connection = $resolver->resolve($accessToken, $pageId);

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

    /**
     * Unsend a page-sent message on Facebook (removes it for the customer too).
     */
    public function deleteMessage(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $mid = trim((string) $request->input('mid', $request->input('message_id', '')));

        if ($mid === '') {
            return $this->errorResponse('mid is required.', 422);
        }

        $connection = $resolver->resolve($accessToken, $pageId);

        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->deleteMessage($connection, $mid);

        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to delete Messenger message.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'mid' => $mid,
            'page_id' => $connection->page_id,
        ], 'Message deleted.');
    }

    /**
     * Public reply under a Facebook Page comment.
     */
    public function commentReply(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $commentId = trim((string) $request->input('comment_id', ''));
        $text = trim((string) $request->input('text', $request->input('message', '')));

        if ($commentId === '') {
            return $this->errorResponse('comment_id is required.', 422);
        }
        if ($text === '') {
            return $this->errorResponse('Reply text is required.', 422);
        }

        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->replyToComment($connection, $commentId, $text);
        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to reply to comment.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'id' => (string) ($result['id'] ?? ''),
            'page_id' => $connection->page_id,
            'comment_id' => $commentId,
        ], 'Comment reply sent.');
    }

    /**
     * Private Reply on a comment (opens/sends Messenger DM).
     */
    public function commentPrivateReply(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $commentId = trim((string) $request->input('comment_id', ''));
        $text = trim((string) $request->input('text', $request->input('message', '')));

        if ($commentId === '') {
            return $this->errorResponse('comment_id is required.', 422);
        }
        if ($text === '') {
            return $this->errorResponse('Private reply text is required.', 422);
        }

        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->privateReplyToComment($connection, $commentId, $text);
        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to send private reply.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'id' => (string) ($result['id'] ?? ''),
            'page_id' => $connection->page_id,
            'comment_id' => $commentId,
            'recipient_id' => (string) ($result['recipient_id'] ?? ''),
        ], 'Private reply sent.');
    }

    /**
     * Hide a Facebook Page comment (is_hidden=true).
     */
    public function commentHide(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $commentId = trim((string) $request->input('comment_id', ''));
        $hidden = filter_var($request->input('hidden', true), FILTER_VALIDATE_BOOLEAN);

        if ($commentId === '') {
            return $this->errorResponse('comment_id is required.', 422);
        }

        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->hideComment($connection, $commentId, $hidden);
        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to hide comment.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'page_id' => $connection->page_id,
            'comment_id' => $commentId,
            'hidden' => $hidden,
        ], $hidden ? 'Comment hidden on Facebook.' : 'Comment unhidden on Facebook.');
    }

    /**
     * Delete a Facebook Page comment (Graph DELETE).
     */
    public function commentDelete(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $commentId = trim((string) $request->input('comment_id', ''));

        if ($commentId === '') {
            return $this->errorResponse('comment_id is required.', 422);
        }

        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->deleteComment($connection, $commentId);
        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to delete comment.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'page_id' => $connection->page_id,
            'comment_id' => $commentId,
            'deleted' => true,
        ], 'Comment deleted on Facebook.');
    }

    /**
     * Lookup commenter name/id for a Page comment.
     */
    public function commentMeta(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $commentId = trim((string) $request->input('comment_id', ''));

        if ($commentId === '') {
            return $this->errorResponse('comment_id is required.', 422);
        }

        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->fetchCommentMeta($connection, $commentId);
        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to load comment.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'page_id' => $connection->page_id,
            'comment_id' => $commentId,
            'from_id' => (string) ($result['from_id'] ?? ''),
            'from_name' => (string) ($result['from_name'] ?? ''),
            'message' => (string) ($result['message'] ?? ''),
        ], 'Comment loaded.');
    }

    public function commentPostMeta(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $postId = trim((string) $request->input('post_id', ''));

        if ($postId === '') {
            return $this->errorResponse('post_id is required.', 422);
        }

        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('No connected Facebook Page found for this license.', 404);
        }

        $result = $oauth->fetchPostMeta($connection, $postId);
        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to load post.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'page_id' => $connection->page_id,
            'post_id' => (string) ($result['post_id'] ?? $postId),
            'message' => (string) ($result['message'] ?? ''),
            'story' => (string) ($result['story'] ?? ''),
            'permalink' => (string) ($result['permalink'] ?? ''),
            'picture_url' => (string) ($result['picture_url'] ?? ''),
            'created_time' => (string) ($result['created_time'] ?? ''),
        ], 'Post loaded.');
    }
}
