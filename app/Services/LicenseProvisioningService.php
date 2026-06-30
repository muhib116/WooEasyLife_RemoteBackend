<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseProvisioningService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer,
        protected WebsiteSyncService $websiteSync,
        protected DomainAvailabilityService $domainAvailability
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{access_token: AccessToken, plain_text_token: string}
     */
    public function create(
        User $user,
        string $domain,
        array $attributes = [],
        bool $requireUserPackage = true,
        bool $requireDns = true
    ): array {
        $normalizedDomain = $this->domainNormalizer->normalize($domain);
        if (! $normalizedDomain) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        if ($requireDns && ! $this->domainNormalizer->hasDnsARecord($normalizedDomain)) {
            $isLocalDevHost = in_array($normalizedDomain, ['localhost', '127.0.0.1'], true);

            if (! (app()->environment('local') && $isLocalDevHost)) {
                throw ValidationException::withMessages([
                    'domain' => 'Invalid domain',
                ]);
            }
        }

        $this->domainAvailability->assertAvailableForUser($user, $normalizedDomain, forAdmin: true);

        $userPackage = $this->resolveUserPackage($user, $normalizedDomain, $attributes, $requireUserPackage);

        return DB::transaction(function () use ($user, $normalizedDomain, $attributes, $userPackage) {
            $tokenLength = AccessToken::where('tokenable_id', $user->id)->count();
            $title = $attributes['title'] ?? $user->name . '(' . $user->id . ') - t(' . $tokenLength . ')';

            $token = $user->createToken($title, ['*']);
            $plainTextToken = $token->plainTextToken;
            $accessToken = AccessToken::findOrFail($token->accessToken->id);

            $website = $this->websiteSync->resolveForUser($user, $normalizedDomain, $title);

            $accessToken->update([
                'access_key' => Crypt::encryptString($plainTextToken),
                'title' => $title,
                'description' => $attributes['description'] ?? null,
                'domain' => $normalizedDomain,
                'website_id' => $website?->id,
                'user_package_id' => $userPackage?->id,
                'status' => array_key_exists('status', $attributes) ? (bool) $attributes['status'] : true,
                'expires_at' => isset($attributes['expires_at']) && $attributes['expires_at']
                    ? Carbon::parse($attributes['expires_at'])
                    : null,
            ]);

            if ($userPackage && ! $userPackage->website_id && $website) {
                UserPackage::query()
                    ->whereKey($userPackage->id)
                    ->update(['website_id' => $website->id]);
            }

            return [
                'access_token' => $accessToken->fresh(),
                'plain_text_token' => $plainTextToken,
                'user_package' => $userPackage,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AccessToken $accessToken, array $attributes): AccessToken
    {
        $domain = isset($attributes['domain']) && $attributes['domain']
            ? $this->domainNormalizer->normalize($attributes['domain']) ?? $attributes['domain']
            : $accessToken->domain;

        if ($domain && $accessToken->tokenable_type === User::class && $accessToken->tokenable_id) {
            $user = User::query()->find($accessToken->tokenable_id);
            if ($user && ! $this->domainNormalizer->matches($domain, $accessToken->domain)) {
                $this->domainAvailability->assertAvailableForUser($user, $domain, forAdmin: true);
            }
        }

        $website = null;
        if ($accessToken->tokenable_type === User::class && $accessToken->tokenable_id && $domain) {
            $user = User::query()->find($accessToken->tokenable_id);
            if ($user) {
                $website = $this->websiteSync->resolveForUser($user, $domain, $attributes['title'] ?? $accessToken->title);
            }
        }

        $accessToken->update([
            'title' => $attributes['title'] ?? $accessToken->title,
            'description' => $attributes['description'] ?? $accessToken->description,
            'domain' => $domain,
            'website_id' => $website?->id ?? $accessToken->website_id,
            'status' => array_key_exists('status', $attributes) ? (bool) $attributes['status'] : $accessToken->status,
            'expires_at' => array_key_exists('expires_at', $attributes)
                ? ($attributes['expires_at'] ? Carbon::parse($attributes['expires_at']) : null)
                : $accessToken->expires_at,
        ]);

        return $accessToken->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveUserPackage(
        User $user,
        string $normalizedDomain,
        array $attributes,
        bool $requireUserPackage
    ): ?UserPackage {
        if (! empty($attributes['user_package_id'])) {
            $userPackage = UserPackage::query()
                ->where('user_id', $user->id)
                ->find($attributes['user_package_id']);

            if (! $userPackage) {
                throw ValidationException::withMessages([
                    'user_package_id' => 'Website plan not found for this merchant.',
                ]);
            }

            $packageDomain = $this->domainNormalizer->normalize($userPackage->domain);
            if ($packageDomain !== $normalizedDomain) {
                throw ValidationException::withMessages([
                    'domain' => 'License domain must match the selected website plan.',
                ]);
            }

            return $userPackage;
        }

        $userPackage = UserPackage::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->first(fn (UserPackage $package) => $this->domainNormalizer->normalize($package->domain) === $normalizedDomain);

        if ($requireUserPackage && ! $userPackage) {
            throw ValidationException::withMessages([
                'domain' => 'Assign a subscription plan for this domain before generating a license.',
            ]);
        }

        return $userPackage;
    }
}
