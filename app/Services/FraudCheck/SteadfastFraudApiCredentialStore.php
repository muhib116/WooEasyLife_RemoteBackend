<?php

namespace App\Services\FraudCheck;

use App\Models\CourierConfiguration;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Website;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Platform Steadfast score-API keys saved from the admin panel.
 * Several pairs can be saved. Merchant plugin checks still use each store's own courier keys.
 */
class SteadfastFraudApiCredentialStore
{
    public const SETTING_KEY = 'fraud_check.steadfast_api';

    private const MAX_ACCOUNTS = 500;

    private int $lockDepth = 0;

    /**
     * @return list<array{id: string, label: string, api_key: string, secret_key: string}>
     */
    public function accounts(): array
    {
        return $this->readAccounts();
    }

    /**
     * @return list<array{api_key: string, secret_key: string}>
     */
    public function pairs(): array
    {
        return array_map(fn (array $account) => [
            'api_key' => $account['api_key'],
            'secret_key' => $account['secret_key'],
        ], $this->readAccounts());
    }

    public function hasAccounts(): bool
    {
        return $this->readAccounts() !== [];
    }

    /**
     * Hub checks only use keys that have not failed. A failed key stays out
     * until an admin test clears the error. A key that hit a rate limit or
     * a Steadfast outage sits out only until its cooldown ends.
     *
     * @return list<array{api_key: string, secret_key: string}>
     */
    public function hubCandidates(int $limit): array
    {
        $healthy = [];

        foreach ($this->readAccounts() as $account) {
            if (($account['last_error'] ?? '') !== '' || $this->isCoolingDown($account['api_key'])) {
                continue;
            }

            $healthy[] = [
                'api_key' => $account['api_key'],
                'secret_key' => $account['secret_key'],
            ];
        }

        shuffle($healthy);

        return array_slice($healthy, 0, max(1, $limit));
    }

    /**
     * @return array{id: string, label: string, api_key: string, secret_key: string, last_error: string, last_error_at: string, use_count: int, source_user_id: int|null}|null
     */
    public function find(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        foreach ($this->readAccounts() as $account) {
            if ($account['id'] === $id) {
                return $account;
            }
        }

        return null;
    }

    public function merchantKeyCount(): int
    {
        $chosen = [];

        $rows = CourierConfiguration::query()
            ->where('slug', 'steadfast')
            ->whereNotNull('user_id')
            ->orderByDesc('id')
            ->get(['user_id', 'api_key', 'secret_key']);

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;

            if (isset($chosen[$userId])) {
                continue;
            }

            if (trim((string) $row->api_key) === '' || trim((string) $row->secret_key) === '') {
                continue;
            }

            $chosen[$userId] = true;
        }

        if ($chosen === []) {
            return 0;
        }

