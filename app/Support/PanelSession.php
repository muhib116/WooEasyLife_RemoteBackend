<?php

namespace App\Support;

use Illuminate\Http\Request;

class PanelSession
{
    public const MERCHANT_COOKIE_SUFFIX = '_panel_merchant';

    public const MERCHANT_XSRF_COOKIE = 'XSRF-TOKEN-MERCHANT';

    public const ADMIN_XSRF_COOKIE = 'XSRF-TOKEN';

    public static function isMerchant(Request $request): bool
    {
        return $request->is('portal', 'portal/*', 'marchent', 'marchent/*');
    }

    public static function baseCookieName(): string
    {
        $name = (string) config('session.cookie');
        $suffix = self::MERCHANT_COOKIE_SUFFIX;

        return str_ends_with($name, $suffix)
            ? substr($name, 0, -strlen($suffix))
            : $name;
    }

    public static function merchantCookieName(): string
    {
        return self::baseCookieName().self::MERCHANT_COOKIE_SUFFIX;
    }

    public static function cookieNameFor(Request $request): string
    {
        return self::isMerchant($request)
            ? self::merchantCookieName()
            : self::baseCookieName();
    }

    public static function xsrfCookieNameFor(Request $request): string
    {
        return self::isMerchant($request)
            ? self::MERCHANT_XSRF_COOKIE
            : self::ADMIN_XSRF_COOKIE;
    }
}
