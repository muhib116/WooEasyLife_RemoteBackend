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
        );
    }

    private function legalPage(
        string $markdownPath,
        string $view,
        string $title,
        string $effectiveDate,
        string $lastUpdated,
    ): View {
        $markdown = File::get(resource_path($markdownPath));

        return view($view, [
            'title' => $title,
            'content' => Str::markdown($markdown),
            'effectiveDate' => $effectiveDate,
            'lastUpdated' => $lastUpdated,
            'contactEmail' => 'dev.muhibbullah@gmail.com',
            'contactWebsite' => 'https://app.wpsalehub.com',
        ]);
    }
}
