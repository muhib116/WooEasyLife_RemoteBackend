<?php

namespace App\Support;

/**
 * Public courier names for marketing copy, FAQ, JSON-LD, and llms.txt.
 * History membership matches PlatformSufficiencyChecker::configuredCouriers().
 */
class PublicCourierNames
{
    /**
     * @return list<string>
     */
    public static function historyNames(): array
    {
        $names = [];

        foreach (config('couriers.history', []) as $row) {
            if (! is_array($row) || ! filled($row['name'] ?? null)) {
                continue;
            }

            $flag = $row['flag'] ?? null;

            if ($flag === 'include_redx' && ! config('fraud_check.include_redx', true)) {
                continue;
            }

            if ($flag === 'include_carrybee' && ! config('fraud_check.include_carrybee', true)) {
                continue;
            }

            $names[] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function bookingNames(): array
    {
        return array_values(array_filter(
            config('couriers.booking', []),
            static fn ($name): bool => is_string($name) && $name !== ''
        ));
    }

    public static function historyPhrase(string $locale): string
    {
        return self::phrase(self::historyNames(), $locale);
    }

    public static function bookingPhrase(string $locale): string
    {
        return self::phrase(self::bookingNames(), $locale);
    }

    /**
     * Short title fragment. The count comes from the live history list.
     * Bangla uses "সহ Nটি কুরিয়ার", or "+ আরও" when that fragment is too long to sit in a ~60-character title.
     */
    public static function historyShort(string $locale): string
    {
        $names = self::historyNames();
        $head = array_values(array_filter(
            ['Pathao', 'Steadfast', 'RedX'],
            static fn (string $name): bool => in_array($name, $names, true)
        ));
        $headText = implode(', ', $head);
        $more = count($names) - count($head);

        if ($locale === 'en') {
            if ($more <= 0) {
                return $headText;
            }

            return $headText.' + '.$more.' more';
        }

        $full = $headText.' সহ '.self::bnDigits((string) count($names)).'টি কুরিয়ার';

        if (mb_strlen($full) > 42) {
            return $headText.' + আরও';
        }

        return $full;
    }

    public static function expand(string $text): string
    {
        if (! str_contains($text, '{couriers.')) {
            return $text;
        }

        return strtr($text, [
            '{couriers.history.bn}' => self::historyPhrase('bn'),
            '{couriers.history.en}' => self::historyPhrase('en'),
            '{couriers.history.short.bn}' => self::historyShort('bn'),
            '{couriers.history.short.en}' => self::historyShort('en'),
            '{couriers.history.count.bn}' => self::bnDigits((string) count(self::historyNames())),
            '{couriers.history.count.en}' => (string) count(self::historyNames()),
            '{couriers.booking.bn}' => self::bookingPhrase('bn'),
            '{couriers.booking.en}' => self::bookingPhrase('en'),
        ]);
    }

    public static function expandValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::expand($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::expandValue($item);
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    public static function share(): array
    {
        return [
            'historyPhraseBn' => self::historyPhrase('bn'),
            'historyPhraseEn' => self::historyPhrase('en'),
            'historyShortBn' => self::historyShort('bn'),
            'historyShortEn' => self::historyShort('en'),
            'bookingPhraseBn' => self::bookingPhrase('bn'),
            'bookingPhraseEn' => self::bookingPhrase('en'),
        ];
    }

    /**
     * @param  list<string>  $names
     */
    public static function phrase(array $names, string $locale): string
    {
        $names = array_values($names);
        $count = count($names);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $names[0];
        }

        if ($locale === 'en') {
            if ($count === 2) {
                return $names[0].' and '.$names[1];
            }

            $last = array_pop($names);

            return implode(', ', $names).', and '.$last;
        }

        $last = array_pop($names);

        return implode(', ', $names).' ও '.$last;
    }

    private static function bnDigits(string $value): string
    {
        return strtr($value, [
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
}
