<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class MerchantDomainValidator
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer,
        protected DomainAvailabilityService $domainAvailability
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function validate(
        User $merchant,
        string $rawDomain,
        bool $forAdmin = false,
        bool $requireNewWebsite = false
    ): string {
        $domain = $this->domainNormalizer->normalize($rawDomain);
        if (! $domain) {
            throw ValidationException::withMessages([
                'domain' => 'Enter a valid website domain (e.g. shop.example.com).',
            ]);
        }

        if (! $this->domainNormalizer->hasDnsARecord($domain)) {
            if (! (app()->environment('local') && in_array($domain, ['localhost', '127.0.0.1'], true))) {
                throw ValidationException::withMessages([
                    'domain' => 'Domain must resolve to a DNS A record before continuing.',
                ]);
            }
        }

        $this->domainAvailability->rejectCrossUserWebsiteClaim($merchant, $domain, $forAdmin);
        $this->domainAvailability->assertAvailableForUser($merchant, $domain, $forAdmin);

        if ($requireNewWebsite) {
            $this->domainAvailability->rejectDuplicateWebsiteForUser($merchant, $domain);
        }

        return $domain;
    }
}
