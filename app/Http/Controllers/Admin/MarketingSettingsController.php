<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LandingSettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketingSettingsController extends Controller
{
    public function __construct(
        private LandingSettingsService $landingSettings,
    ) {}

    public function index()
    {
        return Inertia::render('MarketingSettings/Index', [
            'settings' => $this->landingSettings->marketingTracking(),
        ]);
    }

    public function update(Request $request)
    {
        $hasOpenAiPixel = $request->exists('openai_ads_pixel_id');

        $request->merge([
            'meta_pixel_id' => $request->filled('meta_pixel_id')
                ? $request->string('meta_pixel_id')->trim()->toString()
                : null,
            'header_scripts' => $request->filled('header_scripts')
                ? $request->string('header_scripts')->toString()
                : null,
            'footer_scripts' => $request->filled('footer_scripts')
                ? $request->string('footer_scripts')->toString()
                : null,
            'openai_ads_pixel_id' => $hasOpenAiPixel && $request->filled('openai_ads_pixel_id')
                ? $request->string('openai_ads_pixel_id')->trim()->toString()
                : null,
        ]);

        $validated = $request->validate([
            'meta_pixel_id' => ['nullable', 'string', 'max:64', 'regex:/^\d+$/'],
            'header_scripts' => ['nullable', 'string', 'max:50000'],
            'footer_scripts' => ['nullable', 'string', 'max:50000'],
            'openai_ads_pixel_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'openai_ads_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $request->filled('openai_ads_api_key')) {
            unset($validated['openai_ads_api_key']);
        }

        if (! $hasOpenAiPixel) {
            unset($validated['openai_ads_pixel_id']);
        }

        $this->landingSettings->update($validated);

        return back()->with('success', 'Marketing settings saved.');
    }
}
