<?php

namespace App\Services\Courier;

class CourierLogoUrl
{
    public const PARTNERS = ['steadfast', 'pathao', 'paperfly', 'redx'];

    /**
     * Absolute public URL for a courier partner logo (always served from the hub).
     */
    public static function forSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        if ($slug === '' || !in_array($slug, self::PARTNERS, true)) {
            return '';
        }

        $base = rtrim((string) config('app.url'), '/');

        return $base . '/images/' . $slug . '.png';
    }
}
