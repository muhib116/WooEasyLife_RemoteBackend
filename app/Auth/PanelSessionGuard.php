<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;

class PanelSessionGuard extends SessionGuard
{
    /**
     * Admin and merchant "remember me" cookies must not overwrite each other.
     * The admin name stays the historical SessionGuard name.
     */
    public function getRecallerName()
    {
        $panel = str_ends_with((string) config('session.cookie'), '_panel_merchant')
            ? 'merchant'
            : $this->name;

        return 'remember_'.$panel.'_'.sha1(SessionGuard::class);
    }
}
