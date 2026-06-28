<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\DomainAlignmentAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DomainAlignmentAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_domain_string_inconsistency(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'https://shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $issues = app(DomainAlignmentAuditService::class)->auditUser($user);

        $this->assertTrue(
            collect($issues)->contains(fn ($issue) => $issue['type'] === 'domain_string_inconsistency')
        );
        $this->assertSame(
            'medium',
            collect($issues)->firstWhere('type', 'domain_string_inconsistency')['severity']
        );
    }

    public function test_reports_no_issues_when_domains_match_exactly(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $issues = app(DomainAlignmentAuditService::class)->auditUser($user);

        $this->assertEmpty(
            collect($issues)->where('type', 'domain_string_inconsistency')->all()
        );
    }
}
