<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\PackagePaymentRequest;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteAdminService
{
    public function __construct(
        protected WebsiteBaseUrlNormalizer $baseUrlNormalizer,
        protected DomainNormalizer $domainNormalizer,
        protected MerchantDomainValidator $domainValidator
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $merchant, Website $website, array $data): Website
    {
        $this->assertBelongsToMerchant($website, $merchant);

        return DB::transaction(function () use ($merchant, $website, $data) {
            $update = [];
            $demotedPrimary = false;
            $fromDomain = $website->domain;
            $targetDomain = $fromDomain;
            $domainChanged = false;

            if (array_key_exists('domain', $data)) {
                $normalized = $this->domainNormalizer->normalize((string) ($data['domain'] ?? ''));
                if (! $normalized) {
                    throw ValidationException::withMessages([
                        'domain' => 'Enter a valid website domain (e.g. shop.example.com).',
                    ]);
                }

                if (! $this->domainNormalizer->matches($normalized, $fromDomain)) {
                    $targetDomain = $this->domainValidator->validate(
                        $merchant,
                        (string) $data['domain'],
                        forAdmin: true,
                        requireNewWebsite: true,
                        ignoreWebsiteId: (int) $website->id,
                    );
                    $domainChanged = true;
                    $update['domain'] = $targetDomain;
                }
            }

            if (array_key_exists('title', $data)) {
                $title = trim((string) ($data['title'] ?? ''));

                if ($title === '' || ($domainChanged && $this->domainNormalizer->matches($title, $fromDomain))) {
                    $update['title'] = $targetDomain;
                } else {
                    $update['title'] = $title;
                }
            } elseif ($domainChanged && $this->domainNormalizer->matches((string) $website->title, $fromDomain)) {
                $update['title'] = $targetDomain;
            }

            if (array_key_exists('base_url', $data)) {
                $update['base_url'] = $this->resolveBaseUrl($data['base_url'], $targetDomain);
            } elseif ($domainChanged) {
                $update['base_url'] = $this->rewriteBaseUrlForDomain(
                    $website->base_url,
                    $fromDomain,
                    $targetDomain
                );
            }

            if (array_key_exists('status', $data)) {
                $update['status'] = (bool) $data['status'];
            }

            if (array_key_exists('is_primary', $data)) {
                $wantsPrimary = (bool) $data['is_primary'];

                if ($wantsPrimary) {
                    Website::query()
                        ->where('user_id', $merchant->id)
                        ->whereKeyNot($website->id)
                        ->update(['is_primary' => false]);

                    $update['is_primary'] = true;
                } elseif ($website->is_primary) {
                    $hasOtherWebsites = Website::query()
                        ->where('user_id', $merchant->id)
                        ->whereKeyNot($website->id)
                        ->exists();

                    if (! $hasOtherWebsites) {
                        throw ValidationException::withMessages([
                            'is_primary' => 'At least one website must remain primary.',
                        ]);
                    }

                    $update['is_primary'] = false;
                    $demotedPrimary = true;
                }
            }

            if ($update !== []) {
                $website->update($update);
            }

            if ($domainChanged) {
                $this->repointRelatedRecords($merchant, $website, $fromDomain, $targetDomain);
            }

            $website = $website->fresh();

            if ($demotedPrimary) {
                $this->promoteNextPrimaryWebsite($merchant, (int) $website->id);
            } elseif (! $website->is_primary) {
                $this->ensurePrimaryWebsite($merchant);
            }

            return $website->fresh();
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertBelongsToMerchant(Website $website, User $merchant): void
    {
        if ((int) $website->user_id !== (int) $merchant->id) {
            throw ValidationException::withMessages([
                'website_id' => 'Website not found for this merchant.',
            ]);
        }
    }

    private function resolveBaseUrl(mixed $baseUrl, string $domain): ?string
    {
        if ($baseUrl === null || trim((string) $baseUrl) === '') {
            return null;
        }

        return $this->baseUrlNormalizer->normalizeForDomain((string) $baseUrl, $domain);
    }

    private function rewriteBaseUrlForDomain(?string $baseUrl, string $oldDomain, string $newDomain): ?string
    {
        if ($baseUrl === null || trim($baseUrl) === '') {
            return null;
        }

        $normalized = $this->baseUrlNormalizer->normalize($baseUrl);
        if ($normalized === null) {
            return null;
        }

        $parsed = parse_url($normalized);
        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        if ($this->domainNormalizer->matches($host, $oldDomain)) {
            $scheme = strtolower((string) ($parsed['scheme'] ?? 'http'));
            $port = isset($parsed['port']) ? ':'.(int) $parsed['port'] : '';
            $path = isset($parsed['path']) ? rtrim((string) $parsed['path'], '/') : '';

            return $this->baseUrlNormalizer->normalizeForDomain(
                $scheme.'://'.$newDomain.$port.$path,
                $newDomain
            );
        }

        if ($this->domainNormalizer->matches($host, $newDomain)) {
            return $normalized;
        }

        return null;
    }

    private function repointRelatedRecords(
        User $merchant,
        Website $website,
        string $fromDomain,
        string $toDomain
    ): void {
        $websiteId = (int) $website->id;

        UserPackage::query()
            ->where('user_id', $merchant->id)
            ->get()
            ->filter(fn (UserPackage $package) => $this->recordBelongsToWebsite(
                $package->website_id,
                $package->domain,
                $websiteId,
                $fromDomain
            ))
            ->each(function (UserPackage $package) use ($toDomain, $websiteId) {
                $package->update([
                    'domain' => $toDomain,
                    'website_id' => $websiteId,
                ]);
            });

        AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $merchant->id)
            ->get()
            ->filter(fn (AccessToken $token) => $this->recordBelongsToWebsite(
                $token->website_id,
                $token->domain,
                $websiteId,
                $fromDomain
            ))
            ->each(function (AccessToken $token) use ($toDomain, $websiteId) {
                $token->update([
                    'domain' => $toDomain,
                    'website_id' => $websiteId,
                ]);
            });

        PackagePaymentRequest::query()
            ->where('user_id', $merchant->id)
            ->get()
            ->filter(fn (PackagePaymentRequest $request) => $this->recordBelongsToWebsite(
                $request->website_id,
                $request->domain,
                $websiteId,
                $fromDomain
            ))
            ->each(function (PackagePaymentRequest $request) use ($toDomain, $websiteId) {
                $request->update([
                    'domain' => $toDomain,
                    'website_id' => $request->website_id ?: $websiteId,
                ]);
            });

        UserBusiness::query()
            ->where('user_id', $merchant->id)
            ->get()
            ->filter(fn (UserBusiness $business) => $this->domainNormalizer->matches(
                $business->domain,
                $fromDomain
            ))
            ->each(fn (UserBusiness $business) => $business->update(['domain' => $toDomain]));

        SmsBalance::query()
            ->where('user_id', $merchant->id)
            ->get()
            ->filter(fn (SmsBalance $balance) => $this->domainNormalizer->matches(
                $balance->domain,
                $fromDomain
            ))
            ->each(fn (SmsBalance $balance) => $balance->update(['domain' => $toDomain]));
    }

    private function recordBelongsToWebsite(
        mixed $recordWebsiteId,
        ?string $recordDomain,
        int $websiteId,
        string $fromDomain
    ): bool {
        if ($recordWebsiteId) {
            return (int) $recordWebsiteId === $websiteId;
        }

        return $this->domainNormalizer->matches($recordDomain, $fromDomain);
    }

    private function ensurePrimaryWebsite(User $merchant): void
    {
        $websites = Website::query()
            ->where('user_id', $merchant->id)
            ->orderBy('id')
            ->get();

        if ($websites->isEmpty()) {
            return;
        }

        if ($websites->contains(fn (Website $row) => $row->is_primary)) {
            return;
        }

        Website::query()
            ->whereKey($websites->first()->id)
            ->update(['is_primary' => true]);
    }

    private function promoteNextPrimaryWebsite(User $merchant, int $excludeWebsiteId): void
    {
        Website::query()
            ->where('user_id', $merchant->id)
            ->update(['is_primary' => false]);

        $next = Website::query()
            ->where('user_id', $merchant->id)
            ->whereKeyNot($excludeWebsiteId)
            ->orderBy('id')
            ->first();

        if ($next) {
            $next->update(['is_primary' => true]);
        }
    }
}
