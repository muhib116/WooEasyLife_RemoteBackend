<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class MerchantSocialAuthService
{
    /**
     * @return list<string>
     */
    public static function enabledProviders(): array
    {
        $providers = [];

        if (filled(config('services.google.client_id')) && filled(config('services.google.client_secret'))) {
            $providers[] = 'google';
        }

        if (filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret'))) {
            $providers[] = 'facebook';
        }

        return $providers;
    }

    public function authenticate(string $provider, SocialiteUser $socialUser): User
    {
        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail();
        $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname() ?: 'Merchant'));

        if ($providerId === '') {
            throw ValidationException::withMessages([
                'email' => 'Unable to verify your social account. Please try again.',
            ]);
        }

        if (! $email) {
            throw ValidationException::withMessages([
                'email' => 'We need an email address from your '.ucfirst($provider).' account. Allow email access or sign in with password.',
            ]);
        }

        $idColumn = $this->providerIdColumn($provider);

        $user = User::query()->where($idColumn, $providerId)->first();

        if ($user) {
            return $this->ensureMerchantCanLogin($user);
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->role !== 'user') {
                throw ValidationException::withMessages([
                    'email' => $this->roleConflictMessage($existingUser->role),
                ]);
            }

            $existingUser->update([
                $idColumn => $providerId,
                'email_verified_at' => $existingUser->email_verified_at ?? now(),
            ]);

            return $this->ensureMerchantCanLogin($existingUser->fresh());
        }

        return DB::transaction(function () use ($name, $email, $idColumn, $providerId) {
            return User::query()->create([
                'name' => $name,
                'email' => $email,
                $idColumn => $providerId,
                'role' => 'user',
                'status' => true,
                'email_verified_at' => now(),
                'password' => null,
            ]);
        });
    }

    private function ensureMerchantCanLogin(User $user): User
    {
        if ($user->role !== 'user') {
            throw ValidationException::withMessages([
                'email' => $this->roleConflictMessage($user->role),
            ]);
        }

        if (! $user->canAccessPlatform()) {
            throw ValidationException::withMessages([
                'email' => 'This account is disabled.',
            ]);
        }

        return $user;
    }

    private function providerIdColumn(string $provider): string
    {
        return match ($provider) {
            'google' => 'google_id',
            'facebook' => 'facebook_id',
            default => throw new \InvalidArgumentException("Unsupported provider [{$provider}]."),
        };
    }

    private function roleConflictMessage(string $role): string
    {
        return match ($role) {
            'admin' => 'This email belongs to an admin account. Use the admin sign-in page instead.',
            'merchant_staff' => 'Team members must sign in with the email and password provided by the store owner.',
            default => 'This account cannot sign in through the merchant portal.',
        };
    }
}
