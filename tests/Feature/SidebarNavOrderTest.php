<?php

use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminSidebarNavOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createSidebarNavSuperAdmin(): User
{
    return User::create([
        'name' => 'Sidebar Super Admin',
        'email' => 'sidebar-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

function createSidebarNavLimitedAdmin(): User
{
    $roleId = Role::query()
        ->where('slug', 'billing-clerk')
        ->where('scope', 'platform')
        ->value('id');

    return User::create([
        'name' => 'Sidebar Clerk',
        'email' => 'sidebar-clerk-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'admin_role_id' => $roleId,
        'status' => true,
    ]);
}

it('shares admin sidebar nav order with platform admins', function () {
    $admin = createSidebarNavSuperAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('admin_sidebar_nav_order.sections'));
});

it('allows roles.manage admins to save sidebar order', function () {
    $admin = createSidebarNavSuperAdmin();

    $payload = [
        'sections' => ['Platform', 'Merchants', 'Overview', 'Marketing', 'Analytics', 'System'],
        'items' => [
            'Platform' => [
                'Blog Posts',
                'Settings',
                'Plugin Versions',
                'Plans & Billing',
                'Tutorials',
                'Media Library',
                'Subscription Alerts',
            ],
        ],
        'children' => [
            'Plans & Billing' => [
                'Customer Notices',
                'Pricing Plans',
                'Landing Orders',
                'Payment Requests',
            ],
        ],
    ];

    $this->actingAs($admin)
        ->putJson(route('sidebarNavOrder.update'), $payload)
        ->assertOk()
        ->assertJsonPath('order.sections.0', 'Platform')
        ->assertJsonPath('order.items.Platform.0', 'Blog Posts')
        ->assertJsonPath('order.children.Plans & Billing.0', 'Customer Notices');

    $stored = PlatformSetting::query()
        ->where('key', AdminSidebarNavOrder::SETTING_KEY)
        ->value('value');

    expect($stored['sections'][0] ?? null)->toBe('Platform')
        ->and($stored['items']['Platform'][0] ?? null)->toBe('Blog Posts');
});

it('strips unknown titles and appends missing catalog entries', function () {
    $admin = createSidebarNavSuperAdmin();

    $response = $this->actingAs($admin)
        ->putJson(route('sidebarNavOrder.update'), [
            'sections' => ['Platform', 'Not A Real Section', 'Merchants'],
            'items' => [
                'Platform' => ['Blog Posts', 'Fake Item', 'Settings'],
            ],
            'children' => [
                'Plans & Billing' => ['Fake Child', 'Pricing Plans'],
            ],
        ])
        ->assertOk();

    $order = $response->json('order');

    expect($order['sections'])->not->toContain('Not A Real Section')
        ->and($order['sections'])->toContain('Overview')
        ->and($order['items']['Platform'])->not->toContain('Fake Item')
        ->and($order['items']['Platform'])->toContain('Plugin Versions')
        ->and($order['items']['Platform'])->toContain('Blog Posts')
        ->and($order['items']['Platform'])->toContain('Settings')
        ->and(array_search('Blog Posts', $order['items']['Platform'], true))
        ->toBeLessThan(array_search('Settings', $order['items']['Platform'], true))
        ->and($order['children']['Plans & Billing'])->not->toContain('Fake Child')
        ->and($order['children']['Plans & Billing'][0])->toBe('Pricing Plans');
});

it('preserves hidden platform items when a partial item list is saved', function () {
    $admin = createSidebarNavSuperAdmin();
    $service = app(AdminSidebarNavOrder::class);

    $service->update([
        'items' => [
            'Platform' => [
                'Blog Posts',
                'Plugin Versions',
                'Settings',
                'Plans & Billing',
                'Tutorials',
                'Media Library',
                'Subscription Alerts',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->putJson(route('sidebarNavOrder.update'), [
            'items' => [
                'Platform' => ['Settings', 'Blog Posts'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('order.items.Platform', [
            'Settings',
            'Plugin Versions',
            'Blog Posts',
            'Plans & Billing',
            'Tutorials',
            'Media Library',
            'Subscription Alerts',
        ]);
});

it('rejects oversized sidebar order payloads', function () {
    $admin = createSidebarNavSuperAdmin();

    $this->actingAs($admin)
        ->putJson(route('sidebarNavOrder.update'), [
            'sections' => array_map(
                fn (int $i) => "Section {$i}",
                range(1, 40),
            ),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sections']);
});

it('requires authentication for sidebar order updates', function () {
    $this->putJson(route('sidebarNavOrder.update'), [
        'sections' => ['Platform'],
    ])->assertUnauthorized();
});

it('forbids sidebar order updates without roles.manage', function () {
    $admin = createSidebarNavLimitedAdmin();

    $this->actingAs($admin)
        ->putJson(route('sidebarNavOrder.update'), [
            'sections' => ['Platform', 'Overview'],
        ])
        ->assertForbidden();
});

it('returns current sidebar order for authorized admins', function () {
    $admin = createSidebarNavSuperAdmin();
    $service = app(AdminSidebarNavOrder::class);

    $service->update([
        'sections' => ['System', 'Overview'],
    ]);

    $this->actingAs($admin)
        ->getJson(route('sidebarNavOrder.show'))
        ->assertOk()
        ->assertJsonPath('order.sections.0', 'System');
});
