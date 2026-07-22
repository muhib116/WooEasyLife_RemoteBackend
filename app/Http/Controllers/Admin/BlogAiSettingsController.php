<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BlogAi\BlogAiRuntimeConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogAiSettingsController extends Controller
{
    public function __construct(
        private BlogAiRuntimeConfig $runtime,
    ) {}

    public function index(): Response
    {
        return Inertia::render('BlogPosts/Settings', $this->runtime->snapshot());
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'smart_one_click' => ['sometimes', 'boolean'],
            'prefer_gsc' => ['sometimes', 'boolean'],
            'competitors_enabled' => ['sometimes', 'boolean'],
            'competitors_in_prompts' => ['sometimes', 'boolean'],
            'discovery_enabled' => ['sometimes', 'boolean'],
            'discovery_auto_on_smart' => ['sometimes', 'boolean'],
            'landing_ref_fetch' => ['sometimes', 'boolean'],
            'memory_enabled' => ['sometimes', 'boolean'],
            'memory_in_prompts' => ['sometimes', 'boolean'],
            'queue' => ['sometimes', 'boolean'],
            'landing_public_base_url' => ['nullable', 'string', 'max:255'],
            'brave_api_key' => ['nullable', 'string', 'max:512'],
            'bing_api_key' => ['nullable', 'string', 'max:512'],
            'clear_brave_api_key' => ['sometimes', 'boolean'],
            'clear_bing_api_key' => ['sometimes', 'boolean'],
        ]);

        $this->runtime->update($validated);

        return redirect()
            ->route('blogPosts.settings')
            ->with('success', 'Blog AI settings saved.');
    }

    public function reset(): RedirectResponse
    {
        $this->runtime->resetToEnv();

        return redirect()
            ->route('blogPosts.settings')
            ->with('success', 'Blog AI settings reset to .env defaults.');
    }
}
