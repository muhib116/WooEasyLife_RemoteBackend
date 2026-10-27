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

    /** A rejected key is asked again after this long, one key at a time. */
    public const RECHECK_AFTER_HOURS = 6;

    private const RECHECK_GAP_SECONDS = 120;

    private const MAX_REST_SECONDS = 3600;

    private const STAMP_FORMAT = 'j M Y, g:i A';

    private const STAMP_TIMEZONE = 'Asia/Dhaka';

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
     * Keys a hub check may use, best first: keys that gave parcel counts
     * lately, then keys not asked yet, then keys that only gave a ratio.
     * A resting key sits out until its rest ends. A rejected key sits out
     * too, except one that is due for its automatic re-check; that key goes
     * first and is marked `recheck`.
     *
     * @return list<array{api_key: string, secret_key: string, shape: string|null, recheck?: bool}>
     */
    public function hubCandidates(?int $limit = null): array
    {
        $groups = ['counts' => [], 'unknown' => [], 'ratio' => []];
        $due = null;
        $dueSince = null;

        foreach ($this->readAccounts() as $account) {
            $apiKey = $account['api_key'];

            if ($this->isCoolingDown($apiKey)) {
                continue;
            }

            if ($this->isRejectedError($account['last_error'])) {
                $since = $this->stampTime($account['last_error_at']);

                if ($this->recheckDue($since) && ($due === null || $since < $dueSince)) {
                    $due = [
                        'api_key' => $apiKey,
                        'secret_key' => $account['secret_key'],
                        'shape' => null,
                        'recheck' => true,
                    ];
                    $dueSince = $since;
                }

                continue;
            }

            $shape = $this->shape($apiKey);
            $groups[$shape ?? 'unknown'][] = [
                'api_key' => $apiKey,
                'secret_key' => $account['secret_key'],
                'shape' => $shape,
            ];
        }

        foreach ($groups as &$rows) {
            shuffle($rows);
        }
        unset($rows);

        $candidates = array_merge($due !== null ? [$due] : [], $groups['counts'], $groups['unknown'], $groups['ratio']);

        if ($limit === null) {
            return $candidates;
        }

        return array_slice($candidates, 0, max(1, $limit));
    }

    /**
     * Only one rejected key is asked again per gap, across every worker.
     */
    public function claimRecheck(): bool
    {
        try {
            return Cache::add('fraud_check_steadfast_key_recheck_gate', true, now()->addSeconds(self::RECHECK_GAP_SECONDS));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Steadfast hides parcel counts on keys of stores that are not booking.
     * That is a property of the key, so it is remembered for a while.
     */
    public function noteShape(string $apiKey, bool $hasCounts): void
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            return;
        }

        Cache::put(
            $this->shapeKey($apiKey),
            $hasCounts ? 'counts' : 'ratio',
            $hasCounts ? now()->addDay() : now()->addHours(6),
        );
    }

    public function shape(string $apiKey): ?string
    {
        $value = Cache::get($this->shapeKey(trim($apiKey)));

        return in_array($value, ['counts', 'ratio'], true) ? $value : null;
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
            'recheck_after_hours' => self::RECHECK_AFTER_HOURS,
            'accounts' => array_map(fn (array $account) => [
                'id' => $account['id'],
                'label' => $account['label'],
                'api_key' => $account['api_key'],
                'secret_key' => $account['secret_key'],
                'last_error' => $account['last_error'],
                'last_error_at' => $account['last_error_at'],
                'error_temporary' => $this->isTemporaryError($account['last_error']),
                'resting_until' => $this->restingUntil($account['api_key']),
                'next_check_at' => $this->nextCheckAt($account),
                'shape' => $this->shape($account['api_key']),
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

        $until = now()->addSeconds($seconds);

        Cache::put($this->cooldownKey($apiKey), $until->toIso8601String(), $until);
    }

    /**
     * Rest a key after a failure that can pass. Each failure in a row doubles
     * the rest, up to an hour, so a key that keeps failing is asked less
     * often but is never dropped. An answer clears the count.
     */
    public function rest(string $apiKey, int $seconds, bool $exact = false): int
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '' || $seconds <= 0) {
            return 0;
        }

        if (! $exact) {
            $strikesKey = $this->strikesKey($apiKey);

            try {
                Cache::add($strikesKey, 0, now()->addDay());
                $strikes = max(1, (int) Cache::increment($strikesKey));
            } catch (Throwable) {
                $strikes = 1;
            }

            $seconds = (int) min(self::MAX_REST_SECONDS, $seconds * (2 ** min(10, $strikes - 1)));
        }

        $this->coolDown($apiKey, min(self::MAX_REST_SECONDS, $seconds));

        return $seconds;
    }

    public function clearRest(string $apiKey): void
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            return;
        }

        Cache::forget($this->cooldownKey($apiKey));
        Cache::forget($this->strikesKey($apiKey));
    }

    /**
     * Only a rejected key or secret stays off. Every other error (rate
     * limit, outage, odd answer) only rests the key.
     */
    public function isRejectedError(string $error): bool
    {
        return str_contains(strtolower(trim($error)), 'rejected this api key');
    }

    public function isTemporaryError(string $error): bool
    {
        return trim($error) !== '' && ! $this->isRejectedError($error);
    }

    public function restingUntil(string $apiKey): ?string
    {
        $value = Cache::get($this->cooldownKey($apiKey));

        if (! is_string($value) || trim($value) === '') {
            return $this->isCoolingDown($apiKey) ? 'soon' : null;
        }

        try {
            return \Carbon\Carbon::parse($value)->timezone(self::STAMP_TIMEZONE)->format('g:i A');
        } catch (\Throwable) {
            return 'soon';
        }
    }

    public function hasCoolingKey(): bool
    {
        foreach ($this->readAccounts() as $account) {
            if ($this->isCoolingDown($account['api_key'])) {
                return true;
            }
        }

        return false;
    }

    public function isCoolingDown(string $apiKey): bool
    {
        $apiKey = trim($apiKey);

        return $apiKey !== '' && Cache::has($this->cooldownKey($apiKey));
    }

    /**
     * @param  array{last_error: string, last_error_at: string}  $account
     */
    private function nextCheckAt(array $account): ?string
    {
        if (! $this->isRejectedError($account['last_error'])) {
            return null;
        }

        $since = $this->stampTime($account['last_error_at']);

        if ($this->recheckDue($since)) {
            return 'soon';
        }

        return \Carbon\Carbon::createFromTimestamp($since + self::RECHECK_AFTER_HOURS * 3600, self::STAMP_TIMEZONE)
            ->format('j M, g:i A');
    }

    private function recheckDue(int $since): bool
    {
        return $since <= time() - self::RECHECK_AFTER_HOURS * 3600;
    }

    /**
     * An empty or unreadable stamp counts as old, so that key is due.
     */
    private function stampTime(string $stamp): int
    {
        $stamp = trim($stamp);

        if ($stamp === '') {
            return 0;
        }

        try {
            return \Carbon\Carbon::createFromFormat(self::STAMP_FORMAT, $stamp, self::STAMP_TIMEZONE)->getTimestamp();
        } catch (Throwable) {
            return 0;
        }
    }

    private function cooldownKey(string $apiKey): string
    {
        return 'fraud_check_steadfast_key_cooldown:'.hash('sha256', $apiKey);
    }

    private function strikesKey(string $apiKey): string
    {
        return 'fraud_check_steadfast_key_strikes:'.hash('sha256', $apiKey);
    }

    private function shapeKey(string $apiKey): string
    {
        return 'fraud_check_steadfast_key_shape:'.hash('sha256', $apiKey);
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
                $this->clearRest((string) $apiKey);
            }
        }

        if (($outcomes === [] && $usedApiKey === '') || ! $this->tableReady()) {
            return;
        }

        $this->withLock(function () use ($outcomes, $usedApiKey): void {
            $accounts = $this->readAccounts();
            $changed = false;
            $stampedAt = now()->timezone(self::STAMP_TIMEZONE)->format(self::STAMP_FORMAT);

            foreach ($accounts as &$account) {
                if ($usedApiKey !== '' && $this->sameSecret($account['api_key'], $usedApiKey)) {
                    $account['use_count'] = (int) $account['use_count'] + 1;

                    if ($account['last_error'] !== '' || $account['last_error_at'] !== '') {
                        $account['last_error'] = '';
                        $account['last_error_at'] = '';
                    }

                    $changed = true;
                }

                foreach ($outcomes as $apiKey => $error) {
                    if (! $this->sameSecret($account['api_key'], trim((string) $apiKey))) {
                        continue;
                    }

                    $nextError = trim((string) $error);

                    if ($this->isTemporaryError($nextError)) {
                        $nextError = '';
                    }

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

        $pairChanged = ! $this->sameSecret($accounts[$index]['api_key'], $apiKey)
            || ! $this->sameSecret($accounts[$index]['secret_key'], $secretKey);

        $accounts[$index]['label'] = $label;
        $accounts[$index]['api_key'] = $apiKey;
        $accounts[$index]['secret_key'] = $secretKey;

        if ($pairChanged) {
            $this->askSoon($accounts[$index]);
        }

        $this->writeAccounts($accounts);
    }

    /**
     * A new key or secret is asked at the next re-check instead of waiting
     * out the old rejection. The error text stays until Steadfast answers.
     *
     * @param  array{api_key: string, last_error: string, last_error_at: string}  $account
     */
    private function askSoon(array &$account): void
    {
        $this->clearRest($account['api_key']);
        Cache::forget($this->shapeKey($account['api_key']));

        if ($this->isRejectedError($account['last_error'])) {
            $account['last_error_at'] = '';
        }
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
            $this->askSoon($accounts[$index]);
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
