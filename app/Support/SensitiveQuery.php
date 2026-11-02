<?php

namespace App\Support;

class SensitiveQuery
{
    /** @var list<string> */
    public const KEYS = ['phone', 'mobile', 'number'];

    /**
     * Remove phone, mobile, and number from a URL query.
     * Other parameters and the hash stay in their original order and encoding.
     */
    public static function strip(string $url): string
    {
        $hash = '';
        $hashAt = strpos($url, '#');

        if ($hashAt !== false) {
            $hash = substr($url, $hashAt);
            $url = substr($url, 0, $hashAt);
        }

        $queryAt = strpos($url, '?');

        if ($queryAt === false) {
            return $url.$hash;
        }

        $path = substr($url, 0, $queryAt);
        $query = substr($url, $queryAt + 1);
        $kept = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $name = rawurldecode(explode('=', $pair, 2)[0]);

            if (in_array(strtolower($name), self::KEYS, true)) {
                continue;
            }

            $kept[] = $pair;
        }

        $next = $path;

        if ($kept !== []) {
            $next .= '?'.implode('&', $kept);
        }

        return $next.$hash;
    }
}
