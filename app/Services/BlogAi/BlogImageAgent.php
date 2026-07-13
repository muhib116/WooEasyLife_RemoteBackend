<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiSession;
use App\Services\LandingSettingsService;
use App\Services\MediaLibraryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogImageAgent
{
    /** @var list<array{outfit: string, posture: string, setting: string, layout: string}> */
    private const LAYOUTS = [
        [
            'outfit' => 'light blue button-down shirt',
            'posture' => 'sitting at desk, chin resting on clasped hands, looking at camera',
            'setting' => 'dark premium home office with warm desk lamp bokeh',
            'layout' => 'person on the right third, left side clean dark space for marketing text overlay later',
        ],
        [
            'outfit' => 'navy blazer over white open-collar shirt',
            'posture' => 'relaxed in office chair holding a ceramic mug, slight smile off-camera',
            'setting' => 'dim professional studio desk with plant and warm lamp',
            'layout' => 'centered portrait with soft dark background',
        ],
        [
            'outfit' => 'beige crewneck t-shirt',
            'posture' => 'sitting behind a laptop on wooden desk, friendly confident smile',
            'setting' => 'warm home office bookshelf bokeh',
            'layout' => 'desk hero shot with laptop showing WEL logo sticker',
        ],
        [
            'outfit' => 'polo shirt',
            'posture' => 'leaning slightly forward at desk typing on laptop',
            'setting' => 'modern minimal desk, dark background',
            'layout' => 'three-quarter angle from left',
        ],
    ];

    public function __construct(
        private LandingSettingsService $landingSettings,
        private MediaLibraryService $mediaLibrary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(BlogAiSession $session): array
    {
        $apiKey = $this->landingSettings->openaiApiKey();
        if (! filled($apiKey)) {
            throw ValidationException::withMessages([
                'ai' => 'OpenAI API key is not configured. Set it in Landing Settings.',
            ]);
        }

        $draft = $session->draft_json ?? [];
        $title = (string) ($draft['title'] ?? $session->seed_topic ?? 'WooEasyLife blog');
        $keyword = (string) ($draft['focus_keyword'] ?? '');
        $recipe = self::LAYOUTS[array_rand(self::LAYOUTS)];
        $author = (string) config('blog_ai.author_name', 'Muhibbullah Ansary');
        $role = (string) config('blog_ai.author_role', 'Developer of WooEasyLife');

        $prompt = implode("\n", [
            'Photorealistic marketing photo for WooEasyLife (Bangladesh WooCommerce SaaS).',
            "Same consistent person every time: South Asian Bangladeshi man in late 20s/early 30s, thick curly dark hair, full groomed beard, thin rectangular glasses, approachable founder energy — {$author}, {$role}.",
            "Outfit: {$recipe['outfit']}.",
            "Posture: {$recipe['posture']}.",
            "Setting: {$recipe['setting']}.",
            "Composition: {$recipe['layout']}.",
            'Color grade: dark premium background, gold/amber accent lighting, high-end tech brand look.',
            'Optional small WEL logo with upward gold arrow on laptop lid or mug.',
            'No readable Bangla or English paragraph text in the image (text will be added later). No watermarks. No other people.',
            "Topic context only as mood (do not render as text): {$title}".($keyword !== '' ? " / {$keyword}" : ''),
        ]);

        $model = $this->landingSettings->openaiImageModel() ?: 'gpt-image-1';

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
        ];

        if (str_starts_with($model, 'dall-e')) {
            $payload['size'] = '1792x1024';
            $payload['response_format'] = 'b64_json';
        } else {
            $payload['size'] = '1536x1024';
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->acceptJson()
            ->post('https://api.openai.com/v1/images/generations', $payload);

        if (! $response->successful()) {
            $detail = $response->json('error.message') ?: $response->body();
            throw ValidationException::withMessages([
                'ai' => 'Image generation failed: '.Str::limit((string) $detail, 240),
            ]);
        }

        $b64 = (string) data_get($response->json(), 'data.0.b64_json', '');
        $url = (string) data_get($response->json(), 'data.0.url', '');

        if ($b64 !== '') {
            $binary = base64_decode($b64, true);
            if ($binary === false || $binary === '') {
                throw ValidationException::withMessages([
                    'ai' => 'Image generation returned empty data.',
                ]);
            }
            if (strlen($binary) > MediaLibraryService::MAX_BYTES) {
                throw ValidationException::withMessages([
                    'ai' => 'Generated image is larger than 8MB.',
                ]);
            }
        } elseif ($url !== '') {
            try {
                $fetched = $this->mediaLibrary->fetchRemoteImage($url);
                $binary = $fetched['binary'];
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?: 'Could not download generated image.';
                throw ValidationException::withMessages([
                    'ai' => (string) $msg,
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'ai' => 'Image generation returned empty data.',
            ]);
        }

        $media = $this->mediaLibrary->storeFromBinary($binary, $session->user_id, [
            'title' => Str::limit($title, 80, ''),
            'alt' => $keyword !== '' ? $keyword : $title,
            'original_name' => 'blog-ai-'.Str::slug(Str::limit($title, 40, '')).'.png',
        ]);

        $image = [
            'media_id' => $media->id,
            'url' => $media->url(),
            'path' => $media->path,
            'recipe' => $recipe,
            'prompt_excerpt' => Str::limit($prompt, 280),
        ];

        $session->image_json = $image;
        $session->ai_calls = (int) $session->ai_calls + 1;
        $session->bumpDailyUsage(1, 0);
        $session->status = 'image_ready';

        if (is_array($session->draft_json)) {
            $nextDraft = $session->draft_json;
            $nextDraft['og_image'] = $media->url();
            $session->draft_json = $nextDraft;
        }

        $session->saveIfJobCurrent();

        return $image;
    }
}
