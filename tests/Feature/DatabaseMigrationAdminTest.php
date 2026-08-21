<?php

use App\Models\BlogPost;
use App\Models\User;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\SeededKnowledge;
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
            'seeders',
        ])
        ->assertJsonPath('seeders.0.key', 'BlogPostSeeder')
        ->assertJsonPath('seeders.1.key', 'WiseKnowledgeSeeder');
});

it('can dry-run migrations from the admin UI', function () {
    $admin = createMigrationAdmin();

    $this->actingAs($admin)
        ->postJson(route('migrations.run'), ['pretend' => true])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('runs migrate without passing a false pretend flag', function () {
    $admin = createMigrationAdmin();

    $this->actingAs($admin)
        ->postJson(route('migrations.run'), ['pretend' => false])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['output', 'status' => ['pending_count', 'ran_count']]);
});

it('rejects unknown seeders from the admin UI', function () {
    $admin = createMigrationAdmin();

    $this->actingAs($admin)
        ->postJson(route('migrations.seed'), ['seeder' => 'DatabaseSeeder'])
        ->assertUnprocessable();
});

it('seeds SEO blog posts from the admin UI and is idempotent', function () {
    $admin = createMigrationAdmin();

    $this->actingAs($admin)
        ->postJson(route('migrations.seed'), ['seeder' => 'BlogPostSeeder'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(BlogPost::query()->count())->toBe(20);

    $this->actingAs($admin)
        ->postJson(route('migrations.seed'), ['seeder' => 'BlogPostSeeder'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(BlogPost::query()->count())->toBe(20);
    expect(BlogPost::query()->where('status', 'published')->count())->toBe(20);

    $slug = 'fake-order-loss-calculation-bangladesh';
    $this->get(route('blog.show', $slug))->assertOk();
});

it('seeds Wise knowledge drafts from the admin UI and stays unpublished', function () {
    $admin = createMigrationAdmin();

    $this->actingAs($admin)
        ->postJson(route('migrations.seed'), ['seeder' => 'WiseKnowledgeSeeder'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $drafts = SeededKnowledge::scopeDraftsForReview(
        WiseKnowledgeItem::query()
    )->count();

    expect($drafts)->toBeGreaterThanOrEqual(24);

    $this->actingAs($admin)
        ->postJson(route('migrations.seed'), ['seeder' => 'WiseKnowledgeSeeder'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(
        SeededKnowledge::scopeOwnedSeeds(
            WiseKnowledgeItem::query()
        )->where('status', 'published')->count()
    )->toBe(0);
});
