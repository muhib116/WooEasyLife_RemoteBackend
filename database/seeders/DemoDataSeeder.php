<?php

namespace Database\Seeders;

use App\Models\AccessToken;
use App\Models\MerchantEmployee;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\PackageUseHistory;
use App\Models\Role;
use App\Models\SmsBalance;
use App\Models\SmsRecharge;
use App\Models\User;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use App\Models\WhitelistedDomain;
use App\Services\LicenseProvisioningService;
use App\Services\MerchantEmployeeService;
use App\Services\PlanAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public const DEMO_PLUGIN_TOKEN = 'demo-plugin-token-wooeasylife';

    /** Local WordPress install used for plugin development. */
    public const LOCAL_WORDPRESS_URL = 'http://localhost:8081/wordpress/';

    /** Origin header the plugin sends from that WordPress site. */
    public const LOCAL_WORDPRESS_ORIGIN = 'http://localhost:8081';

    /** Normalized hostname stored on plans/licenses (matches Origin host). */
    public const LOCAL_WORDPRESS_DOMAIN = 'localhost';

    public function run(): void
    {
        $password = Hash::make('password');
        $admin = $this->seedAdminUsers($password);
        $plans = $this->seedPackageHubs($admin);
        $this->seedWhitelistedDomains();
        $this->seedMerchants($password, $plans, $admin);
    }

    private function seedAdminUsers(string $password): User
    {
        $admins = [
            ['email' => 'admin@example.com', 'name' => 'Admin 1', 'admin_role_id' => null],
            [
                'email' => 'entnasir23a@gmail.com',
                'name' => 'Admin 2',
                'admin_role_id' => Role::query()
                    ->where('slug', 'billing-clerk')
                    ->where('scope', 'platform')
                    ->value('id'),
            ],
        ];

        $primary = null;

        foreach ($admins as $adminData) {
            $user = User::withTrashed()->where('email', $adminData['email'])->first();
            if ($user?->trashed()) {
                $user->restore();
            }

            $admin = User::updateOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'phone' => '01700000099',
                    'role' => 'admin',
                    'admin_role_id' => $adminData['admin_role_id'],
                    'password' => $password,
                    'status' => true,
                ]
            );

            $primary ??= $admin;
        }

        return $primary ?? User::where('role', 'admin')->firstOrFail();
    }

    /**
     * @return array<string, PackageHub>
     */
    private function seedPackageHubs(User $admin): array
    {
        $definitions = [
            'Standard' => [
                'description' => 'Standard order-based subscription',
                'per_order_rate' => 1,
                'is_active' => true,
            ],
            'Premium' => [
                'description' => 'Premium order-based subscription',
                'per_order_rate' => 1.5,
                'is_active' => true,
            ],
        ];

        $plans = [];

        foreach ($definitions as $title => $data) {
            $plans[$title] = PackageHub::updateOrCreate(
                ['title' => $title],
                [
                    ...$data,
                    'created_by' => $admin->id,
                    'index' => count($plans) + 1,
                ]
            );
        }

        return $plans;
    }

    private function seedWhitelistedDomains(): void
    {
        foreach ([
            self::LOCAL_WORDPRESS_DOMAIN,
            'shop-two.example.com',
        ] as $domain) {
            WhitelistedDomain::updateOrCreate(
                ['domain' => $domain],
                [
                    'is_active' => true,
                    'notes' => $domain === self::LOCAL_WORDPRESS_DOMAIN
                        ? 'Local WordPress: ' . self::LOCAL_WORDPRESS_URL
                        : 'Seeded for local fraud-check testing',
                ]
            );
        }
    }

    /**
     * @param  array<string, PackageHub>  $plans
     */
    private function seedMerchants(string $password, array $plans, User $admin): void
    {
        $merchantConfigs = [
            [
                'email' => 'user@example.com',
                'name' => 'Test User',
                'phone' => '01700000000',
                'landing_page' => self::LOCAL_WORDPRESS_URL,
                'sites' => [
                    [
                        'domain' => self::LOCAL_WORDPRESS_DOMAIN,
                        'title' => 'Local WordPress (8081)',
                        'wordpress_url' => self::LOCAL_WORDPRESS_URL,
                        'plan' => 'Standard',
                        'order_limit' => 100,
                        'remaining_order' => 75,
                        'handled' => 25,
                        'sms_balance' => 150,
                        'subscription_expires_at' => now()->addDays(3),
                        'license_expires_at' => now()->addDays(5),
                        'demo_token' => self::DEMO_PLUGIN_TOKEN,
                        'payment_pending' => true,
                    ],
                ],
                'employees' => [
                    ['name' => 'Shop Manager', 'email' => 'manager@localhost', 'role' => 'merchant-manager', 'portal_access' => true],
                ],
            ],
            [
                'email' => 'merchant2@example.com',
                'name' => 'Demo Merchant Two',
                'phone' => '01700000002',
                'sites' => [
                    [
                        'domain' => 'shop-two.example.com',
                        'plan' => 'Premium',
                        'order_limit' => 200,
                        'remaining_order' => 15,
                        'handled' => 20,
                        'sms_balance' => 80,
                        'subscription_expires_at' => now()->addDays(2),
                        'license_expires_at' => now()->addDays(10),
                        'demo_token' => null,
                        'payment_pending' => false,
                    ],
                ],
                'employees' => [
                    ['name' => 'Site Operator', 'email' => 'operator@shop-two.example.com', 'role' => 'merchant-operator'],
                ],
            ],
        ];

        foreach ($merchantConfigs as $config) {
            $merchant = User::withTrashed()->where('email', $config['email'])->first();
            if ($merchant?->trashed()) {
                $merchant->restore();
            }

            $merchant = User::updateOrCreate(
                ['email' => $config['email']],
                [
                    'name' => $config['name'],
                    'phone' => $config['phone'],
                    'whatsapp_phone' => $config['phone'],
                    'facebook_page_link' => $config['landing_page'] ?? null,
                    'role' => 'user',
                    'password' => $password,
                    'status' => true,
                ]
            );

            UserBusiness::updateOrCreate(
                [
                    'user_id' => $merchant->id,
                    'domain' => $config['sites'][0]['domain'],
                ],
                [
                    'title' => $config['name'] . ' Business',
                    'description' => isset($config['landing_page'])
                        ? 'WordPress: ' . $config['landing_page']
                        : 'Seeded business profile',
                    'status' => true,
                ]
            );

            foreach ($config['sites'] as $site) {
                $this->seedMerchantSite($merchant, $admin, $plans, $site);
            }

            foreach ($config['employees'] as $employee) {
                $this->seedEmployee($merchant, $employee, $config['sites'][0]['domain']);
            }
        }
    }

    /**
     * @param  array<string, PackageHub>  $plans
     * @param  array<string, mixed>  $site
     */
    private function seedMerchantSite(User $merchant, User $admin, array $plans, array $site): void
    {
        $plan = $plans[$site['plan']] ?? reset($plans);
        $domain = $site['domain'];

        $website = Website::updateOrCreate(
            ['user_id' => $merchant->id, 'domain' => $domain],
            [
                'title' => $site['title'] ?? $domain,
                'status' => true,
                'is_primary' => true,
            ]
        );

        $userPackage = UserPackage::query()
            ->where('user_id', $merchant->id)
            ->where('domain', $domain)
            ->first();

        if (! $userPackage) {
            $userPackage = app(PlanAssignmentService::class)->assign($merchant, $plan, [
                'domain' => $domain,
                'limit' => $site['order_limit'],
                'transaction_method' => 'Bkash',
                'note' => isset($site['wordpress_url'])
                    ? 'Seeded for ' . $site['wordpress_url']
                    : 'Seeded demo subscription',
            ]);
        }

        $userPackage->update([
            'website_id' => $website->id,
            'remaining_order' => $site['remaining_order'],
            'total_order_handled' => $site['handled'],
            'total_order_can_handle' => $site['order_limit'],
            'total_cost' => $plan->per_order_rate * $site['order_limit'],
            'expires_at' => $site['subscription_expires_at'] ?? null,
            'is_active' => true,
            'updated_by' => $admin->id,
        ]);

        $this->seedLicense(
            $merchant,
            $domain,
            $userPackage,
            $website,
            $site['demo_token'] ?? null,
            $site['license_expires_at'] ?? null
        );
        $this->seedSmsData($merchant, $admin, $domain, (float) $site['sms_balance']);

        if ($site['payment_pending'] ?? false) {
            PackagePaymentRequest::updateOrCreate(
                [
                    'user_id' => $merchant->id,
                    'domain' => $domain,
                    'status' => 'pending',
                    'transaction_id' => 'SEED-PENDING-' . $merchant->id,
                ],
                [
                    'package_hub_id' => $plan->id,
                    'website_id' => $website->id,
                    'user_package_id' => $userPackage->id,
                    'order_limit' => 50,
                    'total_amount' => 50,
                    'transaction_charge' => 0,
                    'transaction_method' => 'Bkash',
                    'account_number' => '01700000000',
                    'note' => 'Seeded pending payment request',
                    'created_by' => $admin->id,
                ]
            );
        }

        PackageUseHistory::updateOrCreate(
            [
                'user_id' => $merchant->id,
                'user_package_id' => $userPackage->id,
                'order_count' => 5,
            ],
            [
                'use_details' => json_encode(['source' => 'seeder', 'note' => 'Sample usage']),
                'cost' => 5 * $plan->per_order_rate,
                'total_order_handled' => min(5, $site['handled']),
                'remaining_order' => max($site['remaining_order'], 0),
                'created_by' => $admin->id,
            ]
        );
    }

    private function seedLicense(
        User $merchant,
        string $domain,
        UserPackage $userPackage,
        Website $website,
        ?string $plainToken,
        $licenseExpiresAt = null
    ): void {
        if ($plainToken) {
            $existingByToken = AccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $merchant->id)
                ->where('token', hash('sha256', $plainToken))
                ->first();

            if ($existingByToken) {
                $existingByToken->update([
                    'domain' => $domain,
                    'website_id' => $website->id,
                    'user_package_id' => $userPackage->id,
                    'status' => true,
                    'expires_at' => $licenseExpiresAt,
                    'access_key' => Crypt::encryptString($plainToken),
                ]);

                return;
            }
        }

        $existing = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $merchant->id)
            ->where('domain', $domain)
            ->first();

        if ($existing) {
            $existing->update([
                'website_id' => $website->id,
                'user_package_id' => $userPackage->id,
                'status' => true,
                'expires_at' => $licenseExpiresAt,
            ]);

            if ($plainToken && empty($existing->access_key)) {
                $existing->update([
                    'access_key' => Crypt::encryptString($plainToken),
                    'token' => hash('sha256', $plainToken),
                ]);
            }

            return;
        }

        if ($plainToken) {
            AccessToken::unguarded(function () use ($merchant, $domain, $userPackage, $website, $plainToken, $licenseExpiresAt) {
                AccessToken::create([
                    'tokenable_type' => User::class,
                    'tokenable_id' => $merchant->id,
                    'name' => $merchant->name . ' License',
                    'title' => $merchant->name . ' License',
                    'token' => hash('sha256', $plainToken),
                    'access_key' => Crypt::encryptString($plainToken),
                    'domain' => $domain,
                    'website_id' => $website->id,
                    'user_package_id' => $userPackage->id,
                    'status' => true,
                    'expires_at' => $licenseExpiresAt,
                    'abilities' => ['*'],
                ]);
            });

            return;
        }

        app(LicenseProvisioningService::class)->create(
            $merchant,
            $domain,
            [
                'title' => $merchant->name . ' License',
                'user_package_id' => $userPackage->id,
            ],
            requireUserPackage: true,
            requireDns: false
        );
    }

    private function seedSmsData(User $merchant, User $admin, string $domain, float $balance): void
    {
        SmsRecharge::updateOrCreate(
            [
                'user_id' => $merchant->id,
                'domain' => $domain,
                'transaction_id' => 'SEED-SMS-APPROVED-' . $merchant->id,
            ],
            [
                'total_amount' => $balance + 10,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'account_number' => $merchant->phone,
                'status' => 'approved',
                'created_by' => $admin->id,
            ]
        );

        SmsRecharge::updateOrCreate(
            [
                'user_id' => $merchant->id,
                'domain' => $domain,
                'transaction_id' => 'SEED-SMS-PENDING-' . $merchant->id,
            ],
            [
                'total_amount' => 100,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'account_number' => $merchant->phone,
                'status' => 'pending',
                'created_by' => $admin->id,
            ]
        );

        if (SmsBalance::query()->where('user_id', $merchant->id)->where('domain', $domain)->exists()) {
            return;
        }

        SmsBalance::create([
            'user_id' => $merchant->id,
            'type' => 'in',
            'amount' => $balance,
            'domain' => $domain,
            'note' => 'Seeded SMS balance',
            'created_by' => $admin->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $employee
     */
    private function seedEmployee(User $merchant, array $employee, string $defaultDomain): void
    {
        $role = Role::query()
            ->where('slug', $employee['role'])
            ->where('scope', 'merchant')
            ->first();

        if (! $role) {
            return;
        }

        $website = Website::query()
            ->where('user_id', $merchant->id)
            ->where('domain', $defaultDomain)
            ->first();

        $existing = MerchantEmployee::query()
            ->where('merchant_user_id', $merchant->id)
            ->where('email', $employee['email'])
            ->first();

        if ($existing) {
            $existing->update([
                'role_id' => $role->id,
                'website_id' => $website?->id,
                'name' => $employee['name'],
                'status' => true,
            ]);

            if ($employee['portal_access'] ?? false) {
                app(MerchantEmployeeService::class)->update($existing, $merchant, [
                    'name' => $employee['name'],
                    'email' => $employee['email'],
                    'phone' => $merchant->phone,
                    'role_id' => $role->id,
                    'website_id' => $website?->id,
                    'status' => true,
                    'grant_portal_access' => true,
                    'portal_password' => 'password',
                ]);
            }

            return;
        }

        $created = app(MerchantEmployeeService::class)->create($merchant, [
            'name' => $employee['name'],
            'email' => $employee['email'],
            'phone' => $merchant->phone,
            'role_id' => $role->id,
            'website_id' => $website?->id,
            'status' => true,
            'notes' => 'Seeded team member',
            'grant_portal_access' => (bool) ($employee['portal_access'] ?? false),
            'portal_password' => ($employee['portal_access'] ?? false) ? 'password' : null,
        ]);
    }
}
