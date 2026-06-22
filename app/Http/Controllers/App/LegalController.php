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
        $markdown = File::get(resource_path('content/wooeasylife/privacy-policy.md'));

        return view('legal.privacy-policy', [
            'title' => 'Privacy Policy — WooEasyLife',
            'content' => Str::markdown($markdown),
            'effectiveDate' => 'June 22, 2026',
            'lastUpdated' => 'June 22, 2026',
        ]);
    }
}
