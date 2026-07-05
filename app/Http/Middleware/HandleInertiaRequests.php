<?php

namespace App\Http\Middleware;

use App\Support\WhatsappLink;
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

        $accessArea = match (true) {
            $user?->role === 'admin' => 'admin',
            in_array($user?->role, ['user', 'merchant_staff'], true) => 'portal',
            default => null,
        };

        $accessLabel = match (true) {
            $user?->role === 'admin' => 'Platform Admin',
            $user?->role === 'user' => 'Merchant',
            $user?->role === 'merchant_staff' => 'Merchant Team',
            default => null,
        };

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? match (true) {
                    in_array($user->role, ['user', 'merchant_staff'], true) => $rbac->merchantPermissionSlugsFor($user),
                    default => $rbac->permissionSlugsFor($user),
                } : [],
                'is_super_admin' => $user ? $rbac->isSuperAdmin($user) : false,
                'access_area' => $accessArea,
                'access_label' => $accessLabel,
                'portal' => $user ? $portal->sharePayload($user) : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'warning' => session('warning'),
                'store_sync' => session('store_sync'),
                'license_token' => session('license_token'),
                'subscription_submitted' => session('subscription_submitted'),
            ],
            'subscriptionPaymentMethods' => app(SubscriptionPaymentConfigService::class)->forApi(),
            'marketing' => [
                'helpline' => config('landing.helpline_phone'),
                'location' => config('landing.location'),
                'footer_tagline' => config('landing.footer_tagline'),
                'footer_tagline_en' => config('landing.footer_tagline_en'),
                'trust_badges' => config('landing.trust_badges', []),
                'whatsapp_url' => WhatsappLink::url(config('landing.whatsapp_phone')),
                'whatsapp_contact_url' => WhatsappLink::url(
                    config('landing.whatsapp_phone'),
                    config('landing.whatsapp_default_message'),
                ),
            ],
        ];
    }
}
