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
        $request->merge([
            'meta_pixel_id' => $request->filled('meta_pixel_id')
                ? $request->string('meta_pixel_id')->trim()->toString()
                : null,
        ]);

        $validated = $request->validate([
            'meta_pixel_id' => ['nullable', 'string', 'max:64', 'regex:/^\d+$/'],
        ]);

        $this->landingSettings->update($validated);

        return back()->with('success', 'Marketing settings saved.');
    }
}
