<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BlogAi\BlogAiRuntimeConfig;
use App\Services\Seo\GaCredentialStore;
use App\Services\Seo\GoogleAnalyticsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class BlogAiSettingsController extends Controller
{
    public function __construct(
        private BlogAiRuntimeConfig $runtime,
        private GaCredentialStore $gaCredentials,
        private GoogleAnalyticsClient $ga,
    ) {}

    public function index(): Response
    {
        return Inertia::render('BlogPosts/Settings', $this->pagePayload());
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
            'ga_property_id' => ['nullable', 'string', 'max:64'],
        ]);

        $this->runtime->update($validated);

        $gaRaw = trim((string) ($validated['ga_property_id'] ?? ''));
        if ($gaRaw !== '' && $this->gaCredentials->normalizePropertyId($gaRaw) === null) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['ga_property_id' => 'Enter a numeric GA4 property ID (e.g. 123456789).']);
        }
        $this->saveGaPropertyId($gaRaw === '' ? null : $gaRaw);

        return redirect()
            ->route('blogPosts.settings')
            ->with('success', 'Blog AI settings saved.');
    }

    public function reset(): RedirectResponse
    {
        $this->runtime->resetToEnv();
        $this->gaCredentials->clearPropertyId();
        $this->ga->forgetCachedAccessToken();

        return redirect()
            ->route('blogPosts.settings')
            ->with('success', 'Blog AI settings reset to .env defaults.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(): array
    {
        $payload = $this->runtime->snapshot();
        $payload['settings']['ga_property_id'] = $this->ga->propertyId() ?? '';
        $payload['sources']['ga_property_id'] = $this->ga->propertyIdSource() ?? 'missing';
        $payload['ops_notes'][] = 'GA4 Property ID can be set here (or SEO & Learning). Then Connect Google Analytics.';

        return $payload;
    }

    private function saveGaPropertyId(mixed $raw): void
    {
        $value = trim((string) ($raw ?? ''));
        if ($value !== '' && $this->gaCredentials->normalizePropertyId($value) === null) {
            // Soft-skip invalid empty-after-normalize; validation already max-length.
            return;
        }

        try {
            $previous = $this->ga->propertyId();
            $this->gaCredentials->putPropertyId($value === '' ? null : $value);
            $this->ga->forgetCachedAccessToken();
            if ($previous) {
                Cache::forget('seo:ga:realtime:'.$previous);
            }
            $next = $this->ga->propertyId();
            if ($next) {
                Cache::forget('seo:ga:realtime:'.$next);
            }
        } catch (\Throwable $e) {
            Log::error('GA property ID save from Blog AI settings failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
