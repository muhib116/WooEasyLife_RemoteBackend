<?php

namespace App\Services\Meta;

use App\Models\AccessToken;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\MetaPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class MetaConnectionService
{
    /**
     * Validate a System User token against Graph, upsert connection + ad accounts + pages.
     * Ads and Pages are independent: store whichever the token can access.
     *
     * @return array{
     *     connection: MetaConnection,
     *     accounts: list<array{account_id:string,account_name:?string,currency:?string,is_tracked:bool}>,
     *     pages: list<array{page_id:string,page_name:?string,is_tracked:bool,webhook_subscribed:bool}>,
     *     granted_scopes: list<string>
     * }
     */
    public function connect(string $licenseKey, string $accessToken): array
    {
        $licenseKey = trim($licenseKey);
        $accessToken = trim($accessToken);

        if ($licenseKey === '' || $accessToken === '') {
            throw new InvalidArgumentException('license_key and access_token are required.');
        }

        $this->resolveLicense($licenseKey);

        $adResult = $this->fetchAdAccounts($accessToken);
        $pageResult = $this->fetchPages($accessToken);

        if (! $adResult['ok'] && ! $pageResult['ok']) {
            throw new RuntimeException(
                'Meta token is invalid or has no Ads/Page access. Check System User roles and scopes.'
            );
        }

        $grantedScopes = $this->inferGrantedScopes($adResult['ok'], $pageResult['ok']);

        return DB::transaction(function () use ($licenseKey, $accessToken, $adResult, $pageResult, $grantedScopes) {
            $connection = MetaConnection::query()->updateOrCreate(
                ['license_key' => $licenseKey],
                [
                    'system_user_token' => $accessToken,
                    'granted_scopes' => $grantedScopes,
                    'status' => 'active',
                    'source' => MetaConnection::SOURCE_SYSTEM_USER,
                    'last_verified_at' => now(),
                ]
            );

            if ($adResult['ok']) {
                $this->upsertAdAccounts($connection, $adResult['accounts']);
            }

            if ($pageResult['ok']) {
                $this->upsertPages($connection, $pageResult['pages']);
            }

            $connection = $connection->fresh(['adAccounts', 'pages']);

            return [
                'connection' => $connection,
                'accounts' => $this->serializeAccounts($connection->adAccounts),
                'pages' => $this->serializePages($connection->pages),
                'granted_scopes' => $grantedScopes,
            ];
        });
    }

    /**
     * @return list<array{account_id:string,account_name:?string,currency:?string,is_tracked:bool}>
     */
    public function getAccounts(string $licenseKey): array
    {
        $connection = $this->requireActiveConnection($licenseKey);

        return $this->serializeAccounts(
            $connection->adAccounts()->orderBy('account_name')->get()
        );
    }

    /**
     * @return list<array{page_id:string,page_name:?string,is_tracked:bool,webhook_subscribed:bool}>
     */
    public function getPages(string $licenseKey): array
    {
        $connection = $this->requireActiveConnection($licenseKey);

        return $this->serializePages(
            $connection->pages()->orderBy('page_name')->get()
        );
    }

    public function resolveLicense(string $licenseKey): AccessToken
    {
        $token = AccessToken::findToken($licenseKey);

        if (! $token) {
            throw new InvalidArgumentException('Invalid license_key.');
        }

        if ($token->status === false) {
            throw new InvalidArgumentException('License is inactive.');
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            throw new InvalidArgumentException('License has expired.');
        }

        return $token;
    }

    public function findActiveConnection(string $licenseKey): ?MetaConnection
    {
        return MetaConnection::query()
            ->active()
            ->where('license_key', $licenseKey)
            ->first();
    }

    protected function requireActiveConnection(string $licenseKey): MetaConnection
    {
        $licenseKey = trim($licenseKey);
        $this->resolveLicense($licenseKey);

        $connection = $this->findActiveConnection($licenseKey);
        if (! $connection) {
            throw new RuntimeException('No active Meta connection for this license. Connect first.');
        }

        return $connection;
    }

    /**
     * @return array{ok:bool,accounts:list<array{account_id:string,account_name:?string,currency:?string}>}
     */
    protected function fetchAdAccounts(string $accessToken): array
    {
        try {
            $response = Http::timeout(25)->get(
                $this->graphBase() . '/me/adaccounts',
                [
                    'fields' => 'id,account_id,name,currency',
                    'limit' => 100,
                    'access_token' => $accessToken,
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Meta /me/adaccounts request failed', [
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'accounts' => []];
        }

        if (! $response->successful()) {
            Log::info('Meta /me/adaccounts rejected', [
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'accounts' => []];
        }

        $rows = $response->json('data');
        if (! is_array($rows)) {
            return ['ok' => false, 'accounts' => []];
        }

        $accounts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $accountId = $this->normalizeAdAccountId(
                (string) ($row['account_id'] ?? $row['id'] ?? '')
            );
            if ($accountId === '') {
                continue;
            }

            $accounts[] = [
                'account_id' => $accountId,
                'account_name' => isset($row['name']) ? (string) $row['name'] : null,
                'currency' => isset($row['currency']) ? (string) $row['currency'] : null,
            ];
        }

        // Empty list still counts as OK — token has ads scope but no accounts yet.
        return ['ok' => true, 'accounts' => $accounts];
    }

    /**
     * @return array{ok:bool,pages:list<array{page_id:string,page_name:?string,page_access_token:?string}>}
     */
    protected function fetchPages(string $accessToken): array
    {
        try {
            $response = Http::timeout(25)->get(
                $this->graphBase() . '/me/accounts',
                [
                    // Same fields Messenger OAuth uses when listing pages (incl. page token).
                    'fields' => 'id,name,access_token',
                    'limit' => 100,
                    'access_token' => $accessToken,
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Meta /me/accounts request failed', [
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'pages' => []];
        }

        if (! $response->successful()) {
            Log::info('Meta /me/accounts rejected', [
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'pages' => []];
        }

        $rows = $response->json('data');
        if (! is_array($rows)) {
            return ['ok' => false, 'pages' => []];
        }

        $pages = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pageId = trim((string) ($row['id'] ?? ''));
            if ($pageId === '') {
                continue;
            }

            $pageToken = isset($row['access_token']) ? trim((string) $row['access_token']) : '';

            $pages[] = [
                'page_id' => $pageId,
                'page_name' => isset($row['name']) ? (string) $row['name'] : null,
                'page_access_token' => $pageToken !== '' ? $pageToken : null,
            ];
        }

        return ['ok' => true, 'pages' => $pages];
    }

    /**
     * @param  list<array{account_id:string,account_name:?string,currency:?string}>  $accounts
     */
    protected function upsertAdAccounts(MetaConnection $connection, array $accounts): void
    {
        $seen = [];

        foreach ($accounts as $account) {
            $seen[] = $account['account_id'];

            MetaAdAccount::query()->updateOrCreate(
                [
                    'meta_connection_id' => $connection->id,
                    'account_id' => $account['account_id'],
                ],
                [
                    'account_name' => $account['account_name'],
                    'currency' => $account['currency'],
                    // Preserve existing is_tracked on reconnect.
                ]
            );
        }

        $query = MetaAdAccount::query()->where('meta_connection_id', $connection->id);
        if ($seen !== []) {
            $query->whereNotIn('account_id', $seen);
        }
        $query->delete();
    }

    /**
     * @param  list<array{page_id:string,page_name:?string,page_access_token:?string}>  $pages
     */
    protected function upsertPages(MetaConnection $connection, array $pages): void
    {
        $seen = [];

        foreach ($pages as $page) {
            $seen[] = $page['page_id'];

            $existing = MetaPage::query()
                ->where('meta_connection_id', $connection->id)
                ->where('page_id', $page['page_id'])
                ->first();

            $attrs = [
                'page_name' => $page['page_name'],
            ];

            // Only overwrite page token when Graph returned one (same pattern as Messenger upsert).
            if (! empty($page['page_access_token'])) {
                $attrs['page_access_token'] = $page['page_access_token'];
            }

            if ($existing) {
                $existing->fill($attrs)->save();
            } else {
                MetaPage::query()->create(array_merge(
                    [
                        'meta_connection_id' => $connection->id,
                        'page_id' => $page['page_id'],
                        'is_tracked' => false,
                        'webhook_subscribed' => false,
                    ],
                    $attrs
                ));
            }
        }

        $query = MetaPage::query()->where('meta_connection_id', $connection->id);
        if ($seen !== []) {
            $query->whereNotIn('page_id', $seen);
        }
        $query->delete();
    }

    /**
     * @return list<string>
     */
    protected function inferGrantedScopes(bool $hasAds, bool $hasPages): array
    {
        $scopes = [];

        if ($hasAds) {
            $scopes[] = 'ads_read';
            $scopes[] = 'ads_management';
        }

        if ($hasPages) {
            $scopes[] = 'pages_show_list';
            $scopes[] = 'pages_messaging';
            $scopes[] = 'pages_manage_metadata';
        }

        return array_values(array_unique($scopes));
    }

    protected function normalizeAdAccountId(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (str_starts_with($raw, 'act_')) {
            return $raw;
        }

        // Graph sometimes returns bare numeric account_id.
        if (ctype_digit($raw)) {
            return 'act_' . $raw;
        }

        return $raw;
    }

    protected function graphBase(): string
    {
        $version = trim((string) (
            config('services.meta.graph_version')
            ?: config('services.messenger.graph_version')
            ?: config('services.facebook.graph_version')
            ?: 'v21.0'
        ));

        return 'https://graph.facebook.com/' . $version;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MetaAdAccount>|iterable<MetaAdAccount>  $accounts
     * @return list<array{account_id:string,account_name:?string,currency:?string,is_tracked:bool}>
     */
    protected function serializeAccounts(iterable $accounts): array
    {
        $out = [];
        foreach ($accounts as $account) {
            $out[] = [
                'account_id' => (string) $account->account_id,
                'account_name' => $account->account_name,
                'currency' => $account->currency,
                'is_tracked' => (bool) $account->is_tracked,
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MetaPage>|iterable<MetaPage>  $pages
     * @return list<array{page_id:string,page_name:?string,is_tracked:bool,webhook_subscribed:bool}>
     */
    protected function serializePages(iterable $pages): array
    {
        $out = [];
        foreach ($pages as $page) {
            $out[] = [
                'page_id' => (string) $page->page_id,
                'page_name' => $page->page_name,
                'is_tracked' => (bool) $page->is_tracked,
                'webhook_subscribed' => (bool) $page->webhook_subscribed,
            ];
        }

        return $out;
    }
}
