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
     * Linked Instagram professional account for a Facebook Page (if any).
     *
     * Prefer Page access token; fall back to user token via /me/accounts when the
     * Page token lacks pages_read_engagement / Instagram scopes.
     *
     * @return array{id:string,username:string}
     */
    public function fetchPageInstagramAccount(
        string $pageId,
        string $pageAccessToken,
        string $userAccessToken = ''
    ): array {
        $fallback = ['id' => '', 'username' => ''];
        $pageId = trim($pageId);
        $pageAccessToken = trim($pageAccessToken);
        $userAccessToken = trim($userAccessToken);
        if ($pageId === '') {
            return $fallback;
        }

        if ($pageAccessToken !== '') {
            try {
                $response = Http::timeout(20)->get(
                    'https://graph.facebook.com/' . $this->graphVersion() . '/' . $pageId,
                    [
                        'fields' => 'instagram_business_account{id,username}',
                        'access_token' => $pageAccessToken,
                    ]
                );

                if ($response->successful()) {
                    $ig = $response->json('instagram_business_account');
                    if (is_array($ig)) {
                        $id = trim((string) ($ig['id'] ?? ''));
                        $username = trim((string) ($ig['username'] ?? ''));
                        if ($id !== '' || $username !== '') {
                            return ['id' => $id, 'username' => $username];
                        }
                    }
                } else {
                    Log::info('Messenger IG account lookup via page token failed', [
                        'page_id' => $pageId,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::warning('Messenger IG account lookup exception', [
                    'page_id' => $pageId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($userAccessToken === '') {
            return $fallback;
        }

        try {
            $response = Http::timeout(25)->get(
                'https://graph.facebook.com/' . $this->graphVersion() . '/me/accounts',
                [
                    'fields' => 'id,instagram_business_account{id,username}',
                    'access_token' => $userAccessToken,
                    'limit' => 100,
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Messenger IG account /me/accounts exception', [
                'page_id' => $pageId,
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        if (! $response->successful()) {
            Log::info('Messenger IG account /me/accounts failed', [
                'page_id' => $pageId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $fallback;
        }

        $rows = $response->json('data');
        if (! is_array($rows)) {
            return $fallback;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['id'] ?? '')) !== $pageId) {
                continue;
            }
            $ig = is_array($row['instagram_business_account'] ?? null)
                ? $row['instagram_business_account']
                : [];

            return [
                'id' => trim((string) ($ig['id'] ?? '')),
                'username' => trim((string) ($ig['username'] ?? '')),
            ];
        }

        return $fallback;
    }

    /**
     * Persist / refresh Instagram linkage on an existing Page connection.
     *
     * @return array{id:string,username:string,changed:bool}
     */
    public function syncInstagramLinkage(MessengerPageConnection $connection, bool $force = false): array
    {
        $existingId = trim((string) ($connection->instagram_business_account_id ?? ''));
        $existingUsername = trim((string) ($connection->instagram_username ?? ''));

        if (! $force && $existingId !== '') {
            return [
                'id' => $existingId,
                'username' => $existingUsername,
                'changed' => false,
            ];
        }

        $ig = $this->fetchPageInstagramAccount(
            (string) $connection->page_id,
            (string) $connection->page_access_token,
            (string) ($connection->user_access_token ?? '')
        );

        $changed = ($ig['id'] !== $existingId) || ($ig['username'] !== $existingUsername);
        if ($changed) {
            $connection->instagram_business_account_id = $ig['id'] !== '' ? $ig['id'] : null;
            $connection->instagram_username = $ig['username'] !== '' ? $ig['username'] : null;
            $connection->save();
        }

        return [
            'id' => $ig['id'],
            'username' => $ig['username'],
            'changed' => $changed,
        ];
    }

    /**
     * @param  'messenger'|'instagram'  $channel
     * @return array{name:string,profile_pic:string,username:string,gender:string}
     */
    public function fetchSenderProfile(
        string $psid,
        string $pageAccessToken,
        string $channel = 'messenger'
    ): array {
        $fallback = ['name' => '', 'profile_pic' => '', 'username' => '', 'gender' => ''];
        $psid = trim($psid);
        $channel = $channel === 'instagram' ? 'instagram' : 'messenger';
        if ($psid === '' || $pageAccessToken === '') {
            return $fallback;
        }

        $cacheKey = 'messenger.psid.' . $channel . '.' . $psid;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $name = (string) ($cached['name'] ?? '');
            $profilePic = (string) ($cached['profile_pic'] ?? '');
            $username = (string) ($cached['username'] ?? '');
            $gender = $this->normalizeSenderGender($cached['gender'] ?? '');

            // If we previously cached an empty avatar for Messenger, retry once
            // without the gender field (gender permission/feature missing can
            // make the whole request fail).
            if ($channel === 'messenger' && $profilePic === '') {
                $noGenderProfilePicFields = 'name,profile_pic';
                try {
                    $retry = Http::timeout(15)->get(
                        'https://graph.facebook.com/' . $this->graphVersion() . '/' . $psid,
                        [
                            'fields' => $noGenderProfilePicFields,
                            'access_token' => $pageAccessToken,
                        ]
                    );

                    if ($retry->successful()) {
                        $name = trim((string) ($retry->json('name') ?? $name));
                        $profilePic = trim((string) ($retry->json('profile_pic') ?? ''));
                    }
                } catch (\Throwable $exception) {
                    // Best-effort only; fall back to cached empty avatar.
                }
            }

            return [
                'name' => $name,
                'profile_pic' => $profilePic,
                'username' => $username,
                'gender' => $gender,
            ];
        }

        // Messenger User Profile supports gender with pages_user_gender.
        // Instagram User Profile typically has no gender field.
        $fields = $channel === 'instagram'
            ? 'name,username,profile_pic'
            : 'name,profile_pic,gender';
        $noGenderFields = $channel === 'messenger'
            ? 'name,profile_pic'
            : '';

        try {
            $response = Http::timeout(15)->get(
                'https://graph.facebook.com/' . $this->graphVersion() . '/' . $psid,
                [
                    'fields' => $fields,
                    'access_token' => $pageAccessToken,
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Messenger sender profile exception', [
                'psid' => $psid,
                'channel' => $channel,
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        if (! $response->successful()) {
            if ($channel === 'messenger' && $noGenderFields !== '') {
                // Retry without gender to avoid avatar failing due to missing
                // pages_user_gender feature/permission.
                try {
                    $retry = Http::timeout(15)->get(
                        'https://graph.facebook.com/' . $this->graphVersion() . '/' . $psid,
                        [
                            'fields' => $noGenderFields,
                            'access_token' => $pageAccessToken,
                        ]
                    );

                    if ($retry->successful()) {
                        $username = trim((string) ($response->json('username') ?? ''));
                        $name = trim((string) ($retry->json('name') ?? ''));
                        $profilePic = trim((string) ($retry->json('profile_pic') ?? ''));

                        if ($profilePic === '') {
                            $profilePic = $this->fetchSenderProfilePictureUrl($psid, $pageAccessToken);
                        }

                        $profile = [
                            'name' => $name,
                            'profile_pic' => $profilePic,
                            'username' => $username,
                            'gender' => '',
                        ];

                        Cache::put($cacheKey, $profile, now()->addHours(12));
                        return $profile;
                    }
                } catch (\Throwable $exception) {
                    // Continue to failure logging below.
                }
            }

            Log::info('Messenger sender profile failed', [
                'psid' => $psid,
                'channel' => $channel,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $fallback;
        }

        $username = trim((string) ($response->json('username') ?? ''));
        $name = trim((string) ($response->json('name') ?? ''));
        if ($name === '' && $username !== '') {
            $name = $username;
        }
        $profilePic = trim((string) ($response->json('profile_pic') ?? ''));
        if ($profilePic === '') {
            $profilePic = $this->fetchSenderProfilePictureUrl($psid, $pageAccessToken);
        }
        if ($profilePic === '' && $channel === 'instagram' && $username !== '') {
            $profilePic = $this->instagramPublicAvatarUrl($username);
        }
        $gender = $channel === 'messenger'
            ? $this->normalizeSenderGender($response->json('gender') ?? '')
            : '';

        $profile = [
            'name' => $name,
            'profile_pic' => $profilePic,
            'username' => $username,
            'gender' => $gender,
        ];

        Cache::put($cacheKey, $profile, now()->addHours(12));

        return $profile;
    }

    /**
     * Normalize Meta User Profile gender to male|female|''.
     */
    public function normalizeSenderGender(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['male', 'm', 'man', 'boy'], true)) {
            return 'male';
        }
        if (in_array($value, ['female', 'f', 'woman', 'girl'], true)) {
            return 'female';
        }

        return '';
    }

    /**
     * Best-effort public Instagram avatar when Graph User Profile is unavailable
     * (common before Advanced Access approval).
     */
    public function instagramPublicAvatarUrl(string $username): string
    {
        $username = ltrim(trim($username), '@');
        if ($username === '' || ! preg_match('/^[A-Za-z0-9._]{1,30}$/', $username)) {
            return '';
        }

        return 'https://unavatar.io/instagram/' . rawurlencode($username);
    }

    /**
     * Fallback profile picture endpoint when profile_pic is missing
     * from Graph User Profile payload.
     */
    private function fetchSenderProfilePictureUrl(string $psid, string $pageAccessToken): string
    {
        try {
            $response = Http::timeout(15)->get(
                'https://graph.facebook.com/' . $this->graphVersion() . '/' . $psid . '/picture',
                [
                    'redirect' => 'false',
                    'width' => 96,
                    'height' => 96,
                    'access_token' => $pageAccessToken,
                ]
            );
        } catch (\Throwable $exception) {
            Log::info('Messenger sender picture fallback exception', [
                'psid' => $psid,
                'message' => $exception->getMessage(),
            ]);
            return '';
        }

        if (! $response->successful()) {
            return '';
        }

        return trim((string) ($response->json('data.url') ?? ''));
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

        // Meta's ingest for video/large media routinely exceeds the default 30s
        // max_execution_time. The HTTP client already waits up to 90s; lift the
        // PHP ceiling so the worker is not fatally killed mid-upload (which the
        // store then surfaces as a generic "Could not send message").
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

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
     * Public reply under a Page comment: POST /{comment-id}/comments
     *
     * @return array{ok:bool, id?:string, error?:string, http_status?:int}
     */
    public function replyToComment(MessengerPageConnection $connection, string $commentId, string $message): array
    {
        $commentId = trim($commentId);
        $message = trim($message);
        $pageToken = (string) $connection->page_access_token;

        if ($commentId === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing comment id or page token.'];
        }
        if ($message === '') {
            return ['ok' => false, 'error' => 'Reply message is empty.'];
        }

        $url = 'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($commentId) . '/comments';
        $response = Http::asForm()->timeout(30)->post($url, [
            'message' => $message,
            'access_token' => $pageToken,
        ]);

        if (! $response->successful()) {
            $error = (string) (
                $response->json('error.message')
                ?: $response->json('error.error_user_msg')
                ?: ('Facebook comment reply failed (HTTP ' . $response->status() . ').')
            );

            return [
                'ok' => false,
                'error' => $error,
                'http_status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'id' => (string) ($response->json('id') ?? ''),
            'http_status' => $response->status(),
        ];
    }

    /**
     * Private Reply on a comment → Messenger DM: POST /{comment-id}/private_replies
     *
     * @return array{ok:bool, id?:string, recipient_id?:string, error?:string, http_status?:int}
     */
    public function privateReplyToComment(MessengerPageConnection $connection, string $commentId, string $message): array
    {
        $commentId = trim($commentId);
        $message = trim($message);
        $pageToken = (string) $connection->page_access_token;

        if ($commentId === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing comment id or page token.'];
        }
        if ($message === '') {
            return ['ok' => false, 'error' => 'Private reply message is empty.'];
        }

        // Resolve commenter Page-scoped id before send (needed for WP Messenger seed).
        $recipientId = '';
        $commentUrl = 'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($commentId);
        $commentRes = Http::timeout(20)->get($commentUrl, [
            'fields' => 'from{id,name}',
            'access_token' => $pageToken,
        ]);
        if ($commentRes->successful()) {
            $recipientId = trim((string) ($commentRes->json('from.id') ?? ''));
        }

        $url = 'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($commentId) . '/private_replies';
        $response = Http::asForm()->timeout(30)->post($url, [
            'message' => $message,
            'access_token' => $pageToken,
        ]);

        if (! $response->successful()) {
            $error = (string) (
                $response->json('error.message')
                ?: $response->json('error.error_user_msg')
                ?: ('Facebook private reply failed (HTTP ' . $response->status() . ').')
            );

            return [
                'ok' => false,
                'error' => $error,
                'http_status' => $response->status(),
                'recipient_id' => $recipientId,
            ];
        }

        $messageId = (string) ($response->json('id') ?? '');

        // Best-effort: confirm recipient from the outbound message "to" field.
        if ($messageId !== '') {
            $msgRes = Http::timeout(20)->get(
                'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($messageId),
                [
                    'fields' => 'to{id},from{id}',
                    'access_token' => $pageToken,
                ]
            );
            if ($msgRes->successful()) {
                $toId = trim((string) ($msgRes->json('to.data.0.id') ?? $msgRes->json('to.id') ?? ''));
                if ($toId !== '') {
                    $recipientId = $toId;
                }
            }
        }

        return [
            'ok' => true,
            'id' => $messageId,
            'recipient_id' => $recipientId,
            'http_status' => $response->status(),
        ];
    }

    /**
     * Hide (or unhide) a Page comment: POST /{comment-id}?is_hidden=…
     *
     * Meta documents query-string is_hidden; multipart form is a fallback.
     * Preflight can_hide / is_hidden avoids opaque "An unknown error occurred".
     *
     * @return array{ok:bool, error?:string, http_status?:int, can_hide?:bool, already?:bool}
     */
    public function hideComment(MessengerPageConnection $connection, string $commentId, bool $hidden = true): array
    {
        $commentId = trim($commentId);
        $pageToken = (string) $connection->page_access_token;

        if ($commentId === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing comment id or page token.'];
        }

        $baseUrl = 'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($commentId);

        $meta = Http::timeout(20)->get($baseUrl, [
            'fields' => 'can_hide,is_hidden,can_remove',
            'access_token' => $pageToken,
        ]);

        if ($meta->successful()) {
            $alreadyHidden = filter_var($meta->json('is_hidden'), FILTER_VALIDATE_BOOLEAN);
            if ($alreadyHidden === $hidden) {
                return [
                    'ok' => true,
                    'http_status' => $meta->status(),
                    'already' => true,
                    'can_hide' => filter_var($meta->json('can_hide'), FILTER_VALIDATE_BOOLEAN),
                ];
            }

            $canHide = $meta->json('can_hide');
            if ($hidden && $canHide === false) {
                return [
                    'ok' => false,
                    'error' => 'Facebook will not allow hiding this comment (can_hide=false). '
                        . 'Use Delete, or reconnect the Page with pages_manage_engagement / MODERATE.',
                    'http_status' => 422,
                    'can_hide' => false,
                ];
            }
        }

        // Official format: POST /{comment-id}?is_hidden=true (token in body too).
        $response = Http::asForm()->timeout(30)->post(
            $baseUrl . '?' . http_build_query([
                'is_hidden' => $hidden ? 'true' : 'false',
            ]),
            [
                'is_hidden' => $hidden ? 'true' : 'false',
                'access_token' => $pageToken,
            ]
        );

        // Fallback: multipart (curl -F) when Graph returns opaque code 1.
        if (! $response->successful()) {
            $code = (int) ($response->json('error.code') ?? 0);
            $msg = strtolower((string) ($response->json('error.message') ?? ''));
            $retryable = $code === 1 || str_contains($msg, 'unknown error');
            if ($retryable) {
                $response = Http::asMultipart()->timeout(30)->post($baseUrl, [
                    'is_hidden' => $hidden ? 'true' : 'false',
                    'access_token' => $pageToken,
                ]);
            }
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => $this->formatCommentHideError($response, $hidden),
                'http_status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'http_status' => $response->status(),
        ];
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     */
    protected function formatCommentHideError($response, bool $hidden): string
    {
        $message = (string) (
            $response->json('error.message')
            ?: $response->json('error.error_user_msg')
            ?: ('Facebook ' . ($hidden ? 'hide' : 'unhide') . ' comment failed (HTTP ' . $response->status() . ').')
        );
        $code = (int) ($response->json('error.code') ?? 0);
        $type = trim((string) ($response->json('error.type') ?? ''));

        $hint = '';
        if ($code === 1 || stripos($message, 'unknown error') !== false) {
            $hint = ' Reconnect the Page with pages_manage_engagement, confirm the comment still exists, then retry — or use Delete.';
        } elseif ($code === 200 || $code === 10 || $code === 210) {
            $hint = ' Page token needs MODERATE / pages_manage_engagement — use Reconnect.';
        } elseif ($code === 100) {
            $hint = ' Comment id may be invalid or already removed.';
        }

        $prefix = $code > 0 ? "(#{$code}) " : '';
        if ($type !== '' && stripos($message, $type) === false) {
            $prefix = "{$prefix}[{$type}] ";
        }

        return trim($prefix . $message . $hint);
    }

    /**
     * Delete a Page comment: DELETE /{comment-id}.
     *
     * @return array{ok:bool, error?:string, http_status?:int}
     */
    public function deleteComment(MessengerPageConnection $connection, string $commentId): array
    {
        $commentId = trim($commentId);
        $pageToken = (string) $connection->page_access_token;

        if ($commentId === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing comment id or page token.'];
        }

        $url = 'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($commentId);
        $response = Http::timeout(30)->delete($url, [
            'access_token' => $pageToken,
        ]);

        if (! $response->successful()) {
            $error = (string) (
                $response->json('error.message')
                ?: $response->json('error.error_user_msg')
                ?: ('Facebook delete comment failed (HTTP ' . $response->status() . ').')
            );

            return [
                'ok' => false,
                'error' => $error,
                'http_status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'http_status' => $response->status(),
        ];
    }

    /**
     * Fetch commenter identity for a stored comment id.
     *
     * @return array{ok:bool, from_id?:string, from_name?:string, message?:string, error?:string, http_status?:int}
     */
    public function fetchCommentMeta(MessengerPageConnection $connection, string $commentId): array
    {
        $commentId = trim($commentId);
        $pageToken = (string) $connection->page_access_token;

        if ($commentId === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing comment id or page token.'];
        }

        $url = 'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($commentId);
        $response = Http::timeout(20)->get($url, [
            'fields' => 'from{id,name},message',
            'access_token' => $pageToken,
        ]);

        if (! $response->successful()) {
            $error = (string) (
                $response->json('error.message')
                ?: $response->json('error.error_user_msg')
                ?: ('Facebook comment lookup failed (HTTP ' . $response->status() . ').')
            );

            return [
                'ok' => false,
                'error' => $error,
                'http_status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'from_id' => trim((string) ($response->json('from.id') ?? '')),
            'from_name' => trim((string) ($response->json('from.name') ?? '')),
            'message' => trim((string) ($response->json('message') ?? '')),
            'http_status' => $response->status(),
        ];
    }

    /**
     * Fetch Page post preview fields for Comments inbox context.
     *
     * @return array{ok:bool, post_id?:string, message?:string, story?:string, permalink?:string, picture_url?:string, created_time?:string, error?:string, http_status?:int}
     */
    public function fetchPostMeta(MessengerPageConnection $connection, string $postId): array
    {
        $postId = trim($postId);
        $pageToken = (string) $connection->page_access_token;

        if ($postId === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing post id or page token.'];
        }

        $url = 'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($postId);
        $response = Http::timeout(20)->get($url, [
            'fields' => 'id,message,story,permalink_url,full_picture,picture,created_time,attachments{media,title,description,type,url}',
            'access_token' => $pageToken,
        ]);

        if (! $response->successful()) {
            $error = (string) (
                $response->json('error.message')
                ?: $response->json('error.error_user_msg')
                ?: ('Facebook post lookup failed (HTTP ' . $response->status() . ').')
            );

            return [
                'ok' => false,
                'error' => $error,
                'http_status' => $response->status(),
            ];
        }

        $picture = trim((string) ($response->json('full_picture') ?? ''));
        if ($picture === '') {
            $picture = trim((string) ($response->json('picture') ?? ''));
        }
        if ($picture === '') {
            $attachments = $response->json('attachments.data');
            if (is_array($attachments) && isset($attachments[0]) && is_array($attachments[0])) {
                $media = $attachments[0]['media']['image']['src']
                    ?? $attachments[0]['media']['source']
                    ?? null;
                if (is_string($media) && trim($media) !== '') {
                    $picture = trim($media);
                }
            }
        }

        return [
            'ok' => true,
            'post_id' => trim((string) ($response->json('id') ?? $postId)),
            'message' => trim((string) ($response->json('message') ?? '')),
            'story' => trim((string) ($response->json('story') ?? '')),
            'permalink' => trim((string) ($response->json('permalink_url') ?? '')),
            'picture_url' => $picture,
            'created_time' => trim((string) ($response->json('created_time') ?? '')),
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
     * Send a sender_action to a recipient.
     *
     * Supported: mark_seen, typing_on, typing_off, react, unreact.
     * Meta requires these on their own request (no message body).
     * For react/unreact, pass options.message_id (+ options.reaction emoji for react).
     *
     * @param  array{message_id?:string, reaction?:string}  $options
     * @return array{ok:bool, error?:string, http_status?:int}
     */
    public function sendSenderAction(
        MessengerPageConnection $connection,
        string $psid,
        string $action,
        array $options = []
    ): array {
        $psid = trim($psid);
        $pageToken = (string) $connection->page_access_token;
        $action = strtolower(trim($action));

        $allowed = ['mark_seen', 'typing_on', 'typing_off', 'react', 'unreact'];
        if (! in_array($action, $allowed, true)) {
            $action = 'typing_on';
        }

        if ($psid === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing recipient or page token.'];
        }

        $payload = [
            'recipient' => ['id' => $psid],
            'sender_action' => $action,
        ];

        if (in_array($action, ['react', 'unreact'], true)) {
            $messageId = trim((string) ($options['message_id'] ?? ''));
            if ($messageId === '') {
                return ['ok' => false, 'error' => 'message_id is required for react/unreact.'];
            }

            $reactionPayload = ['message_id' => $messageId];
            if ($action === 'react') {
                $reaction = trim((string) ($options['reaction'] ?? ''));
                if ($reaction === '') {
                    return ['ok' => false, 'error' => 'reaction emoji is required for react.'];
                }
                $reactionPayload['reaction'] = $reaction;
            }
            $payload['payload'] = $reactionPayload;
        }

        try {
            $response = Http::timeout(15)
                ->withToken($pageToken)
                ->post(
                    'https://graph.facebook.com/' . $this->graphVersion() . '/me/messages',
                    $payload
                );
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => (string) ($response->json('error.message') ?? 'Failed to send sender action.'),
                'http_status' => $response->status(),
            ];
        }

        return ['ok' => true, 'http_status' => $response->status()];
    }

    /**
     * Delete (unsend) a page-sent Messenger message for everyone.
     * Meta Graph: DELETE /{message-id}
     *
     * @return array{ok:bool, error?:string, http_status?:int}
     */
    public function deleteMessage(
        MessengerPageConnection $connection,
        string $mid
    ): array {
        $mid = trim($mid);
        $pageToken = (string) $connection->page_access_token;

        if ($mid === '' || $pageToken === '') {
            return ['ok' => false, 'error' => 'Missing message id or page token.', 'http_status' => 422];
        }

        // Local synthetic ids are never Graph-deletable.
        if (str_starts_with($mid, 'out_')
            || str_starts_with($mid, 'evt_')
            || str_starts_with($mid, 'postback_')
            || str_starts_with($mid, 'echo_')
        ) {
            return ['ok' => false, 'error' => 'This message cannot be deleted on Facebook.', 'http_status' => 422];
        }

        try {
            $response = Http::timeout(25)
                ->withToken($pageToken)
                ->delete(
                    'https://graph.facebook.com/' . $this->graphVersion() . '/' . rawurlencode($mid)
                );
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            $graphError = (string) ($response->json('error.message') ?? '');
            $code = (int) ($response->json('error.code') ?? 0);
            // Already gone on Facebook — treat as success so local inbox can catch up.
            if ($response->status() === 404
                || $code === 100
                || stripos($graphError, 'does not exist') !== false
                || stripos($graphError, 'unsupported delete') !== false
            ) {
                // code 100 can also mean permission; only soft-succeed on clear missing-object wording.
                if ($response->status() === 404 || stripos($graphError, 'does not exist') !== false) {
                    return ['ok' => true, 'http_status' => 200];
                }
            }

            return [
                'ok' => false,
                'error' => $graphError !== '' ? $graphError : 'Failed to delete Messenger message.',
                'http_status' => $response->status(),
            ];
        }

        return ['ok' => true, 'http_status' => $response->status()];
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

        // Messenger quick replies (tap chips under the bubble).
        if (! empty($options['quick_replies']) && is_array($options['quick_replies']) && $text !== '') {
            $quickReplies = [];
            foreach ($options['quick_replies'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $payload = trim((string) ($row['payload'] ?? $title));
                $contentType = strtolower(trim((string) ($row['content_type'] ?? 'text')));
                if ($title === '' || $contentType !== 'text') {
                    continue;
                }
                // Meta: title max 20 characters.
                if (mb_strlen($title) > 20) {
                    $title = mb_substr($title, 0, 20);
                }
                if (strlen($payload) > 1000) {
                    $payload = substr($payload, 0, 1000);
                }
                $quickReplies[] = [
                    'content_type' => 'text',
                    'title' => $title,
                    'payload' => $payload !== '' ? $payload : $title,
                ];
                if (count($quickReplies) >= 13) {
                    break;
                }
            }
            if ($quickReplies !== []) {
                $message['quick_replies'] = $quickReplies;
            }
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

        // Force Meta to re-prompt when adding new scopes (e.g. comments engagement).
        $authType = trim((string) ($context['auth_type'] ?? ''));
        if ($authType !== '') {
            $params['auth_type'] = $authType;
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
                'fields' => 'id,name,access_token,picture{url},instagram_business_account{id,username}',
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

            $ig = is_array($row['instagram_business_account'] ?? null)
                ? $row['instagram_business_account']
                : [];

            $pages[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? 'Facebook Page'),
                'access_token' => $token,
                'picture' => $picture,
                'instagram_business_account_id' => trim((string) ($ig['id'] ?? '')),
                'instagram_username' => trim((string) ($ig['username'] ?? '')),
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

        $ig = $this->fetchPageInstagramAccount(
            (string) $page['id'],
            (string) $page['access_token'],
            $userAccessToken
        );
        if ($ig['id'] === '' && ! empty($page['instagram_business_account_id'])) {
            $ig = [
                'id' => trim((string) $page['instagram_business_account_id']),
                'username' => trim((string) ($page['instagram_username'] ?? '')),
            ];
        }

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
                'instagram_business_account_id' => $ig['id'] !== '' ? $ig['id'] : null,
                'instagram_username' => $ig['username'] !== '' ? $ig['username'] : null,
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
                        'subscribed_fields' => 'messages,message_echoes,message_reactions,messaging_postbacks,messaging_optins,message_deliveries,message_reads,messaging_referrals,feed',
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
