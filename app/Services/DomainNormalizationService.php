<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\SmsBalance;
use App\Models\SmsRecharge;
use App\Models\User;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class DomainNormalizationService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function normalizeUser(User $user, bool $dryRun = false): array
    {
        $stats = [
            'packages_updated' => 0,
            'tokens_updated' => 0,
            'businesses_updated' => 0,
            'sms_balances_updated' => 0,
            'sms_recharges_updated' => 0,
            'websites_updated' => 0,
            'websites_merged' => 0,
            'skipped_invalid' => 0,
        ];

        $apply = function () use ($user, $dryRun, &$stats) {
            $stats['packages_updated'] += $this->normalizeModelDomains(
                UserPackage::query()->where('user_id', $user->id),
                $dryRun,
                $stats
            );
            $stats['tokens_updated'] += $this->normalizeModelDomains(
                AccessToken::query()
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $user->id),
                $dryRun,
                $stats
            );
            $stats['businesses_updated'] += $this->normalizeModelDomains(
                UserBusiness::query()->where('user_id', $user->id),
                $dryRun,
                $stats
            );
            $stats['sms_balances_updated'] += $this->normalizeModelDomains(
                SmsBalance::query()->where('user_id', $user->id),
                $dryRun,
                $stats
            );
            $stats['sms_recharges_updated'] += $this->normalizeModelDomains(
                SmsRecharge::query()->where('user_id', $user->id),
                $dryRun,
                $stats
            );
            $stats = $this->mergeWebsiteStats(
                $stats,
                $this->normalizeWebsitesForUser($user, $dryRun, $stats)
            );
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        return $stats;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function normalizeModelDomains($query, bool $dryRun, array &$stats): int
    {
        $updated = 0;

        $query->whereNotNull('domain')->chunkById(200, function ($rows) use ($dryRun, &$updated, &$stats) {
            foreach ($rows as $row) {
                $normalized = $this->domainNormalizer->normalize($row->domain);

                if (! $normalized) {
                    $stats['skipped_invalid']++;

                    continue;
                }

                if ($normalized === $row->domain) {
                    continue;
                }

                $updated++;

                if (! $dryRun) {
                    $row->update(['domain' => $normalized]);
                }
            }
        });

        return $updated;
    }

    /**
     * @return array<string, int>
     */
    private function normalizeWebsitesForUser(User $user, bool $dryRun, array &$stats): array
    {
        $websiteStats = [
            'websites_updated' => 0,
            'websites_merged' => 0,
        ];

        Website::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->chunkById(100, function ($websites) use ($user, $dryRun, &$websiteStats, &$stats) {
                foreach ($websites as $website) {
                    $normalized = $this->domainNormalizer->normalize($website->domain);

                    if (! $normalized) {
                        $stats['skipped_invalid']++;

                        continue;
                    }

                    if ($normalized === $website->domain) {
                        continue;
                    }

                    $existing = Website::query()
                        ->where('user_id', $user->id)
                        ->where('domain', $normalized)
                        ->where('id', '!=', $website->id)
                        ->first();

                    if ($existing) {
                        $websiteStats['websites_merged']++;

                        if (! $dryRun) {
                            UserPackage::query()
                                ->where('website_id', $website->id)
                                ->update(['website_id' => $existing->id]);
                            AccessToken::query()
                                ->where('website_id', $website->id)
                                ->update(['website_id' => $existing->id]);
                            $website->delete();
                        }

                        continue;
                    }

                    $websiteStats['websites_updated']++;

                    if (! $dryRun) {
                        $website->update(['domain' => $normalized]);
                    }
                }
            });

        return $websiteStats;
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $websiteStats
     * @return array<string, int>
     */
    private function mergeWebsiteStats(array $stats, array $websiteStats): array
    {
        $stats['websites_updated'] += $websiteStats['websites_updated'];
        $stats['websites_merged'] += $websiteStats['websites_merged'];

        return $stats;
    }
}
