<?php

namespace App\Services\Messenger;

use App\Models\AccessToken;
use App\Models\MessengerPageConnection;
use Illuminate\Support\Facades\Log;

/**
 * Resolve the Facebook Page connection for a store license.
 *
 * Send/upload used to require an exact access_token_id (+ optional page_id) match.
 * License re-provisioning creates a new Sanctum token without migrating rows, which
 * left WordPress showing "Connected" (local DB) while hub send returned 404.
 */
class MessengerPageConnectionResolver
{
    /**
     * Find a connected page for this license, healing token rotation when safe.
     */
    public function resolve(AccessToken $accessToken, string $pageId = '', bool $rebind = true): ?MessengerPageConnection
    {
        $pageId = trim($pageId);
        $tokenId = (int) $accessToken->id;

        $connection = $this->findForToken($tokenId, $pageId);
        if ($connection) {
            return $connection;
        }

        // Page id filter may be stale (customer row from an older Page). Retry without it.
        if ($pageId !== '') {
            $connection = $this->findForToken($tokenId, '');
            if ($connection) {
                return $connection;
            }
        }

        if (! $rebind) {
            return null;
        }

        $connection = $this->rebindFromPreviousLicense($accessToken, $pageId);
        if ($connection) {
            return $connection;
        }

        // Last resort: any connected page for this website/user (stale local page_id).
        if ($pageId !== '') {
            return $this->rebindFromPreviousLicense($accessToken, '');
        }

        return null;
    }

    public function hasConnection(AccessToken $accessToken, string $pageId = ''): bool
    {
        return $this->resolve($accessToken, $pageId, true) !== null;
    }

    /**
     * @return array{
     *   connected:bool,
     *   page_id:?string,
     *   page_name:?string,
     *   page_picture:?string,
     *   instagram_linked?:bool,
     *   instagram_business_account_id?:string,
     *   instagram_username?:string
     * }
     */
    public function statusPayload(AccessToken $accessToken, string $pageId = ''): array
    {
        $connection = $this->resolve($accessToken, $pageId, true);

        if (! $connection) {
            return [
                'connected' => false,
                'page_id' => null,
                'page_name' => null,
                'page_picture' => null,
                'instagram_linked' => false,
                'instagram_business_account_id' => '',
                'instagram_username' => '',
            ];
        }

        // Backfill IG account id for older connections (needed to route object=instagram webhooks).
        if (trim((string) ($connection->instagram_business_account_id ?? '')) === '') {
            try {
                app(MessengerPageOAuthService::class)->syncInstagramLinkage($connection, true);
                $connection = $connection->fresh() ?: $connection;
            } catch (\Throwable $exception) {
                Log::info('messenger.instagram_linkage_status_backfill_failed', [
                    'page_id' => $connection->page_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $igAccountId = trim((string) ($connection->instagram_business_account_id ?? ''));
        $igUsername = trim((string) ($connection->instagram_username ?? ''));

        return [
            'connected' => true,
            'page_id' => (string) $connection->page_id,
            'page_name' => (string) ($connection->page_name ?? ''),
            'page_picture' => (string) ($connection->page_picture ?? ''),
            'instagram_linked' => $igAccountId !== '' || $igUsername !== '',
            'instagram_business_account_id' => $igAccountId,
            'instagram_username' => $igUsername,
        ];
    }

    private function findForToken(int $accessTokenId, string $pageId): ?MessengerPageConnection
    {
        $query = MessengerPageConnection::query()
            ->connected()
            ->where('access_token_id', $accessTokenId);

        if ($pageId !== '') {
            $query->where('page_id', $pageId);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * After license rotation, re-attach a connected page that still belongs to
     * the same website / user / page so send works without a full reconnect.
     */
    private function rebindFromPreviousLicense(AccessToken $accessToken, string $pageId): ?MessengerPageConnection
    {
        $query = MessengerPageConnection::query()->connected();

        if ($pageId !== '') {
            $query->where('page_id', $pageId);
        }

        $websiteId = (int) ($accessToken->website_id ?? 0);
        $userId = (int) ($accessToken->tokenable_id ?? 0);

        if ($websiteId > 0) {
            $query->where('website_id', $websiteId);
        } elseif ($userId > 0) {
            $query->where('user_id', $userId);
        } else {
            return null;
        }

        /** @var MessengerPageConnection|null $orphan */
        $orphan = $query
            ->where('access_token_id', '!=', (int) $accessToken->id)
            ->orderByDesc('id')
            ->first();

        if (! $orphan) {
            return null;
        }

        $previousTokenId = (int) $orphan->access_token_id;
        $orphan->access_token_id = (int) $accessToken->id;
        if ($websiteId > 0) {
            $orphan->website_id = $websiteId;
        }
        $orphan->save();

        Log::info('messenger.page_connection_rebound', [
            'page_id' => $orphan->page_id,
            'from_access_token_id' => $previousTokenId,
            'to_access_token_id' => (int) $accessToken->id,
            'website_id' => $websiteId ?: null,
        ]);

        return $orphan->fresh();
    }
}
