<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Models\MessengerPageConnection;
use App\Services\Courier\CourierAccountService;
use App\Services\Messenger\MessengerPageOAuthService;
use Illuminate\Http\Request;

class MessengerAttachmentController extends Controller
{
    /**
     * Accept a binary media upload from a WordPress store and register it with Meta.
     *
     * This is the path used when the store is on HTTP/localhost (or any host Meta
     * cannot fetch). The returned Meta attachment_id is then used in /messenger/send.
     */
    public function upload(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $type = strtolower(trim((string) $request->input('type', 'file')));
        $allowedTypes = ['image', 'audio', 'video', 'file'];
        if (! in_array($type, $allowedTypes, true)) {
            $type = 'file';
        }

        if (! $request->hasFile('file')) {
            return $this->errorResponse('A media file is required.', 422);
        }

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return $this->errorResponse('The uploaded file is invalid.', 422);
        }

        // Mirror Meta's documented ceilings (8MB images, 25MB other).
        $maxBytes = $type === 'image' ? 8 * 1024 * 1024 : 25 * 1024 * 1024;
        if ((int) $file->getSize() <= 0) {
            return $this->errorResponse('The uploaded file is empty.', 422);
        }
        if ((int) $file->getSize() > $maxBytes) {
            return $this->errorResponse(
                $type === 'image'
                    ? 'Images must be 8MB or smaller.'
                    : 'Attachments must be 25MB or smaller.',
                422
            );
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

        // Prefer the MIME the store sent us. Laravel's getMimeType() sniffs file
        // content, which reports audio-only MP4/MOV voice notes as "video/mp4" and
        // causes Meta to reject them (#100). The store already resolved the real type.
        $clientMime = trim((string) $request->input('mime', ''));
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/quicktime', 'video/webm',
            'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac',
            'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-wav',
            'application/pdf',
        ];
        $mime = in_array($clientMime, $allowedMimes, true)
            ? $clientMime
            : (string) ($file->getMimeType() ?: '');

        // Defense in depth: even if the store omitted mime, never hand Meta a
        // video/* Content-Type for an attachment declared as audio (voice notes).
        if ($type === 'audio' && in_array($mime, ['video/mp4', 'video/quicktime', ''], true)) {
            $mime = 'audio/mp4';
        }

        $result = $oauth->uploadAttachment(
            $connection,
            $type,
            $file->getRealPath(),
            (string) $file->getClientOriginalName(),
            $mime
        );

        if (empty($result['ok'])) {
            return $this->errorResponse(
                (string) ($result['error'] ?? 'Failed to upload media to Facebook.'),
                (int) ($result['http_status'] ?? 422)
            );
        }

        return $this->successResponse([
            'attachment_id' => (string) ($result['attachment_id'] ?? ''),
            'type' => $type,
            'page_id' => $connection->page_id,
        ], 'Attachment uploaded.');
    }
}
