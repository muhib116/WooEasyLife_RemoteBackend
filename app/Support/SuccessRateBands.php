<?php

namespace App\Support;

use App\Services\PublicFraudCheckService;

/**
 * Bangla labels for the public checker's success-rate bands.
 * Page copy interpolates these so the FAQ cannot drift from the checker.
 */
final class SuccessRateBands
{
    public static function good(): string
    {
        return self::bangla(PublicFraudCheckService::GOOD_MIN);
    }

    public static function caution(): string
    {
        return self::bangla(PublicFraudCheckService::CAUTION_MIN);
    }

    public static function bangla(int $number): string
    {
        return strtr((string) $number, [
            '0' => '০',
            '1' => '১',
            '2' => '২',
            '3' => '৩',
            '4' => '৪',
            '5' => '৫',
            '6' => '৬',
            '7' => '৭',
            '8' => '৮',
            '9' => '৯',
        ]);
    }

    public static function expand(string $text): string
    {
        return str_replace(
            ['{success_good}', '{success_caution}'],
            [self::good(), self::caution()],
            $text,
        );
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public static function expandValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::expand($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $expanded = [];
        foreach ($value as $key => $item) {
            $expanded[$key] = self::expandValue($item);
        }

        return $expanded;
    }
}
