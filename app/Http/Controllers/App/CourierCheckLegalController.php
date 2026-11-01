<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourierCheckLegalController extends Controller
{
    public function privacyPolicy(): View
    {
        return $this->legalPage(
            markdownPath: 'content/wooeasylife/courier-check-privacy-policy.md',
            view: 'legal.privacy-policy',
            title: 'Courier Check Privacy Policy | WPSaleHub',
            effectiveDate: '1 November 2026',
            lastUpdated: '1 November 2026',
            brandName: 'Courier Check',
            brandMark: 'CC',
            appId: 'WordPress plugin',
            lead: 'What the free Courier Check WordPress plugin sends, why it is sent, and how long WPSaleHub keeps it.',
            metaDescription: 'Privacy policy for the free Courier Check WordPress plugin: billing phone, site URL, retention, and contact.',
        );
    }

    public function termsOfService(): View
    {
        return $this->legalPage(
            markdownPath: 'content/wooeasylife/courier-check-terms-of-service.md',
            view: 'legal.terms-of-service',
            title: 'Courier Check Terms of Service | WPSaleHub',
            effectiveDate: '1 November 2026',
            lastUpdated: '1 November 2026',
            brandName: 'Courier Check',
            brandMark: 'CC',
            appId: 'WordPress plugin',
            lead: 'Rules for using the free Courier Check WordPress plugin and the delivery-history result it shows.',
            metaDescription: 'Terms of service for the free Courier Check WordPress plugin.',
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
            'robots' => 'index,follow',
            'canonicalUrl' => url()->current(),
            'contactEmail' => 'dev.muhibbullah@gmail.com',
            'contactWebsite' => 'https://app.wpsalehub.com',
        ]);
    }
}
