<?php

namespace App\Http\Middleware;

use App\Support\WhatsappLink;
use App\Services\LandingSettingsService;
use App\Services\MerchantPortalContext;
use App\Services\RbacService;
use App\Services\SubscriptionPaymentConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
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
        $landingSettings = app(LandingSettingsService::class);
        $paymentConfig = app(SubscriptionPaymentConfigService::class);
        $partnerLabels = $paymentConfig->partnerLabels();
        $metaPixelId = $landingSettings->metaPixelId();

        // Available to the root Blade template for first-paint Meta Pixel injection.
        View::share('metaPixelId', $metaPixelId);

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
                'converted_user_id' => session('converted_user_id'),
                'converted_login_email' => session('converted_login_email'),
                'converted_user_created' => session('converted_user_created'),
                'converted_order_id' => session('converted_order_id'),
                'converted_notify_email' => session('converted_notify_email'),
                'converted_notify_sms' => session('converted_notify_sms'),
                'subscription_submitted' => session('subscription_submitted'),
            ],
            'subscriptionPaymentMethods' => $paymentConfig->forApi(),
            'marketing' => [
                'helpline' => $landingSettings->adminPhone(),
                'admin_email' => $landingSettings->adminEmail(),
                'admin_whatsapp' => $landingSettings->adminWhatsapp(),
                'location' => config('landing.location'),
                'footer_tagline' => config('landing.footer_tagline'),
                'footer_tagline_en' => config('landing.footer_tagline_en'),
                'trust_badges' => collect(config('landing.trust_badges', []))
                    ->map(function (array $badge) use ($partnerLabels) {
                        if (($badge['label'] ?? null) !== 'payment_methods') {
                            return $badge;
                        }

                        if ($partnerLabels === '') {
                            return null;
                        }

                        return [...$badge, 'label' => $partnerLabels];
                    })
                    ->filter()
                    ->values()
                    ->all(),
                'announcement' => config('landing.announcement', []),
                'whatsapp_url' => WhatsappLink::url($landingSettings->adminWhatsapp()),
                'whatsapp_contact_url' => WhatsappLink::url(
                    $landingSettings->adminWhatsapp(),
                    config('landing.whatsapp_default_message'),
                ),
                'payment_numbers' => [
                    'bkash' => $landingSettings->bkashNumber(),
                    'rocket' => $landingSettings->rocketNumber(),
                    'nagad' => $landingSettings->nagadNumber(),
                ],
                'meta_pixel_id' => $metaPixelId,
            ],
        ];
    }
}
