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
            'outfit' => 'navy blazer over white open-collar shirt',
            'posture' => 'sitting at desk, slight friendly smile toward camera, laptop and WEL mug on desk',
            'setting' => 'dark premium home office with warm side light and soft bokeh',
            'layout' => 'founder on the RIGHT half; LEFT ~40% dark clean panel for large Latin headline + feature chips',
        ],
        [
            'outfit' => 'light blue button-down shirt',
            'posture' => 'sitting at desk, chin near clasped hands, looking at camera',
            'setting' => 'dark premium office desk with warm lamp glow',
            'layout' => 'person on the RIGHT third; LEFT reserved for bold Latin marketing headline and icon chips',
        ],
        [
            'outfit' => 'beige crewneck t-shirt',
            'posture' => 'sitting behind a laptop on wooden desk, confident smile',
            'setting' => 'warm home office bookshelf soft bokeh',
            'layout' => 'desk hero with laptop WEL logo; LEFT dark space for Latin headline text only',
        ],
        [
            'outfit' => 'light blue polo shirt',
            'posture' => 'leaning slightly forward at desk beside laptop',
            'setting' => 'modern minimal desk, dark charcoal background',
            'layout' => 'three-quarter angle; RIGHT = founder face; LEFT = Latin headline panel',
        ],
    ];

    /** @var array<string, list<string>> */
    private const CLUSTER_FEATURES = [
        'fake_order' => ['Fraud Check', 'Courier History', 'Checkout OTP', 'Blacklist'],
        'fraud_checker' => ['Fraud Checker', 'Courier History', 'Success Rate', 'Smart Dashboard'],
        'checkout_protection' => ['Checkout OTP', 'Duplicate Block', 'Blacklist', 'Fraud Check'],
        'courier' => ['Auto Courier', 'Return Requests', 'Stuck Scan', 'SteadFast Hub'],
        'messenger' => ['Messenger Inbox', 'Page Chat', 'Lead Labels', 'Order Link'],
        'missing_order' => ['Missing Order', 'Recover Orders', 'Smart Dashboard', 'One-click Call'],
        'facebook_ads' => ['Pixel Protection', 'Confirm Purchase', 'Less Fake Events', 'More Profit'],
        'ai_orders' => ['Message to Order', 'Image to Order', 'Smart Dashboard', 'Save Time'],
        'packing_print' => ['Invoice Print', 'Courier Sticker', 'Packing Slip', 'Multistore'],
        'multistore_app' => ['Multistore App', 'Mobile Dashboard', 'Team Calls', 'All Stores'],
        'team_calls' => ['Call Tracking', 'Staff Assign', 'One-click Call', 'Team Ops'],
        'operations' => ['Smart Dashboard', 'Auto Courier', 'Fraud Check', 'Save Time'],
        'general' => ['Fraud Check', 'Auto Courier', 'Missing Order', 'Smart Dashboard'],
    ];

    /** @var array<string, array{lines: list<string>, sub: string}> */
    private const CLUSTER_COVER_COPY = [
        'fake_order' => [
            'lines' => ['Fake COD orders', 'eating your profit?', 'Stop them today'],
            'sub' => 'Know the practical BD seller fix!',
        ],
        'fraud_checker' => [
            'lines' => ['Courier history', 'before you ship?', 'Check smarter'],
            'sub' => 'Pathao · Steadfast · RedX insights',
        ],
        'facebook_ads' => [
            'lines' => ['Facebook Pixel', 'payment events', 'not firing?'],
            'sub' => 'Know the fix for BD stores!',
        ],
        'checkout_protection' => [
            'lines' => ['Checkout OTP', '& fake customers?', 'Block them'],
            'sub' => 'Protect every COD order',
        ],
        'courier' => [
            'lines' => ['SteadFast returns', 'stuck parcels?', 'Manage in WP'],
            'sub' => 'Ask to return · Decide · Scan stuck',
        ],
        'messenger' => [
            'lines' => ['Facebook Page', 'chat in WordPress?', 'Inbox → order'],
            'sub' => 'Messenger inbox for BD COD sellers',
        ],
        'missing_order' => [
            'lines' => ['Missing orders', 'costing sales?', 'Recover them'],
            'sub' => 'Bring abandoned checkouts back',
        ],
        'ai_orders' => [
            'lines' => ['Inbox to order', 'taking too long?', 'Use AI'],
            'sub' => 'Message & screenshot → order',
        ],
        'general' => [
            'lines' => ['WooCommerce ops', 'for BD sellers', 'One platform'],
            'sub' => 'Fraud · Courier · Growth tools',
        ],
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
                'ai' => 'Author reference image is missing. Place it at resources/blog-ai/author-reference.png.',
            ]);
        }

        $draft = $session->draft_json ?? [];
        $title = (string) ($draft['title'] ?? $session->seed_topic ?? 'WooEasyLife blog');
        $keyword = (string) ($draft['focus_keyword'] ?? '');
        $cluster = (string) ($session->cluster ?? 'general');
        $recipe = self::LAYOUTS[array_rand(self::LAYOUTS)];
        $features = self::CLUSTER_FEATURES[$cluster] ?? self::CLUSTER_FEATURES['general'];
        $coverCopy = $this->coverCopy($cluster, $title, $keyword);
        $author = (string) config('blog_ai.author_name', 'Muhibbullah Ansary');
        $role = (string) config('blog_ai.author_role', 'Developer of WooEasyLife');
        $size = (string) config('blog_ai.image.size', '1536x1024');
        $fidelity = (string) config('blog_ai.image.input_fidelity', 'high');

        $prompt = $this->buildPrompt(
            title: $title,
            keyword: $keyword,
            recipe: $recipe,
            features: $features,
            coverCopy: $coverCopy,
            author: $author,
            role: $role,
            fixPrompt: $fixPrompt,
        );

        $paths = $this->referencePaths($authorPath);

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
            'original_name' => 'blog-ai-'.Str::slug(Str::limit($keyword !== '' ? $keyword : $title, 40, '') ?: 'cover').'.png',
        ]);

        return [
            'media_id' => $media->id,
            'url' => $media->url(),
            'path' => $media->path,
            'recipe' => $recipe,
            'prompt' => $prompt,
            'prompt_excerpt' => Str::limit($prompt, 280),
            'cover_copy' => $coverCopy,
        ];
    }

    /**
     * @return list<string>
     */
    private function referencePaths(string $authorPath): array
    {
        $paths = [$authorPath];

        if (! config('blog_ai.image.use_style_references', false)) {
            return $paths;
        }

        $max = max(0, (int) config('blog_ai.image.max_style_references', 1));
        $style = collect(config('blog_ai.image.style_references', []))
            ->map(fn ($p) => (string) $p)
            ->filter(fn (string $path) => $path !== '' && is_file($path) && $path !== $authorPath)
            ->take($max)
            ->values()
            ->all();

        return array_values(array_filter([...$paths, ...$style]));
    }

    /**
     * @return array{lines: list<string>, sub: string}
     */
    private function coverCopy(string $cluster, string $title, string $keyword): array
    {
        $fallback = self::CLUSTER_COVER_COPY[$cluster] ?? self::CLUSTER_COVER_COPY['general'];

        // If keyword already Latin-ish, prefer it as a short hook line.
        $kw = trim($keyword);
        if ($kw !== '' && preg_match('/^[\x20-\x7E]+$/u', $kw) && mb_strlen($kw) <= 40) {
            return [
                'lines' => array_values(array_filter([
                    $kw,
                    $fallback['lines'][1] ?? 'for BD stores?',
                    $fallback['lines'][2] ?? 'Fix it now',
                ])),
                'sub' => $fallback['sub'],
            ];
        }

        return $fallback;
    }

    /**
     * @param  array{outfit: string, posture: string, setting: string, layout: string}  $recipe
     * @param  list<string>  $features
     * @param  array{lines: list<string>, sub: string}  $coverCopy
     */
    private function buildPrompt(
        string $title,
        string $keyword,
        array $recipe,
        array $features,
        array $coverCopy,
        string $author,
        string $role,
        ?string $fixPrompt,
    ): string {
        $featureLine = implode(', ', array_slice($features, 0, 4));
        $headline = implode(' | ', $coverCopy['lines']);
        $sub = $coverCopy['sub'];
        $latinOnly = (bool) config('blog_ai.image.latin_cover_text_only', true);

        $lines = [
            'Create a FULL photorealistic marketing BANNER for WooEasyLife (Bangladesh WooCommerce SaaS).',
            'IDENTITY LOCK (critical): Image 1 is the ONLY face reference. Render THIS exact man — same bone structure, skin tone, thick curly dark hair, full beard density/shape, thin rectangular or double-bridge glasses, same age. Photorealistic likeness to Image 1, not a cousin or look-alike. Do not average faces from other images.',
            'If extra images are provided they are STYLE only (lighting, desk props, dark/gold mood). IGNORE faces, Bangla text, English headlines, and UI overlays on style images — never copy their text.',
            "Subject credit: {$author}, {$role}. Outfit: {$recipe['outfit']}. Posture: {$recipe['posture']}. Setting: {$recipe['setting']}. Composition: {$recipe['layout']}.",
            "LEFT text panel — print EXACTLY this Latin headline (3 short lines): {$headline}",
            "Under the headline, smaller white/gold subline: {$sub}",
            "Feature chips with icons (Latin labels only): {$featureLine}",
            'Brand look: dark premium charcoal background, gold/amber accents, WEL logo with upward gold arrow on laptop lid and/or mug. Thin elegant frame border OK. No watermarks. No other people. No QR codes.',
            'Typography: sharp, professional, high-contrast, correctly spelled English/Latin characters. Large readable headline. No blurry or melted letters.',
            'Landscape banner suitable for a blog OG/cover. High-end tech brand quality.',
            'Blog topic context (for mood only, do NOT paint this as Bengali glyphs): '.Str::limit($title, 120, ''),
        ];

        if ($latinOnly) {
            $lines[] = 'HARD RULE: Do NOT render any Bengali/Bangla/Indic script characters anywhere. Latin alphabet + digits + ? ! only. Bangla titles live on the website, not inside this bitmap.';
        }

        if ($keyword !== '') {
            $lines[] = 'SEO keyword context (may appear only if Latin): '.Str::limit($keyword, 80, '');
        }

        if (filled($fixPrompt)) {
            $fix = trim((string) $fixPrompt);
            // Never let reviewer force broken Bangla back into pixels.
            if ($latinOnly && preg_match('/[\x{0980}-\x{09FF}]/u', $fix)) {
                $fix = 'Improve identity match to Image 1 (exact face/glasses/beard). Keep all on-image text as clear Latin/English only — no Bengali script.';
            }
            $lines[] = 'Revision notes from reviewer (must fix): '.$fix;
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