        return User::query()
            ->where('role', 'user')
            ->whereIn('id', array_keys($chosen))
            ->count();
    }

    /**
     * @return array{api_key: string, secret_key: string}|null
     */
    public function pair(): ?array
    {
        return $this->pairs()[0] ?? null;
    }

    /**
     * @return array{saved: bool, env_configured: bool, accounts: list<array{id: string, label: string, api_key: string, secret_key: string, last_error: string, last_error_at: string}>}
     */
    public function adminState(): array
    {
        $accounts = $this->readAccounts();

        return [
            'saved' => $accounts !== [],
            'env_configured' => filled(config('fraud-checker-bd-courier.steadfast.api_key'))
                && filled(config('fraud-checker-bd-courier.steadfast.secret_key')),
            'merchant_key_count' => $this->merchantKeyCount(),
            'accounts' => array_map(fn (array $account) => [
                'id' => $account['id'],
                'label' => $account['label'],
                'api_key' => $account['api_key'],
                'secret_key' => $account['secret_key'],
                'last_error' => $account['last_error'],
                'last_error_at' => $account['last_error_at'],
                'use_count' => $account['use_count'],
            ], $accounts),
        ];
    }

    public function recordOutcome(string $apiKey, ?string $error): void
    {
        $this->recordOutcomes([$apiKey => $error]);
    }

    public function coolDown(string $apiKey, int $seconds): void
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '' || $seconds <= 0) {
            return;
        }

        Cache::put($this->cooldownKey($apiKey), true, now()->addSeconds($seconds));
    }

    public function isCoolingDown(string $apiKey): bool
    {
        $apiKey = trim($apiKey);

        return $apiKey !== '' && Cache::has($this->cooldownKey($apiKey));
    }

    private function cooldownKey(string $apiKey): string
    {
        return 'fraud_check_steadfast_key_cooldown:'.hash('sha256', $apiKey);
    }

    /**
     * @param  array<string, string|null>  $outcomes
     */
    public function recordOutcomes(array $outcomes, ?string $usedApiKey = null): void
    {
        $usedApiKey = trim((string) $usedApiKey);

        $answered = $outcomes;
        if ($usedApiKey !== '') {
            $answered[$usedApiKey] = '';
        }

        foreach ($answered as $apiKey => $error) {
            if (trim((string) $apiKey) !== '' && trim((string) $error) === '') {
                Cache::forget($this->cooldownKey(trim((string) $apiKey)));
            }
        }

        if (($outcomes === [] && $usedApiKey === '') || ! $this->tableReady()) {
            return;
        }

        $this->withLock(function () use ($outcomes, $usedApiKey): void {
            $accounts = $this->readAccounts();
            $changed = false;
            $stampedAt = now()->timezone('Asia/Dhaka')->format('j M Y, g:i A');

            foreach ($accounts as &$account) {
                if ($usedApiKey !== '' && $this->sameSecret($account['api_key'], $usedApiKey)) {
                    $account['use_count'] = (int) $account['use_count'] + 1;
                    $changed = true;
                }

                foreach ($outcomes as $apiKey => $error) {
                    if (! $this->sameSecret($account['api_key'], trim((string) $apiKey))) {
                        continue;
                    }

                    $nextError = trim((string) $error);
                    $nextAt = $nextError === '' ? '' : $stampedAt;

                    if ($account['last_error'] !== $nextError || $account['last_error_at'] !== $nextAt) {
                        $account['last_error'] = $nextError;
                        $account['last_error_at'] = $nextAt;
                        $changed = true;
                    }

                    break;
                }
            }
            unset($account);

            if ($changed) {
                $this->writeAccounts($accounts);
            }
        }, false);
    }

    public function add(string $apiKey, string $secretKey, ?string $label = null): void
    {
        $this->withLock(function () use ($apiKey, $secretKey, $label): void {
            $this->addUnlocked($apiKey, $secretKey, $label);
        });
    }

    private function addUnlocked(string $apiKey, string $secretKey, ?string $label = null): void
    {
        if (! $this->tableReady()) {
            throw new InvalidArgumentException('Platform settings are not available yet.');
        }

        $apiKey = trim($apiKey);
        $secretKey = trim($secretKey);
        $label = $this->normalizeLabel($label);

        if ($apiKey === '') {
            throw new InvalidArgumentException('API key is required.');
        }

        if ($secretKey === '') {
            throw new InvalidArgumentException('Secret key is required.');
        }

        $accounts = $this->readAccounts();

        if (count($accounts) >= self::MAX_ACCOUNTS) {
            throw new InvalidArgumentException('You can save up to '.self::MAX_ACCOUNTS.' Steadfast API keys.');
        }

        foreach ($accounts as $account) {
            if ($this->sameSecret($account['api_key'], $apiKey)) {
                throw new InvalidArgumentException('This API key is already saved.');
            }
        }

        $accounts[] = [
            'id' => (string) Str::uuid(),
            'label' => $label,
            'api_key' => $apiKey,
            'secret_key' => $secretKey,
            'last_error' => '',
            'last_error_at' => '',
            'use_count' => 0,
            'source_user_id' => null,
        ];

        $this->writeAccounts($accounts);
    }

    public function updateAccount(string $id, string $apiKey, string $secretKey, ?string $label = null): void
    {
        $this->withLock(function () use ($id, $apiKey, $secretKey, $label): void {
            $this->updateAccountUnlocked($id, $apiKey, $secretKey, $label);
        });
    }

    private function updateAccountUnlocked(string $id, string $apiKey, string $secretKey, ?string $label = null): void
    {
        if (! $this->tableReady()) {
            throw new InvalidArgumentException('Platform settings are not available yet.');
        }

        $id = trim($id);
        $apiKey = trim($apiKey);
        $secretKey = trim($secretKey);
        $label = mb_substr($this->normalizeLabel($label), 0, 180);

        if ($id === '') {
            throw new InvalidArgumentException('That Steadfast API key is no longer in the list.');
        }

        if ($apiKey === '') {
            throw new InvalidArgumentException('API key is required.');
        }

        if ($secretKey === '') {
            throw new InvalidArgumentException('Secret key is required.');
        }

        $accounts = $this->readAccounts();
        $index = null;

        foreach ($accounts as $i => $account) {
            if ($account['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            throw new InvalidArgumentException('That Steadfast API key is no longer in the list.');
        }

        foreach ($accounts as $i => $account) {
            if ($i !== $index && $this->sameSecret($account['api_key'], $apiKey)) {
                throw new InvalidArgumentException('This API key is already saved.');
            }
        }

        $accounts[$index]['label'] = $label;
        $accounts[$index]['api_key'] = $apiKey;
        $accounts[$index]['secret_key'] = $secretKey;
        $this->writeAccounts($accounts);
    }

    /**
     * Single-slot helper used by older callers. A blank secret keeps the first account's secret.
     */
    public function put(string $apiKey, ?string $secretKey): void
    {
        $this->withLock(function () use ($apiKey, $secretKey): void {
            $this->putUnlocked($apiKey, $secretKey);
        });
    }

    private function putUnlocked(string $apiKey, ?string $secretKey): void
    {
        $accounts = $this->readAccounts();
        $apiKey = trim($apiKey);
        $secretKey = $secretKey !== null ? trim($secretKey) : '';

        if ($apiKey === '') {
            throw new InvalidArgumentException('API key is required.');
        }

        if ($accounts === []) {
            $this->addUnlocked($apiKey, $secretKey);

            return;
        }

        if ($secretKey === '') {
            $secretKey = $accounts[0]['secret_key'];
        }

        $accounts[0]['api_key'] = $apiKey;
        $accounts[0]['secret_key'] = $secretKey;
        $accounts[0]['last_error'] = '';
        $accounts[0]['last_error_at'] = '';
        $this->writeAccounts($accounts);
    }

    public function remove(string $id): void
    {
        $this->withLock(function () use ($id): void {
            $this->removeUnlocked($id);
        });
    }

    private function removeUnlocked(string $id): void
    {
        $id = trim($id);
        if ($id === '' || ! $this->tableReady()) {
            return;
        }

        $accounts = array_values(array_filter(
            $this->readAccounts(),
            fn (array $account) => $account['id'] !== $id,
        ));

        if ($accounts === []) {
            $this->clearUnlocked();

            return;
        }

        $this->writeAccounts($accounts);
    }

    /**
     * Copy each merchant's newest Steadfast API pair into this list.
     * The label is the merchant name plus their website domains.
     *
     * @return array{added: int, already_saved: int, missing_keys: int}
     */
    public function importMerchantKeys(): array
    {
        $result = $this->withLock(fn (): array => $this->importMerchantKeysUnlocked());

        return is_array($result) ? $result : [
            'added' => 0,
            'updated' => 0,
            'already_saved' => 0,
            'missing_keys' => 0,
        ];
    }

    /**
     * @return array{added: int, updated: int, already_saved: int, missing_keys: int}
     */
    private function importMerchantKeysUnlocked(): array
    {
        $added = 0;
        $updated = 0;
        $alreadySaved = 0;
        $missingKeys = 0;

        $rows = CourierConfiguration::query()
            ->where('slug', 'steadfast')
            ->whereNotNull('user_id')
            ->orderByDesc('id')
            ->get(['user_id', 'api_key', 'secret_key']);

        $chosen = [];
        $usersWithRows = [];

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $usersWithRows[$userId] = true;

            if (isset($chosen[$userId])) {
                continue;
            }

            $apiKey = trim((string) $row->api_key);
            $secretKey = trim((string) $row->secret_key);

            if ($apiKey === '' || $secretKey === '') {
                continue;
            }

            $chosen[$userId] = [
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
            ];
        }

        $users = User::query()
            ->where('role', 'user')
            ->whereIn('id', array_keys($usersWithRows))
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        $domainsByUser = Website::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get(['user_id', 'domain'])
            ->groupBy('user_id');

        $accounts = $this->readAccounts();
        $dirty = false;

        foreach ($users as $user) {
            $keys = $chosen[$user->id] ?? null;

            if ($keys === null) {
                $missingKeys++;

                continue;
            }

            $label = $this->merchantLabel($user, $domainsByUser->get($user->id) ?? collect());
            $index = $this->importMatchIndex($accounts, (int) $user->id, $keys['api_key']);

            if ($index === null) {
                if (count($accounts) >= self::MAX_ACCOUNTS) {
                    break;
                }

                $accounts[] = [
                    'id' => (string) Str::uuid(),
                    'label' => $label,
                    'api_key' => $keys['api_key'],
                    'secret_key' => $keys['secret_key'],
                    'last_error' => '',
                    'last_error_at' => '',
                    'use_count' => 0,
                    'source_user_id' => (int) $user->id,
                ];
                $added++;
                $dirty = true;

                continue;
            }

            $current = $accounts[$index];
            $samePair = $this->sameSecret($current['api_key'], $keys['api_key'])
                && $this->sameSecret($current['secret_key'], $keys['secret_key']);

            if ($samePair) {
                $alreadySaved++;

                if ((int) ($current['source_user_id'] ?? 0) !== (int) $user->id) {
                    $accounts[$index]['source_user_id'] = (int) $user->id;
                    $dirty = true;
                }

                if ($current['label'] === '') {
                    $accounts[$index]['label'] = $label;
                    $dirty = true;
                }

                continue;
            }

            if ($this->apiKeyOwnedByAnother($accounts, $index, $keys['api_key'])) {
                $alreadySaved++;

                continue;
            }

            $accounts[$index]['label'] = $label;
            $accounts[$index]['api_key'] = $keys['api_key'];
            $accounts[$index]['secret_key'] = $keys['secret_key'];
            $accounts[$index]['source_user_id'] = (int) $user->id;
            $updated++;
            $dirty = true;
        }

        if ($dirty) {
            $this->writeAccounts($accounts);
        }

        return [
            'added' => $added,
            'updated' => $updated,
            'already_saved' => $alreadySaved,
            'missing_keys' => $missingKeys,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $websites
     */
    private function merchantLabel(User $user, $websites): string
    {
        $domains = collect($websites)
            ->pluck('domain')
            ->map(fn ($domain) => trim((string) $domain))
            ->filter()
            ->unique()
            ->values();

        $name = trim((string) $user->name);
        if ($name === '') {
            $name = trim((string) $user->email);
        }
        if ($name === '') {
            $name = 'Merchant '.$user->id;
        }

        $label = $domains->isEmpty()
            ? $name
            : $name.' · '.$domains->implode(', ');

        return mb_substr($this->normalizeLabel($label), 0, 180);
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     */
    private function importMatchIndex(array $accounts, int $userId, string $apiKey): ?int
    {
        foreach ($accounts as $index => $account) {
            if ((int) ($account['source_user_id'] ?? 0) === $userId) {
                return $index;
            }
        }

        foreach ($accounts as $index => $account) {
            if ($this->sameSecret((string) $account['api_key'], $apiKey)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     */
    private function apiKeyOwnedByAnother(array $accounts, int $index, string $apiKey): bool
    {
        foreach ($accounts as $otherIndex => $account) {
            if ($otherIndex === $index) {
                continue;
            }

            if ($this->sameSecret((string) $account['api_key'], $apiKey)) {
                return true;
            }
        }

        return false;
    }

    public function clear(): void
    {
        $this->withLock(function (): void {
            $this->clearUnlocked();
        });
    }

    private function clearUnlocked(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->where('key', self::SETTING_KEY)->delete();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function withLock(callable $callback, bool $required = true): mixed
    {
        if ($this->lockDepth > 0) {
            return $callback();
        }

        $lock = Cache::lock('fraud_check_steadfast_api_keys', 15);
        $acquired = false;

        try {
            $lock->block(5);
            $acquired = true;
        } catch (LockTimeoutException) {
            if ($required) {
                throw new InvalidArgumentException('Steadfast API keys are busy. Try again.');
            }

            return null;
        } catch (Throwable) {
            return $callback();
        }

        $this->lockDepth++;

        try {
            return $callback();
        } finally {
            $this->lockDepth--;

            if ($acquired) {
                $lock->release();
            }
        }
    }

    /**
     * @param  list<array{id: string, label: string, api_key: string, secret_key: string}>  $accounts
     */
    private function writeAccounts(array $accounts): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => [
                'accounts' => array_map(fn (array $account) => [
                    'id' => $account['id'],
                    'label' => $account['label'],
                    'api_key' => Crypt::encryptString($account['api_key']),
                    'secret_key' => Crypt::encryptString($account['secret_key']),
                    'last_error' => $account['last_error'] ?? '',
                    'last_error_at' => $account['last_error_at'] ?? '',
                    'use_count' => (int) ($account['use_count'] ?? 0),
                    'source_user_id' => $account['source_user_id'] ?? null,
                ], $accounts),
            ]],
        );
    }

    /**
     * @return list<array{id: string, label: string, api_key: string, secret_key: string}>
     */
    private function readAccounts(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $value = PlatformSetting::query()->where('key', self::SETTING_KEY)->first()?->value;
        if (! is_array($value)) {
            return [];
        }

        $rows = $value['accounts'] ?? null;
        if (! is_array($rows)) {
            $legacy = $this->decodeAccount([
                'id' => 'legacy',
                'label' => '',
                'api_key' => $value['api_key'] ?? '',
                'secret_key' => $value['secret_key'] ?? '',
            ]);

            return $legacy === null ? [] : [$legacy];
        }

        $accounts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $account = $this->decodeAccount($row);
            if ($account !== null) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: string, label: string, api_key: string, secret_key: string}|null
     */
    private function decodeAccount(array $row): ?array
    {
        $apiKey = $this->decrypt((string) ($row['api_key'] ?? ''));
        $secretKey = $this->decrypt((string) ($row['secret_key'] ?? ''));

        if ($apiKey === null || $secretKey === null) {
            return null;
        }

        $id = trim((string) ($row['id'] ?? ''));

        return [
            'id' => $id !== '' ? $id : 'legacy',
            'label' => $this->normalizeLabel(isset($row['label']) ? (string) $row['label'] : null),
            'api_key' => $apiKey,
            'secret_key' => $secretKey,
            'last_error' => trim((string) ($row['last_error'] ?? '')),
            'last_error_at' => trim((string) ($row['last_error_at'] ?? '')),
            'use_count' => max(0, (int) ($row['use_count'] ?? 0)),
            'source_user_id' => is_numeric($row['source_user_id'] ?? null) ? (int) $row['source_user_id'] : null,
        ];
    }

    private function sameSecret(string $left, string $right): bool
    {
        return $left !== '' && strlen($left) === strlen($right) && hash_equals($left, $right);
    }

    private function normalizeLabel(?string $label): string
    {
        return trim((string) $label);
    }

    private function decrypt(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $plain = trim(Crypt::decryptString($value));

            return $plain !== '' ? $plain : null;
        } catch (DecryptException|Throwable) {
            return null;
        }
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
