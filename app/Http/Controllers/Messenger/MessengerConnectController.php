<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Jobs\SyncMessengerConversationHistory;
use App\Models\Website;
use App\Services\Courier\CourierAccountService;
use App\Services\Messenger\MessengerCommentsHistorySync;
use App\Services\Messenger\MessengerConversationHistorySync;
use App\Services\Messenger\MessengerPageConnectionResolver;
use App\Services\Messenger\MessengerPageOAuthService;
use App\Services\Messenger\WordPressMessengerForwarder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessengerConnectController extends Controller
{
    public function connectUrl(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth,
        WordPressMessengerForwarder $forwarder
    ) {
        if (! $oauth->directConnectEnabled() && ! $oauth->isConfigured()) {
            return $this->errorResponse(
                'Facebook connection is not configured yet. Please try again shortly.',
                503
            );
        }

        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $siteUrl = rtrim((string) ($request->input('site_url') ?: $accounts->resolveSiteUrl($request)), '/');
        if ($siteUrl === '' && $accessToken->website_id) {
            $website = Website::query()->find($accessToken->website_id);
            $siteUrl = rtrim((string) ($website?->base_url ?? ''), '/');
        }
        if ($siteUrl === '' && $accessToken->domain) {
            $domain = trim((string) $accessToken->domain);
            $siteUrl = str_starts_with($domain, 'http') ? rtrim($domain, '/') : 'https://' . rtrim($domain, '/');
        }

        $returnUrl = trim((string) $request->input('return_url', ''));
        if ($returnUrl === '' && $siteUrl !== '') {
            $returnUrl = $siteUrl . '/wp-admin/admin.php?page=woo-easy-life#/messenger?connected=1';
        }

        $authType = trim((string) $request->input('auth_type', ''));
        if ($authType === '' && $request->boolean('force_rerequest')) {
            $authType = 'rerequest';
        }

        $context = [
            'access_token_id' => (int) $accessToken->id,
            'user_id' => (int) ($accessToken->tokenable_id ?? 0),
            'website_id' => $accessToken->website_id,
            'site_url' => $siteUrl,
            'return_url' => $returnUrl,
            'auth_type' => $authType,
        ];

        if ($oauth->directConnectEnabled()) {
            try {
                $connection = $oauth->connectDirectPage($context);
            } catch (\Throwable $exception) {
                return $this->errorResponse($exception->getMessage(), 422);
            }

            $forwarder->notifyPageConnected($connection);
            $this->queueHistorySync($connection);

            return $this->successResponse([
                'connected' => true,
                'hub_ready' => true,
                'history_sync_queued' => true,
                'page' => [
                    'page_id' => $connection->page_id,
                    'page_name' => $connection->page_name,
                    'page_picture' => $connection->page_picture,
                ],
            ], 'Facebook Page connected.');
        }

        return $this->successResponse([
            'connect_url' => $oauth->buildConnectUrl($context),
            'hub_ready' => true,
        ], 'Open Facebook to choose your Page.');
    }

    public function oauthCallback(
        Request $request,
        MessengerPageOAuthService $oauth,
        WordPressMessengerForwarder $forwarder
    ): RedirectResponse|View {
        if ($request->filled('error')) {
            return $this->redirectHomeWithError(
                'Facebook denied access: ' . $request->string('error')
            );
        }

        $state = trim((string) $request->query('state', ''));
        $context = $oauth->pullState($state);
        if (! is_array($context)) {
            return $this->redirectHomeWithError('Facebook connect expired. Please click Connect again.');
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return $this->redirectWithReturn($context, false, 'Missing Facebook authorization code.');
        }

        try {
            $userToken = $oauth->exchangeCode($code);
            $pages = $oauth->listPages($userToken);
        } catch (\Throwable $exception) {
            return $this->redirectWithReturn($context, false, $exception->getMessage());
        }

        if ($pages === []) {
            return $this->redirectWithReturn(
                $context,
                false,
                'No Facebook Pages were found for this account. Create a Page, then try again.'
            );
        }

        if (count($pages) === 1) {
            return $this->finishConnection($oauth, $forwarder, $context, $pages[0], $userToken);
        }

        $pickerToken = $oauth->storePickerSession($context, $pages, $userToken);

        return view('messenger.page-picker', [
            'pages' => $pages,
            'picker_token' => $pickerToken,
            'return_url' => $context['return_url'] ?? '',
        ]);
    }

    public function selectPage(
        Request $request,
        MessengerPageOAuthService $oauth,
        WordPressMessengerForwarder $forwarder
    ): RedirectResponse {
        $pickerToken = trim((string) $request->input('picker_token', ''));
        $pageId = trim((string) $request->input('page_id', ''));
        $session = $oauth->pullPickerSession($pickerToken);

        if (! is_array($session)) {
            return $this->redirectHomeWithError('Page selection expired. Please click Connect again.');
        }

        $context = is_array($session['context'] ?? null) ? $session['context'] : [];
        $pages = is_array($session['pages'] ?? null) ? $session['pages'] : [];
        $userToken = (string) ($session['user_access_token'] ?? '');

        $selected = null;
        foreach ($pages as $page) {
            if (is_array($page) && (string) ($page['id'] ?? '') === $pageId) {
                $selected = $page;
                break;
            }
        }

        if (! $selected) {
            return $this->redirectWithReturn($context, false, 'Please choose a Facebook Page.');
        }

        $oauth->forgetPickerSession($pickerToken);

        return $this->finishConnection($oauth, $forwarder, $context, $selected, $userToken);
    }

    public function disconnect(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageOAuthService $oauth
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $oauth->disconnect($accessToken, $pageId !== '' ? $pageId : null);

        return $this->successResponse([
            'disconnected' => true,
        ], 'Facebook Page disconnected.');
    }

    /**
     * Lightweight hub check so WordPress can stop showing "Connected" when the
     * local pages table is ahead of messenger_page_connections for this license.
     */
    public function status(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageConnectionResolver $resolver
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $payload = $resolver->statusPayload($accessToken, $pageId);

        return $this->successResponse($payload, $payload['connected'] ? 'Connected.' : 'Not connected.');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{id:string,name:string,access_token:string,picture?:string}  $page
     */
    private function finishConnection(
        MessengerPageOAuthService $oauth,
        WordPressMessengerForwarder $forwarder,
        array $context,
        array $page,
        string $userToken
    ): RedirectResponse {
        try {
            $connection = $oauth->persistConnection($context, $page, $userToken);
            $forwarder->notifyPageConnected($connection);
            $this->queueHistorySync($connection);
        } catch (\Throwable $exception) {
            return $this->redirectWithReturn($context, false, $exception->getMessage());
        }

        return $this->redirectWithReturn($context, true);
    }

    /**
     * Import recent Messenger threads from Graph into the connected store.
     * Runs synchronously so local installs without a queue worker still get history.
     */
    public function syncHistory(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageConnectionResolver $resolver,
        MessengerConversationHistorySync $sync
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('Connect a Facebook Page first.', 409);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $result = $sync->sync($connection, [
            'max_conversations' => (int) $request->input('max_conversations', 25),
            'max_messages_per_conversation' => (int) $request->input('max_messages', 40),
            'channel' => (string) $request->input('channel', 'messenger'),
        ]);

        $ok = ! empty($result['ok']);

        return $this->successResponse($result, (string) ($result['message'] ?? ($ok ? 'Synced.' : 'Sync failed.')), $ok ? 200 : 422);
    }

    /**
     * Import recent Page post comments from Graph into the connected store.
     */
    public function syncCommentsHistory(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageConnectionResolver $resolver,
        MessengerCommentsHistorySync $sync
    ) {
        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('Connect a Facebook Page first.', 409);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $result = $sync->sync($connection, [
            'max_posts' => (int) $request->input('max_posts', 20),
            'max_comments_per_post' => (int) $request->input('max_comments_per_post', 40),
        ]);

        $ok = ! empty($result['ok']);

        return $this->successResponse(
            $result,
            (string) ($result['message'] ?? ($ok ? 'Comments synced.' : 'Comments sync failed.')),
            $ok ? 200 : 422
        );
    }

    private function queueHistorySync($connection): void
    {
        try {
            SyncMessengerConversationHistory::dispatch((int) $connection->id);
        } catch (\Throwable $exception) {
            // Non-fatal: WP can still call /messenger/sync-history after connect.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function redirectWithReturn(array $context, bool $ok, string $error = ''): RedirectResponse
    {
        $returnUrl = trim((string) ($context['return_url'] ?? ''));
        if ($returnUrl === '') {
            return $ok
                ? redirect('/')
                : $this->redirectHomeWithError($error !== '' ? $error : 'Facebook connect failed.');
        }

        if (! $ok && $error !== '') {
            $parts = explode('#', $returnUrl, 2);
            $base = $parts[0];
            $hash = $parts[1] ?? 'messenger';
            // Put the error inside the Vue hash query so the SPA can read it.
            $hash .= (str_contains($hash, '?') ? '&' : '?') . 'messenger_error=' . rawurlencode($error);
            $returnUrl = $base . '#' . $hash;
        }

        return redirect()->away($returnUrl);
    }

    /**
     * Batch-refresh sender profiles for a list of PSIDs.
     * Returns profile_pic (and gender) so the WP plugin can backfill contacts
     * whose avatar was not populated before the /{psid}/picture fallback was added.
     */
    public function refreshProfiles(
        Request $request,
        CourierAccountService $accounts,
        MessengerPageConnectionResolver $resolver,
        MessengerPageOAuthService $oauth
    ) {
        $request->validate([
            'page_id' => 'required|string',
            'psids'   => 'required|array|max:50',
            'psids.*' => 'string|max:64',
            'channel' => 'nullable|string|in:messenger,instagram',
        ]);

        $accessToken = $accounts->resolveAccessToken($request);
        if (! $accessToken) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $pageId = trim((string) $request->input('page_id', ''));
        $connection = $resolver->resolve($accessToken, $pageId);
        if (! $connection) {
            return $this->errorResponse('Page not connected.', 404);
        }

        $channel = $request->input('channel', 'messenger');
        $profiles = [];

        foreach ($request->input('psids', []) as $psid) {
            $psid = trim((string) $psid);
            if ($psid === '') {
                continue;
            }
            $profile = $oauth->fetchSenderProfile($psid, $connection->page_access_token, $channel);
            $profiles[$psid] = [
                'profile_pic' => $profile['profile_pic'] ?? '',
                'gender'      => $profile['gender'] ?? '',
                'name'        => $profile['name'] ?? '',
            ];
        }

        return response()->json([
            'ok'       => true,
            'profiles' => $profiles,
        ]);
    }

    private function redirectHomeWithError(string $message): RedirectResponse
    {
        return redirect('/')->with('error', $message);
    }
}
