<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // ChatGPT Ads pixel writes these itself. They must arrive unencrypted for the Conversions API.
        '__oppref',
        '__obref',
    ];
}
