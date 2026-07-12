<?php

namespace App\Services;

use App\Traits\Util;

class DomainNormalizer
{
    use Util;

    /**
     * Normalize a domain or URL to a lowercase hostname (no scheme/path).
     */
    public function normalize(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $host = $this->getDomainFromUrl(trim($input));

        if ($host === null || $host === '') {
            return null;
        }

        return strtolower($host);
    }

    public function matches(?string $left, ?string $right): bool
    {
        $normalizedLeft = $this->normalize($left);
        $normalizedRight = $this->normalize($right);

        return $normalizedLeft !== null
            && $normalizedRight !== null
            && $normalizedLeft === $normalizedRight;
    }

    /**
     * Narrow SQL candidates for legacy rows stored as full URLs or mixed case.
     * Always confirm with matches() before trusting a row.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public function constrainMatchingDomain($query, string $column, string $normalizedDomain): void
    {
        $host = strtolower($normalizedDomain);

        $query->whereNotNull($column)->where(function ($builder) use ($column, $host) {
            $builder->whereRaw('LOWER('.$column.') = ?', [$host]);

            foreach ($this->domainStoragePrefixes() as $prefix) {
                $builder->orWhereRaw('LOWER('.$column.') LIKE ?', [strtolower($prefix.$host).'%']);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function domainStoragePrefixes(): array
    {
        return [
            'http://',
            'https://',
            'http://www.',
            'https://www.',
        ];
    }

    public function hasDnsARecord(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $records = @dns_get_record($host, DNS_A);

        return ! empty($records);
    }

    /**
     * Same public DNS check used for merchant website setup.
     * Allows localhost/127.0.0.1 only in local & testing.
     */
    public function resolvesPublicly(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        if (app()->environment(['local', 'testing']) && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if ($this->hasDnsARecord($host)) {
            return true;
        }

        if (str_starts_with($host, 'www.')) {
            return $this->hasDnsARecord(substr($host, 4));
        }

        return $this->hasDnsARecord('www.'.$host);
    }
}
