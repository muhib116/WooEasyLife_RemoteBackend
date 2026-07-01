<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class WebsiteBaseUrlNormalizer
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * Normalize an optional WordPress base URL (scheme + host + port + path, no trailing slash).
     */
    public function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $input)) {
            return null;
        }

        $parsed = parse_url($input);

        if (! is_array($parsed) || empty($parsed['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? 'http'));
        $host = strtolower((string) $parsed['host']);
        $port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
        $path = isset($parsed['path']) ? rtrim((string) $parsed['path'], '/') : '';

        return $scheme . '://' . $host . $port . $path;
    }

    /**
     * Normalize and ensure the base URL host matches the store domain.
     *
     * @throws ValidationException
     */
    public function normalizeForDomain(?string $input, string $domain): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $normalized = $this->normalize($input);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'base_url' => 'Enter a valid WordPress base URL starting with http:// or https://.',
            ]);
        }

        $this->assertHostMatchesDomain($normalized, $domain);

        return $normalized;
    }

    /**
     * @throws ValidationException
     */
    public function assertHostMatchesDomain(string $baseUrl, string $domain): void
    {
        $expectedHost = $this->domainNormalizer->normalize($domain);
        $actualHost = $this->domainNormalizer->normalize($baseUrl);

        if ($expectedHost === null || $actualHost === null || $expectedHost !== $actualHost) {
            throw ValidationException::withMessages([
                'base_url' => 'The WordPress base URL host must match the store domain.',
            ]);
        }
    }
}
