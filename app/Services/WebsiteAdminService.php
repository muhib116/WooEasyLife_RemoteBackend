<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteAdminService
{
    public function __construct(
        protected WebsiteBaseUrlNormalizer $baseUrlNormalizer
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

            if (array_key_exists('title', $data)) {
                $title = trim((string) ($data['title'] ?? ''));

                $update['title'] = $title !== '' ? $title : $website->domain;
            }

            if (array_key_exists('base_url', $data)) {
                $update['base_url'] = $this->resolveBaseUrl($data['base_url'], $website->domain);
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
