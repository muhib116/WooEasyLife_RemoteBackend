<?php

namespace App\Services\ReturnAi;

/**
 * Small text and date helpers shared by the return desk engine. Dates are plain
 * store-local Y-m-d strings; the math runs in UTC so no timezone shifts a day.
 */
final class ReturnAiText
{
    private const PHONE = '/(?:\+?88)?0\s*1[3-9](?:[\s-]?\d){8}/';

    private const BN_DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    private const BN_MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    private const BN_MONTHS_SHORT = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টে', 'অক্টো', 'নভে', 'ডিসে'];

    private const BN_DAYS = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];

    public static function clip(mixed $text, int $max = 400): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }

    public static function latinDigits(string $text): string
    {
        return strtr($text, self::BN_DIGITS);
    }

    /**
     * The store masks phones; the hub masks again before anything reaches the model.
     */
    public static function maskPhones(string $text): string
    {
        return (string) preg_replace_callback(self::PHONE, static function (array $m): string {
            return '01*******'.substr((string) preg_replace('/\D+/', '', $m[0]), -2);
        }, self::latinDigits($text));
    }

    public static function key(mixed $value): string
    {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
    }

    public static function ts(mixed $at): int
    {
        $ts = strtotime((string) $at);

        return $ts ?: 0;
    }

    /**
     * Shift a Y-m-d or Y-m-d H:i:s string without leaving its own clock.
     */
    public static function shift(string $value, string $modify): string
    {
        $value = trim($value);
        $format = strlen($value) > 10 ? 'Y-m-d H:i:s' : 'Y-m-d';
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone('UTC'));
        if (! $date) {
            return '';
        }
        $shifted = $date->modify($modify);

        return $shifted ? $shifted->format($format) : '';
    }

    public static function day(string $ymd): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($ymd, 0, 10), new \DateTimeZone('UTC'));

        return $date ?: null;
    }

    /**
     * Relative day words (kal, porshu, আগামীকাল…) that only make sense on the day they were written.
     * সকাল / বিকাল contain কাল but are times of day, so a letter before কাল does not count.
     */
    public static function hasRelativeDay(string $text): bool
    {
        return (bool) preg_match(
            '/(আগামীকাল|আগামী\s*কাল|কালকে|পরশু|(?<![\p{L}\p{M}])কাল(?![\p{L}\p{M}])|\b(?:agami\s*kal|agamikal|kalke|kal|porshu|porsu|tomorrow)\b)/iu',
            $text
        );
    }

    /**
     * Whole calendar days from one Y-m-d to another (negative when $to is earlier).
     */
    public static function daysBetween(string $from, string $to): ?int
    {
        $a = self::day($from);
        $b = self::day($to);
        if (! $a || ! $b) {
            return null;
        }

        return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 86400);
    }

    /**
     * Short Bangla date like ২৯ অক্টো, for one-line timelines.
     */
    public static function dateShortBn(string $ymd): string
    {
        $date = self::day($ymd);
        if (! $date) {
            return '';
        }

        return strtr($date->format('j'), array_flip(self::BN_DIGITS))
            .' '.self::BN_MONTHS_SHORT[(int) $date->format('n') - 1];
    }

    /**
     * Bangla date like ২৯ অক্টোবর (বৃহস্পতিবার).
     */
    public static function dateBn(string $ymd): string
    {
        $date = self::day($ymd);
        if (! $date) {
            return $ymd;
        }

        return strtr($date->format('j'), array_flip(self::BN_DIGITS))
            .' '.self::BN_MONTHS[(int) $date->format('n') - 1]
            .' ('.self::BN_DAYS[(int) $date->format('w')].')';
    }
}
