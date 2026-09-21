<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class MerchantAdminSearch
{
    public function apply(Builder $query, string $search): Builder
    {
        $tokens = $this->tokens($search);

        if ($tokens === []) {
            return $query;
        }

        foreach ($tokens as $token) {
            $query->where(function (Builder $group) use ($token) {
                $this->constrainToken($group, $token);
            });
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public function tokens(string $search): array
    {
        $search = trim(mb_substr($search, 0, 80));

        if ($search === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $search) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return array_slice($tokens, 0, 6);
    }

    private function constrainToken(Builder $query, string $token): void
    {
        $like = '%'.$this->escapeLike($token).'%';

        $query->whereRaw('users.name LIKE ? ESCAPE ?', [$like, '\\'])
            ->orWhereRaw('users.email LIKE ? ESCAPE ?', [$like, '\\'])
            ->orWhereRaw('users.phone LIKE ? ESCAPE ?', [$like, '\\'])
            ->orWhereHas('websites', function (Builder $websites) use ($like) {
                $websites->whereRaw('domain LIKE ? ESCAPE ?', [$like, '\\']);
            })
            ->orWhereHas('userPackage', function (Builder $packages) use ($like) {
                $packages->whereRaw('domain LIKE ? ESCAPE ?', [$like, '\\']);
            });

        $digits = preg_replace('/\D+/', '', $token) ?? '';
        if (strlen($digits) >= 3) {
            $query->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(users.phone, '-', ''), ' ', ''), '+', ''), '.', '') LIKE ? ESCAPE ?",
                ['%'.$this->escapeLike($digits).'%', '\\']
            );
        }

        $compact = strtolower(preg_replace('/[^a-z0-9]+/i', '', $token) ?? '');
        if (strlen($compact) >= 3) {
            $compactLike = '%'.$this->escapeLike($compact).'%';
            $query->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(LOWER(users.email), '.', ''), '@', ''), '-', '') LIKE ? ESCAPE ?",
                [$compactLike, '\\']
            )->orWhereHas('websites', function (Builder $websites) use ($compactLike) {
                $websites->whereRaw(
                    "REPLACE(REPLACE(LOWER(domain), '.', ''), '-', '') LIKE ? ESCAPE ?",
                    [$compactLike, '\\']
                );
            })->orWhereHas('userPackage', function (Builder $packages) use ($compactLike) {
                $packages->whereRaw(
                    "REPLACE(REPLACE(LOWER(domain), '.', ''), '-', '') LIKE ? ESCAPE ?",
                    [$compactLike, '\\']
                );
            });
        }

        if (mb_strlen($token) >= 4 && ! ctype_digit($digits)) {
            $query->orWhereRaw('SOUNDEX(users.name) = SOUNDEX(?)', [$token])
                ->orWhereRaw("SOUNDEX(SUBSTRING_INDEX(users.name, ' ', 1)) = SOUNDEX(?)", [$token])
                ->orWhereRaw("SOUNDEX(SUBSTRING_INDEX(users.name, ' ', -1)) = SOUNDEX(?)", [$token]);
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
