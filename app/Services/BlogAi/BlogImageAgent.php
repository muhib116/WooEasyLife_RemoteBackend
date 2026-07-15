<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiSession;
use App\Services\LandingSettingsService;
use App\Services\MediaLibraryService;
use Illuminate\Http\Client\PendingRequest;
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
            'layout' => 'person on the right third, left side reserved for Bangla marketing headline and feature icons',
        ],
        [
            'outfit' => 'navy blazer over white open-collar shirt',
            'posture' => 'relaxed in office chair holding a ceramic mug, slight smile off-camera',
            'setting' => 'dim professional studio desk with plant and warm lamp',
            'layout' => 'person on the right, left side dark space for brand text and feature chips',
        ],
        [
            'outfit' => 'beige crewneck t-shirt',
            'posture' => 'sitting behind a laptop on wooden desk, friendly confident smile',
            'setting' => 'warm home office bookshelf bokeh',
            'layout' => 'desk hero shot with laptop showing WEL logo; left side for marketing copy',
        ],
        [
            'outfit' => 'light blue polo shirt',
            'posture' => 'leaning slightly forward at desk typing on laptop',
            'setting' => 'modern minimal desk, dark background',
            'layout' => 'three-quarter angle from left; keep right side for founder, left for Bangla text',
        ],
    ];

    /** @var array<string, list<string>> */
    private const CLUSTER_FEATURES = [
        'fake_order' => ['Fraud Check', 'Courier History', 'Checkout OTP', 'Blacklist'],
        'fraud_checker' => ['Fraud Checker', 'Courier History', 'Success Rate', 'Smart Dashboard'],
        'checkout_protection' => ['Checkout OTP', 'Duplicate Block', 'Blacklist', 'Fraud Check'],
        'courier' => ['Auto Courier', 'Pathao Steadfast RedX', 'Smart Dashboard', 'Save Time'],
        'missing_order' => ['Missing Order', 'Recover Orders', 'Smart Dashboard', 'One-click Call'],
        'facebook_ads' => ['Pixel Protection', 'Confirm Purchase', 'Less Fake Events', 'More Profit'],
        'ai_orders' => ['Message to Order', 'Image to Order', 'Smart Dashboard', 'Save Time'],
        'packing_print' => ['Invoice Print', 'Courier Sticker', 'Packing Slip', 'Multistore'],
        'multistore_app' => ['Multistore App', 'Mobile Dashboard', 'Team Calls', 'All Stores'],
        'team_calls' => ['Call Tracking', 'Staff Assign', 'One-click Call', 'Team Ops'],
        'operations' => ['Smart Dashboard', 'Auto Courier', 'Fraud Check', 'Save Time'],
        'general' => ['Fraud Check', 'Auto Courier', 'Missing Order', 'Smart Dashboard'],
    ];

    public function __construct(
        private LandingSettingsService $landingSettings,
        private MediaLibraryService $mediaLibrary,
    ) {}

    /**
     * Generate a full marketing banner via /v1/images/edits. Does not set session status.
     *
     * @return array{media_id: int, url: string, path: string, recipe: array<string, string>, prompt: string, prompt_excerpt: string}
     */
    public function generate(BlogAiSession $session, ?string $fixPrompt = null): array
    {
        $apiKey = $this->landingSettings->openaiApiKey();
        if (! filled($apiKey)) {
            throw ValidationException::withMessages([
                'ai' => 'OpenAI API key is not configured. Set it in Landing Settings.',
            ]);
        }

        $model = $this->landingSettings->openaiImageModel() ?: 'gpt-image-1';
        if (! str_starts_with($model, 'gpt-image')) {
            throw ValidationException::withMessages([
                'ai' => 'Blog covers require a gpt-image model for identity-locked banners. Set OPENAI image model to gpt-image-1 in Landing Settings.',
            ]);
        }

        $authorPath = (string) config('blog_ai.image.author_reference', '');
        if ($authorPath === '' || ! is_file($authorPath)) {
            throw ValidationException::withMessages([
                'ai' => 'Author reference image is missing. Place it at resources/blog-ai/author-reference.jpg.',
            ]);
        }

        $draft = $session->draft_json ?? [];
        $title = (string) ($draft['title'] ?? $session->seed_topic ?? 'WooEasyLife blog');
        $keyword = (string) ($draft['focus_keyword'] ?? '');
        $cluster = (string) ($session->cluster ?? 'general');
        $recipe = self::LAYOUTS[array_rand(self::LAYOUTS)];
        $features = self::CLUSTER_FEATURES[$cluster] ?? self::CLUSTER_FEATURES['general'];
        $author = (string) config('blog_ai.author_name', 'Muhibbullah Ansary');
        $role = (string) config('blog_ai.author_role', 'Developer of WooEasyLife');
        $size = (string) config('blog_ai.image.size', '1536x1024');
        $fidelity = (string) config('blog_ai.image.input_fidelity', 'high');

        $prompt = $this->buildPrompt(
            title: $title,
            keyword: $keyword,
            recipe: $recipe,
            features: $features,
            author: $author,
            role: $role,
            fixPrompt: $fixPrompt,
        );

        $paths = array_values(array_filter([
            $authorPath,
            ...array_map('strval', config('blog_ai.image.style_references', [])),
        ], fn (string $path) => $path !== '' && is_file($path)));

        $request = Http::withToken($apiKey)
            ->timeout(180)
            ->acceptJson();

        $request = $this->attachReferenceImages($request, $paths);

        $response = $request->post('https://api.openai.com/v1/images/edits', [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'input_fidelity' => $fidelity,
        ]);

        if (! $response->successful()) {
            $detail = $response->json('error.message') ?: $response->body();
            throw ValidationException::withMessages([
                'ai' => 'Image generation failed: '.Str::limit((string) $detail, 240),
            ]);
        }

        $binary = $this->extractBinary($response->json());

        $media = $this->mediaLibrary->storeFromBinary($binary, $session->user_id, [
            'title' => Str::limit($title, 80, ''),
            'alt' => $keyword !== '' ? $keyword : $title,
            'original_name' => 'blog-ai-'.Str::slug(Str::limit($title, 40, '')).'.png',
        ]);

        return [
            'media_id' => $media->id,
            'url' => $media->url(),
            'path' => $media->path,
            'recipe' => $recipe,
            'prompt' => $prompt,
            'prompt_excerpt' => Str::limit($prompt, 280),
        ];
    }

    /**
     * @param  array{outfit: string, posture: string, setting: string, layout: string}  $recipe
     * @param  list<string>  $features
     */
    private function buildPrompt(
        string $title,
        string $keyword,
        array $recipe,
        array $features,
        string $author,
        string $role,
        ?string $fixPrompt,
    ): string {
        $featureLine = implode(', ', array_slice($features, 0, 4));
        $lines = [
            'Create a FULL photorealistic marketing BANNER for WooEasyLife (Bangladesh WooCommerce SaaS).',
            'Image 1 is the IDENTITY reference: keep the SAME person exactly (face, thick curly dark hair, full beard, thin rectangular/double-bridge glasses). Do not invent a different face.',
            'Any additional images are STYLE references only for layout, lighting, desk props, and brand mood. IGNORE all Bangla/English text, headlines, and UI overlays on style references — do not copy their copy.',
            "Render this Bangla headline exactly (or a tight paraphrase that keeps the same meaning): {$title}",
            $keyword !== '' ? "Focus keyword to reinforce in subline/chips: {$keyword}" : '',
            "Person: {$author}, {$role}. Outfit: {$recipe['outfit']}. Posture: {$recipe['posture']}. Setting: {$recipe['setting']}. Composition: {$recipe['layout']}.",
            "Left side: bold Bangla marketing text + 3–4 feature chips with simple icons ({$featureLine}). Right side: the founder.",
            'Brand look: dark premium background, gold/amber accents, WEL logo with upward gold arrow on laptop lid or mug. No watermarks. No other people.',
            'Landscape banner suitable for a blog cover. High-end tech brand quality.',
        ];

        if (filled($fixPrompt)) {
            $lines[] = 'Revision notes from reviewer (must fix): '.$fixPrompt;
        }

        return implode("\n", array_values(array_filter($lines, fn ($l) => $l !== '')));
    }

    /**
     * @param  list<string>  $paths
     */
    private function attachReferenceImages(PendingRequest $request, array $paths): PendingRequest
    {
        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            if ($contents === false || $contents === '') {
                continue;
            }
            $mime = $this->mimeForPath($path, $contents);
            $filename = basename($path);
            $request = $request->attach('image[]', $contents, $filename, ['Content-Type' => $mime]);
        }

        return $request;
    }

    private function mimeForPath(string $path, string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($contents) ?: null;
        if (is_string($detected) && str_starts_with($detected, 'image/')) {
            return $detected;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractBinary(?array $json): string
    {
        $b64 = (string) data_get($json, 'data.0.b64_json', '');
        $url = (string) data_get($json, 'data.0.url', '');

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

            return $binary;
        }

        if ($url !== '') {
            try {
                return $this->mediaLibrary->fetchRemoteImage($url)['binary'];
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?: 'Could not download generated image.';
                throw ValidationException::withMessages([
                    'ai' => (string) $msg,
                ]);
            }
        }

        throw ValidationException::withMessages([
            'ai' => 'Image generation returned empty data.',
        ]);
    }
}
