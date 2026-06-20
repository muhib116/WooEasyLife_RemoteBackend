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
        $path = $base . '/images/woo-easy-life';

        return [
            '1x' => $path . '/icon-128.png',
            '2x' => $path . '/icon-256.png',
            'svg' => '',
        ];
    }

    /**
     * Wide header logo used in the mobile app splash header.
     */
    public static function logo(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base . '/images/woo-easy-life/app_logo.png';
    }

    /**
     * Square brand icon (1024 source) for app-logo endpoint and admin UI.
     */
    public static function appIcon(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base . '/images/woo-easy-life/app_icon.jpg';
    }
}
