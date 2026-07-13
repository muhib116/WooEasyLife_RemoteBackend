<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LandingSettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingSettingsController extends Controller
{
    public function __construct(
        private LandingSettingsService $landingSettings,
    ) {}

    public function index()
    {
        return Inertia::render('LandingSettings/Index', [
            'settings' => $this->landingSettings->all(),
        ]);
    }

    public function update(Request $request)
    {
        $stringFields = [
            'app_download_url',
            'play_store_url',
            'plugin_download_url',
            'bkash_number',
            'rocket_number',
            'nagad_number',
            'admin_whatsapp',
            'admin_email',
            'admin_phone',
            'openai_api_key',
            'openai_blog_model',
            'openai_image_model',
        ];

        $merged = [];

        foreach ($stringFields as $field) {
            $merged[$field] = $request->filled($field)
                ? $request->string($field)->trim()->toString()
                : null;
        }

        $request->merge($merged);

        $validated = $request->validate([
            'app_download_url' => ['nullable', 'string', 'max:2048', 'url'],
            'play_store_url' => ['nullable', 'string', 'max:2048', 'url'],
            'plugin_download_url' => ['nullable', 'string', 'max:2048', 'url'],
            'bkash_number' => ['nullable', 'string', 'max:32'],
            'rocket_number' => ['nullable', 'string', 'max:32'],
            'nagad_number' => ['nullable', 'string', 'max:32'],
            'admin_whatsapp' => ['nullable', 'string', 'max:32'],
            'admin_email' => ['nullable', 'string', 'max:255', 'email'],
            'admin_phone' => ['nullable', 'string', 'max:32'],
            'openai_api_key' => ['nullable', 'string', 'max:512'],
            'openai_blog_model' => ['nullable', 'string', 'in:'.implode(',', LandingSettingsService::BLOG_MODELS)],
            'openai_image_model' => ['nullable', 'string', 'in:'.implode(',', LandingSettingsService::IMAGE_MODELS)],
        ]);

        $this->landingSettings->update($validated);

        return back()->with('success', 'Landing settings saved.');
    }
}
