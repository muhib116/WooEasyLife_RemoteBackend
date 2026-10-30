<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClearMerchantFromAdminLogin
{
    /**
     * Older merchant "remember me" cookies live on the shared admin session.
     * Drop that sign-in when the admin login page opens, so it does not hide
     * the admin form or sign a merchant into the admin panel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('muhib/login', 'muhib/login/*') && Auth::check()) {
            $role = Auth::user()?->role;

            if (in_array($role, ['user', 'merchant_staff'], true)) {
                Auth::logout();
            }
        }

        return $next($request);
    }
}
