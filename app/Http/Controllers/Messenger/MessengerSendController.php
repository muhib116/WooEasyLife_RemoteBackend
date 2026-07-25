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

        if ($psid === '') {
            return $this->errorResponse('psid is required.', 422);
        }

        if ($text === '') {
            return $this->errorResponse('Message text is required.', 422);
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
}
