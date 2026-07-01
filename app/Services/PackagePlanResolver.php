<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Models\UserPackage;
use App\Support\PlanDisplayPresenter;
use Illuminate\Support\Carbon;

class PackagePlanResolver
{
    public function isCatalog(PackageHub|UserPackage $package): bool
    {
        if ($package instanceof UserPackage) {
            return ($package->plan_type ?? 'legacy') === 'catalog';
        }

        return $package->package_duration !== null
            || $package->order_rate_token !== null;
    }

    public function isLegacy(PackageHub|UserPackage $package): bool
    {
        return ! $this->isCatalog($package);
    }

    public function planType(PackageHub $package): string
    {
        return $this->isCatalog($package) ? 'catalog' : 'legacy';
    }

    public function expiresAt(PackageHub $package, ?Carbon $from = null): ?Carbon
    {
        if (! $this->isCatalog($package)) {
            return null;
        }

        $from = $from ?? now();

        return match ($package->package_duration) {
            'free_trial' => $from->copy()->addDays(max(1, (int) ($package->trial_days ?? 14))),
            '1_month' => $from->copy()->addMonth(),
            '5_month' => $from->copy()->addMonths(5),
            '1_year' => $from->copy()->addYear(),
            default => null,
        };
    }

    public function isFreeTrial(PackageHub $package): bool
    {
        return $package->package_duration === 'free_trial';
    }

    public function comparablePrice(PackageHub $package): float
    {
        if ($this->isCatalog($package)) {
            return (float) ($package->package_price ?? 0);
        }

        return (float) $package->per_order_rate;
    }

    /**
     * Negative => target is a higher tier (upgrade). Positive => lower tier (downgrade).
     */
    public function compareTier(PackageHub $current, PackageHub $target): int
    {
        if ($this->isFreeTrial($target) && ! $this->isFreeTrial($current)) {
            return 1;
        }

        if ($this->isFreeTrial($current) && ! $this->isFreeTrial($target)) {
            return -1;
        }

        $priceComparison = $this->comparablePrice($target) <=> $this->comparablePrice($current);
        if ($priceComparison !== 0) {
            return -$priceComparison;
        }

        return ((int) ($current->index ?? 0)) <=> ((int) ($target->index ?? 0));
    }

    /**
     * @return array<string, mixed>
     */
    public function toPlanPayload(PackageHub $plan): array
    {
        $catalog = $this->isCatalog($plan);

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'description' => $plan->description,
            'plan_type' => $catalog ? 'catalog' : 'legacy',
            'per_order_rate' => (float) $plan->per_order_rate,
            'package_price' => $catalog ? (float) ($plan->package_price ?? 0) : null,
            'order_rate_token' => $catalog ? (int) ($plan->order_rate_token ?? 0) : null,
            'package_duration' => $plan->package_duration,
            'trial_days' => $plan->trial_days,
            'requires_order_limit' => ! $catalog,
            'features' => $plan->features ?? [],
            'app_connect' => (bool) ($plan->app_connect ?? false),
            'total_website_connect' => $plan->total_website_connect,
            'is_special' => (bool) ($plan->is_special ?? false),
            'index' => (int) ($plan->index ?? 0),
        ];
    }

    /**
     * @param  iterable<int, PackageHub>  $plans
     * @return array<int, array<string, mixed>>
     */
    public function mapPlansForDisplay(iterable $plans): array
    {
        return collect($plans)
            ->map(fn (PackageHub $plan) => PlanDisplayPresenter::enrich($this->toPlanPayload($plan)))
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, PackageHub>  $plans
     * @return array<int, array<string, mixed>>
     */
    public function mapPlansForPluginApi(iterable $plans): array
    {
        return $this->mapPlansForDisplay($plans);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function enrichPlanForPluginApi(array $plan): array
    {
        return PlanDisplayPresenter::enrich($plan);
    }

    /**
     * @param  iterable<int, PackageHub>  $plans
     * @return array<int, array<string, mixed>>
     */
    public function mapPlansPayload(iterable $plans): array
    {
        return collect($plans)
            ->map(fn (PackageHub $plan) => $this->toPlanPayload($plan))
            ->values()
            ->all();
    }
}
