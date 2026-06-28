<?php

namespace App\Http\Middleware;

use App\Services\MerchantPortalContext;
use App\Services\RbacService;
use App\Services\SubscriptionPaymentConfigService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $rbac = app(RbacService::class);
        $portal = app(MerchantPortalContext::class);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $rbac->permissionSlugsFor($user) : [],
                'is_super_admin' => $user ? $rbac->isSuperAdmin($user) : false,
                'portal' => $user ? $portal->sharePayload($user) : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'license_token' => session('license_token'),
            ],
            'subscriptionPaymentMethods' => app(SubscriptionPaymentConfigService::class)->forApi(),
        ];
    }
}
