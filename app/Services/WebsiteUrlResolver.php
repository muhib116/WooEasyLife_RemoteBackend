<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\Website;

class WebsiteUrlResolver
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * Build ordered WordPress site URL candidates for backend HTTP calls.
     *
     * @return array<int, string>
     */
    public function siteUrlCandidates(?Website $website, ?AccessToken $token = null): array
    {
        $candidates = [];

        $baseUrl = trim((string) ($website?->base_url ?? ''));
        $domain = trim((string) ($website?->domain ?? ''));

        if ($baseUrl !== '') {
            $candidates[] = rtrim($baseUrl, '/');
        } elseif ($domain !== '') {
            $this->appendDerivedUrl($candidates, $domain);
        }

        $tokenDomain = trim((string) ($token?->domain ?? ''));

        if ($tokenDomain !== '' && ($website === null || ! $this->domainNormalizer->matches($tokenDomain, $website->domain))) {
            $this->appendDerivedUrl($candidates, $tokenDomain);
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function appendDerivedUrl(array &$candidates, string $value): void
    {
        $derived = str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? rtrim($value, '/')
            : 'https://' . rtrim($value, '/');

        if (! in_array($derived, $candidates, true)) {
            $candidates[] = $derived;
        }
    }
}
