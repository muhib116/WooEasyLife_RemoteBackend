<?php

namespace App\Http\Controllers\Plugin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\LandingAiJob;
use App\Services\ApiAccessTokenResolver;
use App\Services\LandingAi\LandingAiException;
use App\Services\LandingAi\LandingAiGenerator;
use App\Services\LandingAi\LandingAiPrompt;
use App\Services\LandingAi\LandingAiSchema;
use App\Services\LandingSettingsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * Store landing page generator for the WEL Elementor widget (license token auth).
 * The OpenAI key and model live in hub Settings → AI; stores never send a key.
 */
class LandingAiController extends Controller
{
    use ApiResponseTrait;

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private ApiAccessTokenResolver $tokens,
        private LandingAiGenerator $generator,
        private LandingAiSchema $schema,
        private LandingAiPrompt $prompt,
        private LandingSettingsService $settings,
    ) {}

    public function generate(Request $request): JsonResponse
    {
        if (! config('landing_ai.enabled', true)) {
            return $this->errorResponse('AI content is turned off right now.', 503);
        }

        $token = $this->tokens->resolve($request->bearerToken(), $request);
        if (! $token) {
            return $this->errorResponse('Invalid Token', 401);
        }

        if (! $this->generator->isConfigured()) {
            return $this->errorResponse('AI is not set up on the hub yet. Please contact WooEasyLife support.', 503);
        }

        $maxImageChars = (int) ceil((int) config('landing_ai.max_image_bytes', 1_500_000) * 4 / 3) + 64;
        $validator = Validator::make($request->all(), [
            'spec' => ['required', 'array'],
            'spec.fields' => ['nullable', 'array'],
            'spec.lists' => ['nullable', 'array'],
            'store' => ['nullable', 'array'],
            'store.brand' => ['nullable', 'string', 'max:120'],
            'store.site_name' => ['nullable', 'string', 'max:160'],
            'store.layout' => ['nullable', 'string', 'max:40'],
            'products' => ['nullable', 'array', 'max:6'],
            'products.*' => ['array'],
            'context' => ['nullable', 'string', 'max:'.(int) config('landing_ai.max_context_chars', 4000)],
            'reference_urls' => ['nullable', 'array', 'max:'.(int) config('landing_ai.max_reference_urls', 3)],
            'reference_urls.*' => ['nullable', 'string', 'max:2048'],
            'images' => ['nullable', 'array', 'max:'.(int) config('landing_ai.max_images', 4)],
            'images.*' => ['string', 'max:'.$maxImageChars],
        ], [
            'context.max' => 'Notes are too long (max :max characters).',
            'reference_urls.max' => 'Use at most :max reference links.',
            'images.max' => 'Send at most :max photos.',
            'images.*.max' => 'A photo is too large.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $spec = $this->schema->normalizeSpec((array) $request->input('spec', []));
        if ($spec['fields'] === [] && $spec['lists'] === []) {
            return $this->errorResponse('The widget sent no fields to write.', 422);
        }

        $urls = [];
        foreach ((array) $request->input('reference_urls', []) as $i => $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://'.$url;
            }
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                return $this->errorResponse('Reference link '.($i + 1).' is not a valid web address.', 422);
            }
            $urls[] = $url;
        }
        $urls = array_values(array_unique($urls));

        $warnings = [];
        $images = $this->images((array) $request->input('images', []), $warnings);

        $layout = strtolower(trim((string) $request->input('store.layout', '')));
        $input = [
            'store' => [
                'brand' => $this->prompt->line($request->input('store.brand', ''), 120),
                'site_name' => $this->prompt->line($request->input('store.site_name', ''), 160),
                'domain' => $this->prompt->line(parse_url((string) $token->domain, PHP_URL_HOST) ?: $token->domain, 190),
                'layout' => preg_match('/^[a-z0-9_-]{1,40}$/', $layout) ? $layout : '',
            ],
            'products' => $this->prompt->normalizeProducts((array) $request->input('products', [])),
            'context' => trim(mb_scrub((string) $request->input('context', ''), 'UTF-8')),
            'reference_urls' => $urls,
            'images' => $images,
        ];

        $lock = Cache::lock('landing_ai:start:'.$token->id, 30);
        if (! $lock->get()) {
            return $this->errorResponse('A page is already being written. Please wait.', 409);
        }

        try {
            $active = $this->activeJob($token);
            if ($active) {
                return response()->json([
                    'status' => false,
                    'message' => 'A page is already being written for this store. Please wait for it to finish.',
                    'data' => $this->present($active, $token),
                ], 409);
            }

            $limit = $this->settings->landingAiDailyLimit();
            if ($this->usedToday($token) >= $limit) {
                return $this->errorResponse("Today's limit of {$limit} AI pages is used. Please try again tomorrow.", 429);
            }

            $job = LandingAiJob::create([
                'personal_access_token_id' => $token->id,
                'user_id' => $token->tokenable_id,
                'domain' => mb_substr((string) $token->domain, 0, 255),
                'status' => LandingAiJob::STATUS_QUEUED,
                'spec' => $spec,
                'warnings' => $warnings,
                'request_meta' => [
                    'fields' => count($spec['fields']),
                    'lists' => count($spec['lists']),
                    'products' => count($input['products']),
                    'reference_urls' => $urls,
                    'images' => count($images),
                    'context_chars' => mb_strlen($input['context']),
                    'layout' => $input['store']['layout'],
                ],
            ]);
        } finally {
            $lock->release();
        }

        try {
            $job = $this->generator->start($job, $input);
        } catch (LandingAiException $e) {
            $job->fill(['status' => LandingAiJob::STATUS_FAILED, 'error' => $e->getMessage(), 'finished_at' => now()])->save();

            return $this->errorResponse($e->getMessage(), $e->status());
        } catch (\Throwable $e) {
            report($e);
            $message = 'Something went wrong on the hub. Please try again.';
            $job->fill(['status' => LandingAiJob::STATUS_FAILED, 'error' => $message, 'finished_at' => now()])->save();

            return $this->errorResponse($message, 500);
        }

        return $this->successResponse($this->present($job, $token), 'Started', 202);
    }

    public function show(Request $request, string $job): JsonResponse
    {
        $token = $this->tokens->resolve($request->bearerToken(), $request);
        if (! $token) {
            return $this->errorResponse('Invalid Token', 401);
        }

        $model = LandingAiJob::query()
            ->where('uuid', $job)
            ->where('personal_access_token_id', $token->id)
            ->first();

        if (! $model) {
            return $this->errorResponse('This AI job was not found.', 404);
        }

        return $this->successResponse($this->present($this->generator->refresh($model), $token));
    }

    private function activeJob(AccessToken $token): ?LandingAiJob
    {
        $job = LandingAiJob::query()
            ->where('personal_access_token_id', $token->id)
            ->whereIn('status', LandingAiJob::ACTIVE_STATUSES)
            ->latest('id')
            ->first();

        if (! $job) {
            return null;
        }

        $job = $this->generator->refresh($job);

        return $job->isActive() ? $job : null;
    }

    private function usedToday(AccessToken $token): int
    {
        return LandingAiJob::query()
            ->where('personal_access_token_id', $token->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', [...LandingAiJob::ACTIVE_STATUSES, LandingAiJob::STATUS_COMPLETED])
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LandingAiJob $job, AccessToken $token): array
    {
        $started = $job->created_at?->getTimestamp() ?? now()->getTimestamp();
        $ended = $job->finished_at?->getTimestamp() ?? now()->getTimestamp();

        return [
            'job' => $job->uuid,
            'status' => $job->status,
            'model' => $job->model,
            'settings' => $job->status === LandingAiJob::STATUS_COMPLETED ? ($job->result['settings'] ?? []) : null,
            'notes' => (string) ($job->result['notes'] ?? ''),
            'warnings' => array_values((array) ($job->warnings ?? [])),
            'error' => $job->error,
            'elapsed_seconds' => max(0, $ended - $started),
            'quota' => [
                'used' => $this->usedToday($token),
                'limit' => $this->settings->landingAiDailyLimit(),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $images
     * @param  list<string>  $warnings
     * @return list<string> data URLs that decode to real images
     */
    private function images(array $images, array &$warnings): array
    {
        $out = [];
        $maxBytes = (int) config('landing_ai.max_image_bytes', 1_500_000);

        foreach (array_values($images) as $i => $image) {
            $label = 'Photo '.($i + 1);
            $comma = is_string($image) ? strpos($image, ',') : false;
            $header = $comma !== false ? substr($image, 0, $comma) : '';

            if (! preg_match('#^data:(image/[a-z]+);base64$#', $header, $m) || ! in_array($m[1], self::IMAGE_TYPES, true)) {
                $warnings[] = $label.' was skipped (use JPG, PNG, WebP or GIF).';

                continue;
            }

            $raw = base64_decode(substr($image, $comma + 1), true);
            if ($raw === false || $raw === '' || strlen($raw) > $maxBytes) {
                $warnings[] = $label.' was skipped (too large or damaged).';

                continue;
            }

            if (! @getimagesizefromstring($raw)) {
                $warnings[] = $label.' was skipped (not a readable image).';

                continue;
            }

            $out[] = 'data:'.$m[1].';base64,'.base64_encode($raw);
        }

        return $out;
    }
}
