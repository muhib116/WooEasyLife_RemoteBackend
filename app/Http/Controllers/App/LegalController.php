<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacyPolicy(): View
    {
        return $this->legalPage(
            markdownPath: 'content/wooeasylife/privacy-policy.md',
            view: 'legal.privacy-policy',
            title: 'WooEasyLife App Privacy Policy | WPSaleHub',
            effectiveDate: 'June 22, 2026',
            lastUpdated: 'June 22, 2026',
            brandName: 'WooEasyLife',
            brandMark: 'WE',
            appId: 'com.wooeasylife.woo_easy_life',
            lead: 'How WooEasyLife collects, uses, and protects data when you manage your WooCommerce store from our Android app.',
            metaDescription: 'WooEasyLife mobile app privacy policy for WooCommerce merchants.',
        );
    }

    public function termsOfService(): View
    {
        return $this->legalPage(
            markdownPath: 'content/wooeasylife/terms-of-service.md',
            view: 'legal.terms-of-service',
            title: 'WooEasyLife App Terms of Service | WPSaleHub',
            effectiveDate: 'June 23, 2026',
            lastUpdated: 'June 23, 2026',
            brandName: 'WooEasyLife',
            brandMark: 'WE',
            appId: 'com.wooeasylife.woo_easy_life',
            lead: 'Rules and conditions for using WooEasyLife to manage your WooCommerce stores from our Android app.',
            metaDescription: 'WooEasyLife mobile app terms of service for WooCommerce merchants.',
        );
    }

    public function messengerPrivacyPolicy(): View
    {
        return $this->legalPage(
            markdownPath: 'content/wooeasylife/messenger-privacy-policy.md',
            view: 'legal.privacy-policy',
            title: 'Woo Easy Life Messenger Privacy Policy | WPSaleHub',
            effectiveDate: 'July 27, 2026',
            lastUpdated: 'July 27, 2026',
            brandName: 'Woo Easy Life',
            brandMark: 'WE',
            appId: 'Facebook Messenger App',
            lead: 'How WPSaleHub processes Facebook Page and Messenger Platform data for Woo Easy Life merchants, including data deletion instructions for Meta App Review.',
            metaDescription: 'Privacy policy for the Woo Easy Life Facebook Messenger app: Platform data use, storage, sharing, and user data deletion.',
        );
    }

    public function messengerTermsOfService(): View
    {
        return $this->legalPage(
            markdownPath: 'content/wooeasylife/messenger-terms-of-service.md',
            view: 'legal.terms-of-service',
            title: 'Woo Easy Life Messenger Terms of Service | WPSaleHub',
            effectiveDate: 'July 27, 2026',
            lastUpdated: 'July 27, 2026',
            brandName: 'Woo Easy Life',
            brandMark: 'WE',
            appId: 'Facebook Messenger App',
            lead: 'Rules for connecting Facebook Pages and using Woo Easy Life Messenger features, including Meta policy compliance and merchant responsibilities.',
            metaDescription: 'Terms of Service for the Woo Easy Life Facebook Messenger app used by WooCommerce merchants.',
        );
    }

    public function woodnutsboltsPrivacyPolicy(): View
    {
        return $this->legalPage(
            markdownPath: 'content/woodnutsbolts/privacy-policy.md',
            view: 'legal.privacy-policy',
            title: 'Wood Nuts & Bolts Privacy Policy | WPSaleHub',
            effectiveDate: '12 July 2026',
            lastUpdated: '12 July 2026',
            brandName: 'Wood Nuts & Bolts',
            brandMark: 'WN',
            appId: 'Wood Nuts & Bolts',
            lead: 'How we handle information when you play the Wood Nuts & Bolts mobile puzzle game on Google Play.',
            metaDescription: 'Privacy Policy for Wood Nuts & Bolts, a mobile puzzle game by WP Sale Hub.',
            robots: 'noindex,nofollow',
        );
    }

    public function woodnutsboltsTermsOfService(): View
    {
        return $this->legalPage(
            markdownPath: 'content/woodnutsbolts/terms-of-service.md',
            view: 'legal.terms-of-service',
            title: 'Wood Nuts & Bolts Terms of Service | WPSaleHub',
            effectiveDate: '12 July 2026',
            lastUpdated: '12 July 2026',
            brandName: 'Wood Nuts & Bolts',
            brandMark: 'WN',
            appId: 'Wood Nuts & Bolts',
            lead: 'Rules and conditions for using the Wood Nuts & Bolts mobile puzzle game on Google Play.',
            metaDescription: 'Terms of Service for Wood Nuts & Bolts, a mobile puzzle game by WP Sale Hub.',
            robots: 'noindex,nofollow',
        );
    }

    private function legalPage(
        string $markdownPath,
        string $view,
        string $title,
        string $effectiveDate,
        string $lastUpdated,
        string $brandName,
        string $brandMark,
        string $appId,
        string $lead,
        string $metaDescription,
        string $robots = 'index,follow',
    ): View {
        $markdown = File::get(resource_path($markdownPath));
        // Hero already renders the page H1; drop the markdown title heading so we
        // do not emit a second H1 (or one that duplicates <title>).
        $markdown = (string) preg_replace('/\A\s*#\s+[^\n]+\n+/u', '', $markdown, 1);

        return view($view, [
            'title' => $title,
            'content' => Str::markdown($markdown),
            'effectiveDate' => $effectiveDate,
            'lastUpdated' => $lastUpdated,
            'brandName' => $brandName,
            'brandMark' => $brandMark,
            'appId' => $appId,
            'lead' => $lead,
            'metaDescription' => $metaDescription,
            'robots' => $robots,
            'canonicalUrl' => url()->current(),
            'contactEmail' => 'dev.muhibbullah@gmail.com',
            'contactWebsite' => 'https://app.wpsalehub.com',
        ]);
    }
}
