<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function createMigrationAdmin(): User
{
    return User::create([
        'name' => 'Migration Admin',
        'email' => 'migration-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

it('requires auth for migration admin routes', function () {
    $this->get(route('migrations.index'))->assertRedirect();
    $this->getJson(route('migrations.status'))->assertUnauthorized();
});

it('shows migration status for platform admins', function () {
    $admin = createMigrationAdmin();

    $this->actingAs($admin)
        ->get(route('migrations.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->getJson(route('migrations.status'))
        ->assertOk()
        ->assertJsonStructure([
            'pending_count',
            'ran_count',
            'latest_batch',
            'repository_ready',
            'pending',
            'ran',
            'connection',
        ]);
});

it('can dry-run migrations from the admin UI', function () {
    $admin = createMigrationAdmin();

    $this->actingAs($admin)
        ->postJson(route('migrations.run'), ['pretend' => true])
        ->assertOk()
        ->assertJsonPath('success', true);
});
