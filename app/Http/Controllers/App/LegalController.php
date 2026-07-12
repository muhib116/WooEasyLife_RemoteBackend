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
            title: 'Privacy Policy — WooEasyLife',
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
            title: 'Terms of Service — WooEasyLife',
            effectiveDate: 'June 23, 2026',
            lastUpdated: 'June 23, 2026',
            brandName: 'WooEasyLife',
            brandMark: 'WE',
            appId: 'com.wooeasylife.woo_easy_life',
            lead: 'Rules and conditions for using WooEasyLife to manage your WooCommerce stores from our Android app.',
            metaDescription: 'WooEasyLife mobile app terms of service for WooCommerce merchants.',
        );
    }

    public function woodnutsboltsPrivacyPolicy(): View
    {
        return $this->legalPage(
            markdownPath: 'content/woodnutsbolts/privacy-policy.md',
            view: 'legal.privacy-policy',
            title: 'Privacy Policy — Wood Nuts & Bolts',
            effectiveDate: '12 July 2026',
            lastUpdated: '12 July 2026',
            brandName: 'Wood Nuts & Bolts',
            brandMark: 'WN',
            appId: 'Wood Nuts & Bolts',
            lead: 'How we handle information when you play the Wood Nuts & Bolts mobile puzzle game on Google Play.',
            metaDescription: 'Privacy Policy for Wood Nuts & Bolts, a mobile puzzle game by WP Sale Hub.',
        );
    }

    public function woodnutsboltsTermsOfService(): View
    {
        return $this->legalPage(
            markdownPath: 'content/woodnutsbolts/terms-of-service.md',
            view: 'legal.terms-of-service',
            title: 'Terms of Service — Wood Nuts & Bolts',
            effectiveDate: '12 July 2026',
            lastUpdated: '12 July 2026',
            brandName: 'Wood Nuts & Bolts',
            brandMark: 'WN',
            appId: 'Wood Nuts & Bolts',
            lead: 'Rules and conditions for using the Wood Nuts & Bolts mobile puzzle game on Google Play.',
            metaDescription: 'Terms of Service for Wood Nuts & Bolts, a mobile puzzle game by WP Sale Hub.',
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
    ): View {
        $markdown = File::get(resource_path($markdownPath));

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
            'contactEmail' => 'dev.muhibbullah@gmail.com',
            'contactWebsite' => 'https://app.wpsalehub.com',
        ]);
    }
}
