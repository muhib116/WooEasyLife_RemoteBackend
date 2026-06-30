<?php

namespace Database\Seeders;

use App\Models\PackageHub;
use App\Models\User;
use App\Support\PackageCatalogFeatures;
use Illuminate\Database\Seeder;

/**
 * Seeds catalog-format packages (duration, tokens, pricing, features).
 *
 * Safe to re-run: uses updateOrCreate by title.
 * Does not modify existing plans outside the catalog definitions below.
 *
 * Usage:
 *   php artisan db:seed --class=PackageCatalogSeeder
 */
class PackageCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        $definitions = [
            [
                'title' => 'Free Trial',
                'description' => '<p>Try core WooEasyLife features before upgrading.</p>',
                'package_duration' => 'free_trial',
                'trial_days' => 14,
                'order_rate_token' => 100,
                'package_price' => 0,
                'app_connect' => false,
                'total_website_connect' => null,
                'is_special' => false,
                'is_active' => true,
                'features' => PackageCatalogFeatures::trialMap(),
            ],
            [
                'title' => 'Starter – 1 Month',
                'description' => '<p>Essential plugin tools for a single growing store.</p>',
                'package_duration' => '1_month',
                'trial_days' => null,
                'order_rate_token' => 1000,
                'package_price' => 999,
                'app_connect' => false,
                'total_website_connect' => null,
                'is_special' => false,
                'is_active' => true,
                'features' => PackageCatalogFeatures::starterMap(),
            ],
            [
                'title' => 'Growth – 1 Month',
                'description' => '<p>Advanced automation with App Connect for two stores.</p>',
                'package_duration' => '1_month',
                'trial_days' => null,
                'order_rate_token' => 3000,
                'package_price' => 2499,
                'app_connect' => true,
                'total_website_connect' => 2,
                'is_special' => false,
                'is_active' => true,
                'features' => PackageCatalogFeatures::map(
                    default: true,
                    disabledKeys: [
                        'ai_image_to_order_create',
                        'courier_webhook_integrations',
                        'centralized_notifications',
                    ],
                ),
            ],
            [
                'title' => 'Pro Plus – 1 Month',
                'description' => '<p>Full plugin + app feature set for serious merchants.</p>',
                'package_duration' => '1_month',
                'trial_days' => null,
                'order_rate_token' => 10000,
                'package_price' => 4999,
                'app_connect' => true,
                'total_website_connect' => 3,
                'is_special' => true,
                'is_active' => true,
                'features' => PackageCatalogFeatures::map(),
            ],
            [
                'title' => 'Pro Plus – 1 Year',
                'description' => '<p>Best value annual plan with unlimited store connections.</p>',
                'package_duration' => '1_year',
                'trial_days' => null,
                'order_rate_token' => 120000,
                'package_price' => 49999,
                'app_connect' => true,
                'total_website_connect' => null,
                'is_special' => true,
                'is_active' => true,
                'features' => PackageCatalogFeatures::map(),
            ],
        ];

        $baseIndex = (int) PackageHub::withTrashed()->max('index');

        foreach ($definitions as $offset => $plan) {
            PackageHub::updateOrCreate(
                ['title' => $plan['title']],
                [
                    'description' => $plan['description'],
                    'per_order_rate' => 0,
                    'package_duration' => $plan['package_duration'],
                    'trial_days' => $plan['trial_days'],
                    'order_rate_token' => $plan['order_rate_token'],
                    'package_price' => $plan['package_price'],
                    'app_connect' => $plan['app_connect'],
                    'total_website_connect' => $plan['total_website_connect'],
                    'features' => $plan['features'],
                    'is_active' => $plan['is_active'],
                    'is_special' => $plan['is_special'],
                    'created_by' => $createdBy,
                    'index' => $baseIndex + $offset + 1,
                ],
            );
        }

        $this->command?->info('Catalog packages seeded: ' . count($definitions));
    }
}
