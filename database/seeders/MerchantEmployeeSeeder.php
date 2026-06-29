<?php

namespace Database\Seeders;

use App\Models\MerchantEmployee;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Services\MerchantEmployeeService;
use Illuminate\Database\Seeder;

/**
 * Seeds demo merchant employees with varied website assignments.
 *
 * Usage:
 *   php artisan db:seed --class=MerchantEmployeeSeeder
 *
 * Safe to re-run: matches employees by merchant + phone and updates in place.
 */
class MerchantEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employeeService = app(MerchantEmployeeService::class);

        $merchants = User::query()
            ->where('role', 'user')
            ->orderBy('id')
            ->get();

        if ($merchants->isEmpty()) {
            $this->command?->warn('No merchant accounts found. Run DemoDataSeeder first.');

            return;
        }

        $total = 0;

        foreach ($merchants as $merchant) {
            $total += $this->seedEmployeesForMerchant($merchant, $employeeService);
        }

        $this->command?->info("Seeded {$total} merchant employee record(s).");
    }

    private function seedEmployeesForMerchant(User $merchant, MerchantEmployeeService $employeeService): int
    {
        $websites = $employeeService
            ->assignableWebsitesForMerchant($merchant)
            ->values();

        if ($websites->isEmpty()) {
            $this->command?->warn("Skipping employees for merchant #{$merchant->id}: no websites found.");

            return 0;
        }

        $primaryWebsite = $websites->firstWhere('is_primary', true) ?? $websites->first();
        $secondaryWebsite = $websites->first(
            fn (Website $website) => (int) $website->id !== (int) $primaryWebsite?->id
        );

        $definitions = [
            [
                'phone' => '01711110001',
                'name' => 'Dale Lee',
                'email' => 'dale.lee@example.com',
                'address' => 'Dhaka, Bangladesh',
                'role_slug' => 'merchant-viewer',
                'website_scope' => 'primary',
                'status' => true,
                'notes' => 'Assigned to the primary store website.',
            ],
            [
                'phone' => '01711110002',
                'name' => 'Roanna Frazier',
                'email' => 'roanna.frazier@example.com',
                'address' => 'Chattogram, Bangladesh',
                'role_slug' => 'merchant-operator',
                'website_scope' => 'all',
                'status' => true,
                'notes' => 'Can work across all merchant websites.',
            ],
            [
                'phone' => '01711110003',
                'name' => 'Aphrodite Ellison',
                'email' => 'aphrodite.ellison@example.com',
                'address' => 'Sylhet, Bangladesh',
                'role_slug' => 'merchant-manager',
                'website_scope' => $secondaryWebsite ? 'multiple' : 'primary',
                'status' => false,
                'notes' => 'Inactive demo employee for status testing.',
            ],
        ];

        $seeded = 0;

        foreach ($definitions as $definition) {
            $role = Role::query()
                ->where('slug', $definition['role_slug'])
                ->where('scope', 'merchant')
                ->first();

            if (! $role) {
                $this->command?->warn("Merchant role [{$definition['role_slug']}] not found. Run RolePermissionSeeder first.");

                continue;
            }

            $payload = [
                'name' => $definition['name'],
                'phone' => $definition['phone'],
                'email' => $definition['email'],
                'address' => $definition['address'],
                'role_id' => $role->id,
                'website_ids' => $this->resolveWebsiteIds(
                    $definition['website_scope'],
                    $primaryWebsite,
                    $secondaryWebsite
                ),
                'status' => $definition['status'],
                'notes' => $definition['notes'],
            ];

            $employee = MerchantEmployee::query()
                ->where('merchant_user_id', $merchant->id)
                ->where('phone', $definition['phone'])
                ->first();

            if ($employee) {
                $employeeService->update($employee, $merchant, $payload);
            } else {
                $employeeService->create($merchant, $payload);
            }

            $seeded++;
        }

        return $seeded;
    }

    /**
     * @return array<int, int>
     */
    private function resolveWebsiteIds(
        string $scope,
        ?Website $primaryWebsite,
        ?Website $secondaryWebsite
    ): array {
        return match ($scope) {
            'all' => [],
            'primary' => $primaryWebsite ? [(int) $primaryWebsite->id] : [],
            'multiple' => collect([$primaryWebsite, $secondaryWebsite])
                ->filter()
                ->map(fn (Website $website) => (int) $website->id)
                ->unique()
                ->values()
                ->all(),
            default => [],
        };
    }
}
