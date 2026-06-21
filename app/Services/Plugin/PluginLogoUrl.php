<?php

namespace App\Services\Plugin;

class PluginLogoUrl
{
    /**
     * WordPress plugin icon URLs (Updates screen + plugin details modal).
     *
     * @return array{1x: string, 2x: string, svg: string}
     */
    public static function icons(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            '1x' => $base . '/brand-asset/icon-128.png',
            '2x' => $base . '/brand-asset/icon-256.png',
            'svg' => '',
        ];
    }

    /**
     * Prefer icons from plugin version settings JSON; fall back to brand-asset URLs.
     *
     * @param  mixed  $icons
     * @return array{1x: string, 2x: string, svg: string}
     */
    public static function resolve(mixed $icons): array
    {
        if (is_array($icons)) {
            $oneX = trim((string) ($icons['1x'] ?? ''));
            $twoX = trim((string) ($icons['2x'] ?? ''));

            if ($oneX !== '' && $twoX !== '') {
                return [
                    '1x' => $oneX,
                    '2x' => $twoX,
                    'svg' => trim((string) ($icons['svg'] ?? '')),
                ];
            }
        }

        return self::icons();
    }

    /**
     * Wide header logo used in the mobile app splash header.
     */
    public static function logo(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base . '/brand-asset/app_logo.png';
    }

    /**
     * Square brand icon (1024 source) for app-logo endpoint and admin UI.
     */
    public static function appIcon(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base . '/app-logo';
    }
}
