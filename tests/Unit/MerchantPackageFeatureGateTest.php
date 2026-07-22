<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\MerchantPackageFeatureGate;
use App\Support\PackageCatalogFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantPackageFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(User $user, string $domain = 'shop.example.com'): Request
    {
        $plainToken = 'gate-token-'.bin2hex(random_bytes(8));

        AccessToken::unguarded(function () use ($user, $plainToken, $domain) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Gate Token',
                'token' => hash('sha256', $plainToken),
                'domain' => $domain,
                'status' => true,
            ]);
        });

        $request = Request::create('/api/steadfast/parcel-notes', 'POST');
        $request->headers->set('Authorization', 'Bearer '.$plainToken);

        return $request;
    }

    public function test_catalog_package_respects_feature_flag(): void
    {
        $user = User::create([
            'name' => 'Gate Merchant',
            'email' => 'gate-'.uniqid().'@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Gate Plan',
            'description' => 'Test',
            'per_order_rate' => 0,
            'package_price' => 999,
            'order_rate_token' => 100,
            'package_duration' => '1_month',
            'is_active' => true,
            'index' => 1,
            'features' => PackageCatalogFeatures::normalize([
                'parcel_note_history' => true,
            ]),
        ]);

        UserPackage::create([
            'title' => $plan->title,
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
            'features' => PackageCatalogFeatures::normalize([
                'parcel_note_history' => true,
            ]),
        ]);

        $gate = app(MerchantPackageFeatureGate::class);
        $request = $this->requestFor($user);

        $this->assertTrue($gate->hasFromRequest($request, 'parcel_note_history'));
        $this->assertFalse($gate->hasFromRequest($request, 'ai_intelligence'));
    }

    public function test_missing_package_denies_feature(): void
    {
        $user = User::create([
            'name' => 'No Package',
            'email' => 'nopkg-'.uniqid().'@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $gate = app(MerchantPackageFeatureGate::class);

        $this->assertFalse(
            $gate->hasFromRequest($this->requestFor($user), 'parcel_note_history')
        );
    }
}
