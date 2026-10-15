<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Employee\EmployeePhoneNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MerchantLoginRequest extends LoginRequest
{
    /**
     * Merchant sign-in accepts an email address or a mobile number in the email field
     * so existing clients keep posting the same payload.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim((string) $this->input('email'));
        $this->merge(['email' => $identifier]);

        try {
            $authenticated = $this->attemptIdentifier($identifier);
        } catch (ValidationException $exception) {
            RateLimiter::hit($this->throttleKey());

            throw $exception;
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function attemptIdentifier(string $identifier): bool
    {
        if (str_contains($identifier, '@') || ! $this->looksLikePhone($identifier)) {
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL) === false) {
                throw ValidationException::withMessages([
                    'email' => 'Enter a valid email address or mobile number.',
                ]);
            }

            return Auth::attempt([
                'email' => $identifier,
                'password' => (string) $this->input('password'),
            ], $this->boolean('remember'));
        }

        return $this->attemptPhone($identifier);
    }

    private function looksLikePhone(string $identifier): bool
    {
        return EmployeePhoneNormalizer::isValidForWpPassword($identifier);
    }

    private function attemptPhone(string $identifier): bool
    {
        $normalized = EmployeePhoneNormalizer::normalize($identifier);
        $password = (string) $this->input('password');

        $matches = $this->usersMatchingPhone($normalized)
            ->filter(fn (User $user) => Hash::check($password, (string) $user->password))
            ->values();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'email' => 'More than one account uses this number. Sign in with your email instead.',
            ]);
        }

        $user = $matches->first();

        if (! $user instanceof User) {
            return false;
        }

        Auth::login($user, $this->boolean('remember'));

        return true;
    }

    /**
     * @return Collection<int, User>
     */
    private function usersMatchingPhone(string $normalized): Collection
    {
        $candidates = $this->phoneCandidates($normalized);

        if ($candidates === []) {
            return collect();
        }

        $strippedPhone = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), '.', '')";

        return User::query()
            ->whereIn('role', ['user', 'merchant_staff'])
            ->whereRaw(
                $strippedPhone.' IN ('.implode(', ', array_fill(0, count($candidates), '?')).')',
                $candidates
            )
            ->get()
            ->filter(fn (User $user) => EmployeePhoneNormalizer::normalize((string) $user->phone) === $normalized)
            ->values();
    }

    /**
     * @return list<string>
     */
    private function phoneCandidates(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];
        $national = str_starts_with($normalized, '0') ? substr($normalized, 1) : $normalized;

        if ($national !== '' && $national !== $normalized) {
            $candidates[] = $national;
            $candidates[] = '880'.$national;
            $candidates[] = '+880'.$national;
            $candidates[] = '00880'.$national;
        }

        return array_values(array_unique($candidates));
    }
}
