<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Website;
use App\Services\MerchantDomainValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MerchantDomainValidatorTest extends TestCase
{
    use RefreshDatabase;

    private MerchantDomainValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(MerchantDomainValidator::class);
    }

    private function merchant(string $email): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => $email,
            'phone' => uniqid('01'),
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    public function test_rejects_invalid_domain(): void
    {
        $user = $this->merchant('invalid@example.com');

        $this->expectException(ValidationException::class);

        $this->validator->validate($user, 'not a domain');
    }

    public function test_rejects_domain_owned_by_another_merchant(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate($intruder, 'shop.example.com', forAdmin: true);
    }

    public function test_rejects_duplicate_website_for_same_merchant(): void
    {
        $user = $this->merchant('dup@example.com');

        Website::create([
            'user_id' => $user->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate($user, 'localhost', forAdmin: true, requireNewWebsite: true);
    }

    public function test_allows_existing_domain_when_not_requiring_new_website(): void
    {
        $user = $this->merchant('assign@example.com');

        Website::create([
            'user_id' => $user->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $domain = $this->validator->validate($user, 'localhost', forAdmin: true);

        $this->assertSame('localhost', $domain);
    }

    public function test_allows_localhost_in_local_environment(): void
    {
        $user = $this->merchant('local@example.com');

        $domain = $this->validator->validate($user, 'localhost', forAdmin: true);

        $this->assertSame('localhost', $domain);
    }
}
