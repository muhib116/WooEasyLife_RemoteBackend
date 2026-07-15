<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogImageReviewAgent
{
    public function __construct(
        private OpenAiBlogClient $openAi,
    ) {}

    /**
     * @param  array{url?: string, path?: string, media_id?: int}  $generated
     * @return array{
     *     pass: bool,
     *     score: int,
     *     alignment: array{ok: bool, notes: string},
     *     consistency: array{ok: bool, notes: string},
     *     brand: array{ok: bool, notes: string},
     *     typography: array{ok: bool, notes: string},
     *     issues: list<string>,
     *     fix_prompt: string|null,
     *     usage: array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}
     * }
     */
    public function review(BlogAiSession $session, array $generated): array
    {
        $authorPath = (string) config('blog_ai.image.author_reference', '');
        if ($authorPath === '' || ! is_file($authorPath)) {
            throw ValidationException::withMessages([
                'ai' => 'Author reference image is missing for review.',
            ]);
        }

        $draft = $session->draft_json ?? [];
        $title = (string) ($draft['title'] ?? $session->seed_topic ?? '');
        $keyword = (string) ($draft['focus_keyword'] ?? '');
        $passScore = (int) config('blog_ai.image.review_pass_score', 70);

        $generatedDataUrl = $this->resolveGeneratedDataUrl($generated);
        $authorDataUrl = $this->openAi->imageDataUrlFromPath($authorPath);

        $system = implode(' ', [
            'You are a strict QA reviewer for WooEasyLife blog marketing banners.',
            'Compare the GENERATED banner to the AUTHOR reference photo and the post context.',
            'Return JSON only.',
            'Hard-fail consistency if the person is clearly a different face (cousin/look-alike counts as fail).',
            'Hard-fail typography if letters are melted, misspelled English, or if ANY Bengali/Indic script is present when latin_cover_text_only is true.',
            'Prefer sharp Latin marketing copy over decorative Bangla (Bangla in AI pixels is usually broken).',
            'Score 0-100 overall quality for publish readiness.',
        ]);

        $userText = json_encode([
            'task' => 'Review this generated marketing banner.',
            'post_title' => $title,
            'focus_keyword' => $keyword,
            'cluster' => $session->cluster,
            'latin_cover_text_only' => (bool) config('blog_ai.image.latin_cover_text_only', true),
            'requirements' => [
                'same_person_as_author_reference' => true,
                'photorealistic_identity_match' => true,
                'latin_english_headline_readable' => true,
                'no_bengali_script_on_image' => (bool) config('blog_ai.image.latin_cover_text_only', true),
                'dark_premium_gold_brand_look' => true,
                'person_preferably_on_right' => true,
            ],
            'response_schema' => [
                'pass' => 'boolean',
                'score' => 'integer 0-100',
                'alignment' => ['ok' => 'boolean', 'notes' => 'string'],
                'consistency' => ['ok' => 'boolean', 'notes' => 'string'],
                'brand' => ['ok' => 'boolean', 'notes' => 'string'],
                'typography' => ['ok' => 'boolean', 'notes' => 'string'],
                'issues' => ['string'],
                'fix_prompt' => 'string or null — short regeneration guidance if not pass (English only)',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->openAi->chatJsonVision([
            ['role' => 'system', 'content' => $system],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => (string) $userText],
                    ['type' => 'text', 'text' => 'AUTHOR REFERENCE (identity lock):'],
                    ['type' => 'image_url', 'image_url' => ['url' => $authorDataUrl]],
                    ['type' => 'text', 'text' => 'GENERATED BANNER:'],
                    ['type' => 'image_url', 'image_url' => ['url' => $generatedDataUrl]],
                ],
            ],
        ], 0.2);

        $decoded = $this->openAi->decodeJsonObject($result['content']);

        $alignment = $this->section($decoded['alignment'] ?? null);
        $consistency = $this->section($decoded['consistency'] ?? null);
        $brand = $this->section($decoded['brand'] ?? null);
        $typography = $this->section($decoded['typography'] ?? null);
        $score = max(0, min(100, (int) ($decoded['score'] ?? 0)));
        $issues = collect($decoded['issues'] ?? [])
            ->map(fn ($i) => trim((string) $i))
            ->filter()
            ->values()
            ->all();
        $fixPrompt = filled($decoded['fix_prompt'] ?? null)
            ? Str::limit(trim((string) $decoded['fix_prompt']), 500, '')
            : null;

        $modelPass = (bool) ($decoded['pass'] ?? false);
        $hardFail = ! $consistency['ok'] || ! $typography['ok'];
        $pass = $modelPass && ! $hardFail && $score >= $passScore;

        if ($hardFail && $fixPrompt === null) {
            $parts = [];
            if (! $consistency['ok']) {
                $parts[] = 'Match Image-1 author face exactly (hair, beard density, thin glasses). Do not invent a look-alike.';
            }
            if (! $typography['ok']) {
                $parts[] = 'Use clear Latin/English headline only — no Bengali script. Fix melted or misspelled letters.';
            }
            $fixPrompt = implode(' ', $parts);
        }

        return [
            'pass' => $pass,
            'score' => $score,
            'alignment' => $alignment,
            'consistency' => $consistency,
            'brand' => $brand,
            'typography' => $typography,
            'issues' => $issues,
            'fix_prompt' => $fixPrompt,
            'usage' => $result['usage'],
        ];
    }

    /**
     * @param  array{url?: string, path?: string}  $generated
     */
    private function resolveGeneratedDataUrl(array $generated): string
    {
        $path = (string) ($generated['path'] ?? '');
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            $binary = Storage::disk('public')->get($path);
            if (is_string($binary) && $binary !== '') {
                return $this->openAi->imageDataUrlFromBinary($binary, 'image/webp');
            }
        }

        $url = (string) ($generated['url'] ?? '');
        if ($url !== '') {
            $response = Http::timeout(60)->get($url);
            if ($response->successful() && $response->body() !== '') {
                $mime = $response->header('Content-Type') ?: 'image/webp';

                return $this->openAi->imageDataUrlFromBinary($response->body(), strtok($mime, ';') ?: 'image/webp');
            }
        }

        throw ValidationException::withMessages([
            'ai' => 'Could not load generated image for review.',
        ]);
    }

    /**
     * @return array{ok: bool, notes: string}
     */
    private function section(mixed $value): array
    {
        if (! is_array($value)) {
            return ['ok' => false, 'notes' => 'Missing review section.'];
        }

        return [
            'ok' => (bool) ($value['ok'] ?? false),
            'notes' => Str::limit(trim((string) ($value['notes'] ?? '')), 400, ''),
        ];
    }
}
