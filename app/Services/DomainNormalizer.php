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

    public function hasDnsARecord(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $records = @dns_get_record($host, DNS_A);

        return ! empty($records);
    }
}
