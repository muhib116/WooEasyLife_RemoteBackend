<?php

namespace App\Support;

class WhatsappLink
{
    public static function url(?string $phone, ?string $message = null): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $url = 'https://wa.me/'.preg_replace('/\D+/', '', $phone);

        if (filled($message)) {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }
}
