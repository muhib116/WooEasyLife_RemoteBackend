<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LandingAi\OpenAiModelCatalog;
use App\Services\LandingSettingsService;
use App\Services\SubscriptionNotificationRuntimeConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingSettingsController extends Controller
{
    public function __construct(
        private LandingSettingsService $landingSettings,
        private SubscriptionNotificationRuntimeConfig $subscriptionNotifications,
        private OpenAiModelCatalog $landingModels,
    ) {}

    public function index(Request $request)
    {
        $settings = $this->landingSettings->all();
        $smsExpiry = $this->subscriptionNotifications->snapshot();
        $catalog = $this->landingModels->catalog($request->boolean('refresh_models'));

        return Inertia::render('LandingSettings/Index', [
            'settings' => array_merge($settings, [
                'sms_expiry' => $smsExpiry['sms_expiry'],
                'sms_expiry_source' => $smsExpiry['source'],
                'landing_model_options' => $this->landingModels->options(),
                'landing_model_best' => $catalog['best'],
                'landing_model_list_source' => $catalog['source'],
                'landing_model_list_error' => $catalog['error'],
                'landing_model_list_fetched_at' => $catalog['fetched_at'],
            ]),
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
            'openai_blog_planning_model',
            'openai_blog_writing_model',
            'openai_image_model',
            'openai_landing_model',
        ];

        $merged = [];

        foreach ($stringFields as $field) {
            $merged[$field] = $request->filled($field)
                ? $request->string($field)->trim()->toString()
                : null;
        }

        $merged['blog_ai_daily_token_cap'] = $request->filled('blog_ai_daily_token_cap')
            ? $request->integer('blog_ai_daily_token_cap')
            : null;

        $merged['landing_ai_daily_limit'] = $request->filled('landing_ai_daily_limit')
            ? $request->integer('landing_ai_daily_limit')
            : null;

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
            'openai_blog_planning_model' => ['nullable', 'string', 'in:'.implode(',', LandingSettingsService::BLOG_MODELS)],
            'openai_blog_writing_model' => ['nullable', 'string', 'in:'.implode(',', LandingSettingsService::BLOG_MODELS)],
            'openai_image_model' => ['nullable', 'string', 'in:'.implode(',', LandingSettingsService::IMAGE_MODELS)],
            'openai_landing_model' => [
                'nullable',
                'string',
                'max:80',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! in_array($value, $this->landingModels->selectableModels(), true)) {
                        $fail('Choose a model from the list.');
                    }
                },
            ],
            'blog_ai_daily_token_cap' => ['nullable', 'integer', 'min:1000', 'max:10000000'],
            'landing_ai_daily_limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $this->landingSettings->update($validated);

        if ($request->exists('sms_expiry')) {
            $request->validate([
                'sms_expiry' => ['required', 'boolean'],
            ]);
            $this->subscriptionNotifications->update($request->boolean('sms_expiry'));
        }

        return back()->with('success', 'Settings saved.');
    }
}
