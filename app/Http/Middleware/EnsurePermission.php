<?php

namespace App\Http\Middleware;

use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        protected RbacService $rbac
    ) {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'You do not have permission to perform this action.');
        }

        // Support OR lists: permission:roles.manage|billing.manage
        $any = array_values(array_filter(array_map(
            'trim',
            preg_split('/[|,]/', $permission) ?: []
        )));

        foreach ($any as $slug) {
            if ($this->rbac->hasPermission($user, $slug)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
