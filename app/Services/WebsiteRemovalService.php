<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\MerchantEmployee;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\SmsBalance;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteRemovalService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * Remove a merchant website and all domain-scoped subscription data.
     *
     * @return array{packages_removed: int, licenses_removed: int, payments_cancelled: int}
     */
    public function remove(User $user, string $domain): array
    {
        $normalizedDomain = $this->domainNormalizer->normalize($domain);
        if (! $normalizedDomain) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        $stats = [
            'packages_removed' => 0,
            'licenses_removed' => 0,
            'payments_cancelled' => 0,
        ];

        DB::transaction(function () use ($user, $normalizedDomain, &$stats) {
            $website = Website::query()
                ->where('user_id', $user->id)
                ->where('domain', $normalizedDomain)
                ->first();

            $packages = UserPackage::query()
                ->where('user_id', $user->id)
                ->get()
                ->filter(fn (UserPackage $package) => $this->domainNormalizer->matches(
                    $package->domain,
                    $normalizedDomain
                ));

            foreach ($packages as $package) {
                $package->update(['updated_by' => Auth::id()]);
                $package->delete();
                $stats['packages_removed']++;
            }

            $tokens = AccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->get()
                ->filter(fn (AccessToken $token) => $this->domainNormalizer->matches(
                    $token->domain,
                    $normalizedDomain
                ));

            foreach ($tokens as $token) {
                $token->delete();
                $stats['licenses_removed']++;
            }

            $pendingPayments = PackagePaymentRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->get()
                ->filter(fn (PackagePaymentRequest $request) => $this->domainNormalizer->matches(
                    $request->domain,
                    $normalizedDomain
                ));

            foreach ($pendingPayments as $paymentRequest) {
                $paymentRequest->update([
                    'status' => 'cancelled',
                    'updated_by' => Auth::id(),
                    'note' => trim(($paymentRequest->note ?? '').' [Cancelled: website removed by admin]'),
                ]);
                $stats['payments_cancelled']++;
            }

            UserBusiness::query()
                ->where('user_id', $user->id)
                ->get()
                ->filter(fn (UserBusiness $business) => $this->domainNormalizer->matches(
                    $business->domain,
                    $normalizedDomain
                ))
                ->each(fn (UserBusiness $business) => $business->delete());

            SmsBalance::query()
                ->where('user_id', $user->id)
                ->get()
                ->filter(fn (SmsBalance $balance) => $this->domainNormalizer->matches(
                    $balance->domain,
                    $normalizedDomain
                ))
                ->each(fn (SmsBalance $balance) => $balance->delete());

            if ($website) {
                MerchantEmployee::query()
                    ->where('merchant_user_id', $user->id)
                    ->where('website_id', $website->id)
                    ->update(['website_id' => null]);

                DB::table('merchant_employee_website')
                    ->where('website_id', $website->id)
                    ->delete();

                $wasPrimary = (bool) $website->is_primary;
                $website->delete();

                if ($wasPrimary) {
                    $this->promoteNextPrimaryWebsite($user);
                }
            }
        });

        return $stats;
    }

    private function promoteNextPrimaryWebsite(User $user): void
    {
        $next = Website::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->first();

        if (! $next) {
            return;
        }

        Website::query()
            ->where('user_id', $user->id)
            ->update(['is_primary' => false]);

        $next->update(['is_primary' => true]);
    }
}
