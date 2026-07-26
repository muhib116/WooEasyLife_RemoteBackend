<?php

namespace App\Services\Messenger;

use App\Models\AccessToken;
use App\Models\MessengerPageConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessengerPageOAuthService
{
    public const CACHE_PREFIX = 'messenger.oauth.';

    public const PICKER_PREFIX = 'messenger.picker.';

    public function appId(): string
    {
        return trim((string) (
            config('services.messenger.app_id')
            ?: config('services.facebook.client_id')
            ?: ''
        ));
    }

    public function appSecret(): string
    {
        return trim((string) (
            config('services.messenger.app_secret')
            ?: config('services.facebook.client_secret')
            ?: ''
        ));
    }

    public function redirectUri(): string
    {
        $configured = trim((string) config('services.messenger.redirect'));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/') . '/api/messenger/oauth/callback';
    }

    public function graphVersion(): string
    {
        return trim((string) (
            config('services.messenger.graph_version')
            ?: config('services.facebook.graph_version')
            ?: 'v21.0'
        ));
    }

    public function scopes(): string
    {
        return trim((string) config(
            'services.messenger.scopes',
            'pages_show_list,pages_messaging,pages_manage_metadata'
        ));
    }

    /**
     * Facebook Login for Business configuration id (optional).
     * When present, OAuth must use config_id instead of raw scope= parameters.
     */
    public function loginConfigId(): string
    {
        return trim((string) config('services.messenger.login_config_id', ''));
    }

    public function isConfigured(): bool
    {
        return $this->appId() !== '' && $this->appSecret() !== '';
    }

    public function directPageId(): string
    {
        return trim((string) config('services.messenger.page_id'));
    }

    public function directPageAccessToken(): string
    {
        return trim((string) config('services.messenger.page_access_token'));
    }

    /**
     * Single-page mode: a Page token is already configured, so no login dialog is needed.
     */
    public function directConnectEnabled(): bool
    {
        return (bool) config('services.messenger.direct_connect')
            && $this->directPageId() !== ''
            && $this->directPageAccessToken() !== '';
    }

    /**
     * Attach the configured Page to a license without the OAuth round trip.
     *
     * @param  array{access_token_id:int,user_id?:int,website_id?:int|null,site_url:string,return_url:string}  $context
     */
    public function connectDirectPage(array $context): MessengerPageConnection
    {
        if (! $this->directConnectEnabled()) {
            throw new \RuntimeException('Direct Page connect is not configured.');
        }

        $pageId = $this->directPageId();
        $token = $this->directPageAccessToken();
        $profile = $this->fetchPageProfile($pageId, $token);

        return $this->persistConnection($context, [
            'id' => $pageId,
            'name' => $profile['name'] !== '' ? $profile['name'] : 'Facebook Page',
            'access_token' => $token,
            'picture' => $profile['picture'],
        ], '');
    }

    /**
     * @return array{name:string,picture:string}
     */
    public function fetchPageProfile(string $pageId, string $pageAccessToken): array
    {
        $fallback = ['name' => '', 'picture' => ''];

        try {
            $response = Http::timeout(20)->get(
                'https://graph.facebook.com/' . $this->graphVersion() . '/' . $pageId,
                [
                    'fields' => 'id,name,picture{url}',
                    'access_token' => $pageAccessToken,
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Messenger page profile exception', [
                'page_id' => $pageId,
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        if (! $response->successful()) {
            Log::warning('Messenger page profile failed', [
                'page_id' => $pageId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $fallback;
        }

        return [
            'name' => trim((string) ($response->json('name') ?? '')),
            'picture' => (string) ($response->json('picture.data.url') ?? ''),
        ];
    }

    /**
     * @return array{name:string,profile_pic:string}
     */
    public function fetchSenderProfile(string $psid, string $pageAccessToken): array
    {
        $fallback = ['name' => '', 'profile_pic' => ''];
        $psid = trim($psid);
        if ($psid === '' || $pageAccessToken === '') {
            return $fallback;
        }

        $cacheKey = 'messenger.psid.' . $psid;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return [
                'name' => (string) ($cached['name'] ?? ''),
                'profile_pic' => (string) ($cached['profile_pic'] ?? ''),
            ];
        }

        try {
            $response = Http::timeout(15)->get(
                'https://graph.facebook.com/' . $this->graphVersion() . '/' . $psid,
                [
                    'fields' => 'name,profile_pic',
                    'access_token' => $pageAccessToken,
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Messenger sender profile exception', [
                'psid' => $psid,
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        if (! $response->successful()) {
            return $fallback;
        }

        $profile = [
            'name' => trim((string) ($response->json('name') ?? '')),
            'profile_pic' => (string) ($response->json('profile_pic') ?? ''),
        ];

        Cache::put($cacheKey, $profile, now()->addHours(12));

        return $profile;
    }

    /**
     * Upload a local media file to Meta's Attachment Upload API.
     *
     * Returns a reusable Meta attachment_id that can be sent without Meta
     * needing to fetch the store's WordPress URL (works for local HTTP too).
     *
     * @return array{ok:bool, attachment_id?:string, error?:string, http_status?:int}
     */
    public function uploadAttachment(
        MessengerPageConnection $connection,
        string $type,
        string $absolutePath,
        string $filename,
        string $mime = ''
    ): array {
        $pageToken = (string) $connection->page_access_token;
        $type = strtolower(trim($type));
        $allowed = ['image', 'audio', 'video', 'file'];
        if (! in_array($type, $allowed, true)) {
            $type = 'file';
        }

        if ($pageToken === '' || $absolutePath === '' || ! is_readable($absolutePath)) {
            return ['ok' => false, 'error' => 'Missing page token or unreadable media file.'];
        }

        $filename = $filename !== '' ? $filename : basename($absolutePath);
        $contents = @file_get_contents($absolutePath);
        if ($contents === false || $contents === '') {
            return ['ok' => false, 'error' => 'Could not read media file.'];
        }

        $message = json_encode([
            'attachment' => [
                'type' => $type,
                'payload' => ['is_reusable' => true],
            ],
        ], JSON_UNESCAPED_SLASHES);

        try {
            $request = Http::timeout(90)
                ->withToken($pageToken)
                ->attach(
                    'filedata',
                    $contents,
                    $filename,
                    $mime !== '' ? ['Content-Type' => $mime] : []
                );

            $response = $request->post(
                'https://graph.facebook.com/' . $this->graphVersion() . '/me/message_attachments',
                ['message' => $message]
            );
        } catch (\Throwable $exception) {
            Log::error('Messenger attachment upload exception', [
                'page_id' => $connection->page_id,
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            $fbMessage = (string) (
                $response->json('error.message')
                ?? $response->body()
            );

            Log::warning('Messenger attachment upload failed', [
                'page_id' => $connection->page_id,
                'type' => $type,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'error' => $fbMessage !== '' ? $fbMessage : 'Facebook rejected the media upload.',
                'http_status' => $response->status(),
            ];
        }

        $attachmentId = (string) ($response->json('attachment_id') ?? '');
        if ($attachmentId === '') {
            return ['ok' => false, 'error' => 'Facebook did not return an attachment_id.'];
        }

        return [
            'ok' => true,
            'attachment_id' => $attachmentId,
            'http_status' => $response->status(),
        ];
    }

    /**
     * Send a text (or attachment) reply via the Page's messaging API.
     *
     * Meta rejects payloads that include both text and attachment. When both are
     * provided we send the attachment first, then the text as a follow-up.
     *
     * @param  array<string, mixed>  $options
     * @return array{ok:bool, mid?:string, secondary_mid?:string, error?:string, http_status?:int}
     */
    public function sendMessage(
        MessengerPageConnection $connection,
        string $psid,
        string $text,
        array $options = []
    ): array {
        $psid = trim($psid);
        $text = trim($text);
        $pageToken = (string) $connection->page_access_token;

        if ($psid === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing recipient or page token.'];
        }

        $hasAttachment = ! empty($options['attachment']) && is_array($options['attachment']);

        if ($text === '' && ! $hasAttachment) {
            return ['ok' => false, 'error' => 'Message body is empty.'];
        }

        // Split caption + media into two Graph calls.
        if ($text !== '' && $hasAttachment) {
            $attachmentOptions = $options;
            unset($attachmentOptions['attachment']); // re-set below
            $attachmentOptions['attachment'] = $options['attachment'];

            $first = $this->sendMessageOnce($connection, $psid, '', $attachmentOptions);
            if (empty($first['ok'])) {
                return $first;
            }

            $textOptions = $options;
            unset($textOptions['attachment'], $textOptions['reply_to_mid']);

            $second = $this->sendMessageOnce($connection, $psid, $text, $textOptions);
            if (empty($second['ok'])) {
                return [
                    'ok' => true,
                    'mid' => (string) ($first['mid'] ?? ''),
                    'secondary_mid' => '',
                    'warning' => (string) ($second['error'] ?? 'Caption failed after media was sent.'),
                    'http_status' => (int) ($first['http_status'] ?? 200),
                ];
            }

            return [
                'ok' => true,
                'mid' => (string) ($first['mid'] ?? ''),
                'secondary_mid' => (string) ($second['mid'] ?? ''),
                'http_status' => (int) ($second['http_status'] ?? 200),
            ];
        }

        return $this->sendMessageOnce($connection, $psid, $text, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{ok:bool, mid?:string, error?:string, http_status?:int}
     */
    private function sendMessageOnce(
        MessengerPageConnection $connection,
        string $psid,
        string $text,
        array $options = []
    ): array {
        $pageToken = (string) $connection->page_access_token;
        $text = trim($text);

        $message = [];
        if ($text !== '') {
            $message['text'] = $text;
        }
        if (! empty($options['attachment']) && is_array($options['attachment'])) {
            $message['attachment'] = $options['attachment'];
        }

        if ($message === []) {
            return ['ok' => false, 'error' => 'Message body is empty.'];
        }

        $payload = [
            'recipient' => ['id' => $psid],
            'messaging_type' => (string) ($options['messaging_type'] ?? 'RESPONSE'),
            'message' => $message,
        ];

        // Meta's Send API expects reply_to as a sibling of `message`, not nested inside it.
        $replyToMid = trim((string) ($options['reply_to_mid'] ?? ''));
        if ($replyToMid !== ''
            && ! str_starts_with($replyToMid, 'out_')
            && ! str_starts_with($replyToMid, 'evt_')
            && ! str_starts_with($replyToMid, 'postback_')
        ) {
            $payload['reply_to'] = ['mid' => $replyToMid];
        }

        if (! empty($options['tag'])) {
            $payload['tag'] = (string) $options['tag'];
            $payload['messaging_type'] = 'MESSAGE_TAG';
        }

        try {
            $response = Http::timeout(25)
                ->withToken($pageToken)
                ->post(
                    'https://graph.facebook.com/' . $this->graphVersion() . '/me/messages',
                    $payload
                );
        } catch (\Throwable $exception) {
            Log::error('Messenger send exception', [
                'page_id' => $connection->page_id,
                'psid' => $psid,
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            $fbMessage = (string) (
                $response->json('error.message')
                ?? $response->body()
            );

            Log::warning('Messenger send failed', [
                'page_id' => $connection->page_id,
                'psid' => $psid,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'error' => $fbMessage !== '' ? $fbMessage : 'Facebook rejected the message.',
                'http_status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'mid' => (string) ($response->json('message_id') ?? ''),
            'http_status' => $response->status(),
        ];
    }

    /**
     * @param  array{access_token_id:int,user_id?:int,website_id?:int|null,site_url:string,return_url:string}  $context
     */
    public function buildConnectUrl(array $context): string
    {
        $state = Str::random(40);
        Cache::put(self::CACHE_PREFIX . $state, $context, now()->addMinutes(20));

        $params = [
            'client_id' => $this->appId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
        ];

        $configId = $this->loginConfigId();
        if ($configId !== '') {
            // Login for Business: permissions come from the dashboard configuration.
            $params['config_id'] = $configId;
            $params['override_default_response_type'] = 'true';
        } else {
            $params['scope'] = $this->scopes();
        }

        return 'https://www.facebook.com/' . $this->graphVersion() . '/dialog/oauth?' . http_build_query($params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pullState(string $state): ?array
    {
        if ($state === '') {
            return null;
        }

        $pending = Cache::pull(self::CACHE_PREFIX . $state);

        return is_array($pending) ? $pending : null;
    }

    /**
     * Exchange code for a user access token.
     */
    public function exchangeCode(string $code): string
    {
        $response = Http::asForm()
            ->timeout(25)
            ->get('https://graph.facebook.com/' . $this->graphVersion() . '/oauth/access_token', [
                'client_id' => $this->appId(),
                'client_secret' => $this->appSecret(),
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
            ]);

        if (! $response->successful()) {
            Log::warning('Messenger OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Facebook login failed. Please try Connect again.');
        }

        $token = trim((string) ($response->json('access_token') ?? ''));
        if ($token === '') {
            throw new \RuntimeException('Facebook did not return an access token.');
        }

        return $token;
    }

    /**
     * @return array<int, array{id:string,name:string,access_token:string,picture?:string}>
     */
    public function listPages(string $userAccessToken): array
    {
        $response = Http::timeout(25)->get(
            'https://graph.facebook.com/' . $this->graphVersion() . '/me/accounts',
            [
                'fields' => 'id,name,access_token,picture{url}',
                'access_token' => $userAccessToken,
                'limit' => 100,
            ]
        );

        if (! $response->successful()) {
            Log::warning('Messenger /me/accounts failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Could not load your Facebook Pages. Please try again.');
        }

        $data = $response->json('data');
        if (! is_array($data) || $data === []) {
            return [];
        }

        $pages = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            $token = trim((string) ($row['access_token'] ?? ''));
            if ($id === '' || $token === '') {
                continue;
            }

            $picture = '';
            if (isset($row['picture']['data']['url'])) {
                $picture = (string) $row['picture']['data']['url'];
            } elseif (isset($row['picture']['url'])) {
                $picture = (string) $row['picture']['url'];
            }

            $pages[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? 'Facebook Page'),
                'access_token' => $token,
                'picture' => $picture,
            ];
        }

        return $pages;
    }

    /**
     * @param  array{access_token_id:int,user_id?:int,website_id?:int|null,site_url:string,return_url:string}  $context
     * @param  array{id:string,name:string,access_token:string,picture?:string}  $page
     */
    public function persistConnection(array $context, array $page, string $userAccessToken): MessengerPageConnection
    {
        $accessTokenId = (int) ($context['access_token_id'] ?? 0);
        if ($accessTokenId <= 0) {
            throw new \RuntimeException('Invalid connect session.');
        }

        /** @var AccessToken|null $accessToken */
        $accessToken = AccessToken::query()->find($accessTokenId);
        if (! $accessToken) {
            throw new \RuntimeException('License token not found.');
        }

        $now = now();

        // One connected page per license for v1.
        MessengerPageConnection::query()
            ->where('access_token_id', $accessTokenId)
            ->where('status', 'connected')
            ->where('page_id', '!=', $page['id'])
            ->update([
                'status' => 'disconnected',
                'disconnected_at' => $now,
            ]);

        $connection = MessengerPageConnection::query()->updateOrCreate(
            [
                'access_token_id' => $accessTokenId,
                'page_id' => $page['id'],
            ],
            [
                'user_id' => (int) ($context['user_id'] ?? $accessToken->tokenable_id ?? 0) ?: null,
                'website_id' => $context['website_id'] ?? $accessToken->website_id,
                'page_name' => $page['name'],
                'page_picture' => $page['picture'] ?? null,
                'page_access_token' => $page['access_token'],
                'user_access_token' => $userAccessToken,
                'scopes' => array_values(array_filter(array_map('trim', explode(',', $this->scopes())))),
                'status' => 'connected',
                'site_url' => rtrim((string) ($context['site_url'] ?? ''), '/'),
                'return_url' => (string) ($context['return_url'] ?? ''),
                'connected_at' => $now,
                'disconnected_at' => null,
            ]
        );

        $this->subscribePageToWebhook($connection);

        return $connection->fresh();
    }

    public function subscribePageToWebhook(MessengerPageConnection $connection): void
    {
        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post(
                    'https://graph.facebook.com/' . $this->graphVersion() . '/' . $connection->page_id . '/subscribed_apps',
                    [
                        'subscribed_fields' => 'messages,messaging_postbacks,messaging_optins,message_deliveries,message_reads,messaging_referrals',
                        'access_token' => $connection->page_access_token,
                    ]
                );

            if ($response->successful()) {
                $connection->webhook_subscribed_at = now();
                $connection->save();
            } else {
                Log::warning('Messenger subscribed_apps failed', [
                    'page_id' => $connection->page_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Messenger subscribed_apps exception', [
                'page_id' => $connection->page_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Store pages temporarily for the HTML picker when the user has multiple Pages.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array{id:string,name:string,access_token:string,picture?:string}>  $pages
     */
    public function storePickerSession(array $context, array $pages, string $userAccessToken): string
    {
        $token = Str::random(40);
        Cache::put(self::PICKER_PREFIX . $token, [
            'context' => $context,
            'pages' => $pages,
            'user_access_token' => $userAccessToken,
        ], now()->addMinutes(15));

        return $token;
    }

    /**
     * @return array{context:array,pages:array,user_access_token:string}|null
     */
    public function pullPickerSession(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $data = Cache::get(self::PICKER_PREFIX . $token);

        return is_array($data) ? $data : null;
    }

    public function forgetPickerSession(string $token): void
    {
        Cache::forget(self::PICKER_PREFIX . $token);
    }

    public function disconnect(AccessToken $accessToken, ?string $pageId = null): void
    {
        $query = MessengerPageConnection::query()
            ->where('access_token_id', $accessToken->id)
            ->where('status', 'connected');

        if ($pageId) {
            $query->where('page_id', $pageId);
        }

        $connections = $query->get();
        foreach ($connections as $connection) {
            $connection->status = 'disconnected';
            $connection->disconnected_at = now();
            $connection->save();
        }
    }
}
