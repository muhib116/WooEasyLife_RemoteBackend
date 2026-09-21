<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Website;
use App\Services\MerchantAdminSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantAdminSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_splits_and_trims_search_tokens(): void
    {
        $tokens = (new MerchantAdminSearch)->tokens('  Expired   Shop  ');

        $this->assertSame(['Expired', 'Shop'], $tokens);
    }

    public function test_it_matches_merchants_by_domain_name_email_or_phone(): void
    {
        $match = User::create([
            'name' => 'Expired Shop',
            'email' => 'expired-search@example.com',
            'phone' => '01711112222',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $match->id,
            'domain' => 'expired.example.com',
            'status' => true,
        ]);

        $other = User::create([
            'name' => 'Other Shop',
            'email' => 'other-search@example.com',
            'phone' => '01733334444',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $search = new MerchantAdminSearch;

        $byDomain = $search->apply(User::query(), 'expired.example.com')->pluck('id')->all();
        $byPhone = $search->apply(User::query(), '01711112222')->pluck('id')->all();
        $byName = $search->apply(User::query(), 'Expired')->pluck('id')->all();

        $this->assertContains($match->id, $byDomain);
        $this->assertNotContains($other->id, $byDomain);
        $this->assertContains($match->id, $byPhone);
        $this->assertNotContains($other->id, $byPhone);
        $this->assertContains($match->id, $byName);

        $escaped = $search->apply(User::query(), '%')->pluck('id')->all();
        $this->assertNotContains($match->id, $escaped);
        $this->assertNotContains($other->id, $escaped);
    }
}
