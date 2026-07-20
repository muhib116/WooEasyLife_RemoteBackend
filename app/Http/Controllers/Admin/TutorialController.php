<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TutorialCategory;
use App\Models\TutorialVideo;
use App\Services\TutorialService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TutorialController extends Controller
{
    public function __construct(
        private TutorialService $tutorialService,
    ) {}

    public function index()
    {
        $categories = TutorialCategory::query()
            ->with('videos')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Tutorials/Index', [
            'categories' => $categories,
            'knownKeys' => collect(TutorialCategory::KNOWN_KEYS)
                ->map(fn (string $key) => [
                    'value' => $key,
                    'label' => TutorialCategory::KEY_LABELS[$key] ?? $key,
                ])
                ->values()
                ->all(),
            // Fresh from DB — do not use the API cache for admin preview.
            'payloadPreview' => $this->tutorialService->buildPayloadFromCategories($categories),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $this->validateCategory($request);

        TutorialCategory::create([
            'key' => $data['key'],
            'label' => $data['label'],
            'sort_order' => $data['sort_order'] ?? ((int) TutorialCategory::query()->max('sort_order') + 1),
        ]);

        $this->tutorialService->forgetCache();

        return back()->with('success', 'Category created successfully.');
    }

    public function updateCategory(Request $request, TutorialCategory $tutorialCategory)
    {
        $data = $this->validateCategory($request, $tutorialCategory);

        $tutorialCategory->update([
            'key' => $data['key'],
            'label' => $data['label'],
            'sort_order' => array_key_exists('sort_order', $data) && $data['sort_order'] !== null
                ? (int) $data['sort_order']
                : $tutorialCategory->sort_order,
        ]);

        $this->tutorialService->forgetCache();

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroyCategory(TutorialCategory $tutorialCategory)
    {
        $tutorialCategory->delete();
        $this->tutorialService->forgetCache();

        return back()->with('success', 'Category deleted successfully.');
    }

    public function storeVideo(Request $request)
    {
        $data = $this->validateVideo($request);

        TutorialVideo::create([
            'tutorial_category_id' => $data['tutorial_category_id'],
            'title' => $data['title'] ?? '',
            'path' => $data['path'],
            'sort_order' => $data['sort_order'] ?? (
                (int) TutorialVideo::query()
                    ->where('tutorial_category_id', $data['tutorial_category_id'])
                    ->max('sort_order') + 1
            ),
        ]);

        $this->tutorialService->forgetCache();

        return back()->with('success', 'Video added successfully.');
    }

    public function updateVideo(Request $request, TutorialVideo $tutorialVideo)
    {
        $data = $this->validateVideo($request);

        $tutorialVideo->update([
            'tutorial_category_id' => $data['tutorial_category_id'],
            'title' => $data['title'] ?? '',
            'path' => $data['path'],
            'sort_order' => array_key_exists('sort_order', $data) && $data['sort_order'] !== null
                ? (int) $data['sort_order']
                : $tutorialVideo->sort_order,
        ]);

        $this->tutorialService->forgetCache();

        return back()->with('success', 'Video updated successfully.');
    }

    public function destroyVideo(TutorialVideo $tutorialVideo)
    {
        $tutorialVideo->delete();
        $this->tutorialService->forgetCache();

        return back()->with('success', 'Video deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?TutorialCategory $existing = null): array
    {
        $unique = Rule::unique('tutorial_categories', 'key');

        if ($existing) {
            $unique = $unique->ignore($existing->id);
        }

        $validated = $request->validate([
            'key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                $unique,
            ],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $validated['key'] = trim($validated['key']);
        $validated['label'] = trim($validated['label']);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateVideo(Request $request): array
    {
        $validated = $request->validate([
            'tutorial_category_id' => [
                'required',
                'integer',
                Rule::exists('tutorial_categories', 'id'),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'path' => [
                'required',
                'string',
                'url',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! TutorialService::isPlayableYoutubeUrl(is_string($value) ? $value : null)) {
                        $fail('Use a YouTube watch or youtu.be URL the plugin can play (e.g. https://www.youtube.com/watch?v=… or https://youtu.be/…).');
                    }
                },
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $validated['path'] = trim($validated['path']);
        $validated['title'] = isset($validated['title']) ? trim((string) $validated['title']) : '';

        return $validated;
    }
}
