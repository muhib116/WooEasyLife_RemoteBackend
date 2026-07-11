<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function createMaintenanceAdmin(): User
{
    return User::create([
        'name' => 'Maintenance Admin',
        'email' => 'maintenance-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

it('blocks unauthenticated access to maintenance routes', function () {
    $this->get(route('maintenance.index'))->assertRedirect();
    $this->getJson(route('maintenance.status'))->assertUnauthorized();
    $this->postJson(route('maintenance.run'), ['action' => 'cache'])->assertUnauthorized();
});

it('shows maintenance status for platform admins', function () {
    $admin = createMaintenanceAdmin();

    $this->actingAs($admin)
        ->get(route('maintenance.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->getJson(route('maintenance.status'))
        ->assertOk()
        ->assertJsonStructure([
            'storage_link_exists',
            'storage_link_path',
            'public_storage_path',
            'app_env',
            'app_debug',
            'actions',
        ]);
});

it('can clear application cache from the admin UI', function () {
    $admin = createMaintenanceAdmin();

    $this->actingAs($admin)
        ->postJson(route('maintenance.run'), ['action' => 'cache'])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('rejects unknown maintenance actions', function () {
    $admin = createMaintenanceAdmin();

    $this->actingAs($admin)
        ->postJson(route('maintenance.run'), ['action' => 'drop-database'])
        ->assertUnprocessable();
});

it('removes the old open artisan helper routes', function () {
    $this->get('/run-migration')->assertNotFound();
    $this->get('/migration-rollback')->assertNotFound();
    $this->get('/clear-cache')->assertNotFound();
    $this->get('/clear-route')->assertNotFound();
    $this->get('/clear-config')->assertNotFound();
    $this->get('/clear-view')->assertNotFound();
    $this->get('/storage-link')->assertNotFound();
    $this->get('/get-ip')->assertNotFound();
    $this->get('/send-message')->assertNotFound();
});

it('reports storage link already exists without failing', function () {
    $admin = createMaintenanceAdmin();
    $link = public_path('storage');

    if (! file_exists($link) && ! is_link($link)) {
        symlink(storage_path('app/public'), $link);
    }

    $this->actingAs($admin)
        ->postJson(route('maintenance.run'), ['action' => 'storage_link'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Storage link already exists.');
});
