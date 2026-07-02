<?php

namespace App\Services\Employee;

class EmployeePhoneNormalizer
{
    private const MIN_LENGTH = 10;

    private const MAX_LENGTH = 15;

    /**
     * Canonicalize employee phone numbers for storage and WordPress password sync.
     */
    public static function normalize(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if ($normalized !== '' && str_starts_with($normalized, '880')) {
            $normalized = '0'.substr($normalized, 3);
        }

        if (preg_match('/^1\d{9}$/', $normalized)) {
            $normalized = '0'.$normalized;
        }

        return $normalized;
    }

    public static function isValidForWpPassword(string $phone): bool
    {
        $normalized = self::normalize($phone);
        $length = strlen($normalized);

        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }
}
