<?php

namespace App\Services\FraudCheck;

use App\Models\FraudPartnerCredential;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class FraudPartnerCredentialResolver
{
    /**
     * Identifier field label per courier for the admin UI.
     *
     * @return array<string, array{identifier_label: string, identifier_placeholder: string, help: string}>
     */
    public function courierMeta(): array
    {
        return [
            'steadfast' => [
                'identifier_label' => 'Email / username',
                'identifier_placeholder' => 'merchant@example.com',
                'help' => 'Steadfast merchant portal login',
            ],
            'pathao' => [
                'identifier_label' => 'Email / username',
                'identifier_placeholder' => 'merchant@example.com',
                'help' => 'Pathao merchant portal login (Hermes OAuth client stays in .env)',
            ],
            'paperfly' => [
                'identifier_label' => 'Username',
                'identifier_placeholder' => 'paperfly username',
                'help' => 'Paperfly merchant API login',
            ],
            'redx' => [
                'identifier_label' => 'Phone',
                'identifier_placeholder' => '01XXXXXXXXX',
                'help' => 'RedX merchant phone (local BD format, without 88)',
            ],
            'carrybee' => [
                'identifier_label' => 'Phone',
                'identifier_placeholder' => '01XXXXXXXXX',
                'help' => 'Carrybee merchant phone (local BD format)',
            ],
        ];
    }

    public function tableReady(): bool
    {
        try {
            return Schema::hasTable('fraud_partner_credentials');
        } catch (\Throwable) {
            return false;
        }
    }

    public function isConfigured(string $courier): bool
    {
        return $this->primary($courier) !== null;
    }

    /**
     * Preferred credential: lowest priority active DB row, else .env fallback.
     *
     * @return array{id: int|null, courier: string, identifier: string, password: string, label: string|null, source: string}|null
     */
    public function primary(string $courier): ?array
    {
        $candidates = $this->candidates($courier);

        return $candidates[0] ?? null;
    }

    /**
     * Credentials to try for a fresh login after session expiry / cache miss.
     * Active DB accounts are shuffled randomly; .env stays last-resort.
     *
     * @return list<array{id: int|null, courier: string, identifier: string, password: string, label: string|null, source: string}>
     */
    public function loginCandidates(string $courier): array
    {
        return $this->candidates($courier, randomize: true);
    }

    /**
     * Active DB credentials (priority ASC by default) then .env fallback.
     * When $randomize is true (session expired / fresh login), shuffle DB accounts.
     *
     * @return list<array{id: int|null, courier: string, identifier: string, password: string, label: string|null, source: string}>
     */
    public function candidates(string $courier, bool $randomize = false): array
    {
        $courier = strtolower(trim($courier));
        $db = [];

        foreach ($this->activeDbRows($courier) as $row) {
            $password = $row->password;
            if (! filled($row->identifier) || ! filled($password)) {
                continue;
            }

            $db[] = [
                'id' => (int) $row->id,
                'courier' => $courier,
                'identifier' => (string) $row->identifier,
                'password' => (string) $password,
                'label' => $row->label,
                'source' => 'database',
            ];
        }

        if ($randomize && count($db) > 1) {
            shuffle($db);
        }

        $out = $db;

        $env = $this->envCredential($courier);
        if ($env !== null) {
            $envIdentifier = $this->normalizeIdentifier($courier, $env['identifier']);
            $already = collect($out)->contains(
                fn (array $item) => $this->normalizeIdentifier($courier, $item['identifier']) === $envIdentifier,
            );
            if (! $already) {
                $out[] = $env;
            }
        }

        return $out;
    }

    public function markUsed(?int $id): void
    {
        if ($id === null || ! $this->tableReady()) {
            return;
        }

        FraudPartnerCredential::query()->whereKey($id)->update([
            'last_used_at' => now(),
        ]);
    }

    public function markSuccess(?int $id): void
    {
        if ($id === null || ! $this->tableReady()) {
            return;
        }

        FraudPartnerCredential::query()->whereKey($id)->update([
            'last_used_at' => now(),
            'last_success_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markFailure(?int $id, string $error): void
    {
        if ($id === null || ! $this->tableReady()) {
            return;
        }

        FraudPartnerCredential::query()->whereKey($id)->update([
            'last_used_at' => now(),
            'last_error' => mb_substr($error, 0, 500),
        ]);
    }

    /**
     * @return Collection<int, FraudPartnerCredential>
     */
    public function listForAdmin(?string $courier = null): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        $query = FraudPartnerCredential::query()
            ->orderBy('courier')
            ->orderBy('priority')
            ->orderBy('id');

        if ($courier) {
            $query->where('courier', $courier);
        }

        return $query->get();
    }

    /**
     * Env fallbacks shown in admin so operators know what's still in .env.
     *
     * @return list<array<string, mixed>>
     */
    public function envFallbacksForAdmin(): array
    {
        $rows = [];
        foreach (FraudPartnerCredential::COURIERS as $courier) {
            $env = $this->envCredential($courier);
            if ($env === null) {
                continue;
            }

            $rows[] = [
                'id' => null,
                'courier' => $courier,
                'label' => '.env fallback',
                'identifier' => $env['identifier'],
                'masked_identifier' => $this->mask($env['identifier']),
                'is_active' => true,
                'priority' => 9999,
                'last_used_at' => null,
                'last_success_at' => null,
                'last_error' => null,
                'has_secret' => true,
                'source' => 'env',
                'read_only' => true,
            ];
        }

        return $rows;
    }

    public function sessionCacheKey(string $courier, string $identifier): string
    {
        return 'fraud_check_'.$courier.'_token_'.md5($identifier);
    }

    public function forgetSessionCaches(string $courier): int
    {
        $cleared = 0;
        $courier = strtolower(trim($courier));

        foreach ($this->candidates($courier) as $candidate) {
            $key = $this->sessionCacheKey($courier, $candidate['identifier']);
            if (Cache::has($key)) {
                Cache::forget($key);
                $cleared++;
            }

            if ($courier === 'steadfast') {
                $sessionWithPassword = SteadfastFraudChecker::sessionCacheKeyFor([
                    'username' => $candidate['identifier'],
                    'password' => $candidate['password'],
                ]);
                if (Cache::has($sessionWithPassword)) {
                    Cache::forget($sessionWithPassword);
                    $cleared++;
                }

                $sessionUsernameOnly = SteadfastFraudChecker::sessionCacheKeyFor(
                    null,
                    $candidate['identifier'],
                );
                if (Cache::has($sessionUsernameOnly)) {
                    Cache::forget($sessionUsernameOnly);
                    $cleared++;
                }
            }
        }

        return $cleared;
    }

    /**
     * Normalize partner login identifiers before save (especially BD phones).
     */
    public function normalizeIdentifier(string $courier, string $identifier): string
    {
        $identifier = trim($identifier);
        $courier = strtolower(trim($courier));

        if (in_array($courier, ['redx', 'carrybee'], true)) {
            $digits = preg_replace('/\D/', '', $identifier) ?? '';

            if (str_starts_with($digits, '880') && strlen($digits) === 13) {
                $digits = '0'.substr($digits, 3);
            } elseif (str_starts_with($digits, '88') && strlen($digits) === 12) {
                $digits = '0'.substr($digits, 2);
            }

            if (preg_match('/^01[3-9]\d{8}$/', $digits)) {
                return $digits;
            }
        }

        if (in_array($courier, ['steadfast', 'pathao', 'paperfly'], true)) {
            return strtolower($identifier);
        }

        return $identifier;
    }

    /**
     * @return Collection<int, FraudPartnerCredential>
     */
    private function activeDbRows(string $courier): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        return FraudPartnerCredential::query()
            ->where('courier', $courier)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{id: null, courier: string, identifier: string, password: string, label: string, source: string}|null
     */
    private function envCredential(string $courier): ?array
    {
        [$identifier, $password] = match ($courier) {
            'steadfast' => [
                config('fraud-checker-bd-courier.steadfast.user'),
                config('fraud-checker-bd-courier.steadfast.password'),
            ],
            'pathao' => [
                config('fraud-checker-bd-courier.pathao.user'),
                config('fraud-checker-bd-courier.pathao.password'),
            ],
            'paperfly' => [
                config('fraud-checker-bd-courier.paperfly.user'),
                config('fraud-checker-bd-courier.paperfly.password'),
            ],
            'redx' => [
                config('courier-checker.redx.phone') ?: config('fraud-checker-bd-courier.redx.phone'),
                config('courier-checker.redx.password') ?: config('fraud-checker-bd-courier.redx.password'),
            ],
            'carrybee' => [
                config('courier-checker.carrybee.phone') ?: config('fraud-checker-bd-courier.carrybee.phone'),
                config('courier-checker.carrybee.password') ?: config('fraud-checker-bd-courier.carrybee.password'),
            ],
            default => [null, null],
        };

        if (! filled($identifier) || ! filled($password)) {
            return null;
        }

        return [
            'id' => null,
            'courier' => $courier,
            'identifier' => $this->normalizeIdentifier($courier, (string) $identifier),
            'password' => (string) $password,
            'label' => '.env',
            'source' => 'env',
        ];
    }

    private function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 2).str_repeat('*', max(0, $len - 4)).substr($value, -2);
    }
}
