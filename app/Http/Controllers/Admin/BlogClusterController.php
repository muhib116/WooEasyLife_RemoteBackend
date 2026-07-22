<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCluster;
use App\Models\BlogPost;
use App\Services\BlogAi\BlogClusterCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogClusterController extends Controller
{
    public function __construct(
        private BlogClusterCatalog $catalog,
    ) {}

    public function index(): Response
    {
        return Inertia::render('BlogPosts/Clusters', [
            'clusters' => $this->catalog->allForAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $normalizedKey = $this->catalog->normalizeKey((string) $request->input('key', ''));
        $request->merge(['key' => $normalizedKey]);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('blog_clusters', 'key')],
            'label' => ['required', 'string', 'max:191'],
            'seed_queries_text' => ['nullable', 'string', 'max:8000'],
            'detect_needles_text' => ['nullable', 'string', 'max:4000'],
            'primary_path' => ['nullable', 'string', 'max:255'],
            'related_paths_text' => ['nullable', 'string', 'max:4000'],
            'must_link_paths_text' => ['nullable', 'string', 'max:2000'],
            'claims_text' => ['nullable', 'string', 'max:4000'],
            'angle_hint' => ['nullable', 'string', 'max:500'],
            'seo_pages_text' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        BlogCluster::query()->create([
            'key' => $validated['key'],
            'label' => trim($validated['label']),
            'seed_queries' => $this->parseLines($validated['seed_queries_text'] ?? '', 40),
            'detect_needles' => $this->parseLines($validated['detect_needles_text'] ?? '', 40),
            'landing_json' => $this->buildLanding($validated),
            'sort_order' => (int) ($validated['sort_order'] ?? 100),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        $this->catalog->forgetCache();

        return redirect()
            ->route('blogPosts.clusters.index')
            ->with('success', 'Cluster created.');
    }

    public function update(Request $request, BlogCluster $blogCluster): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:191'],
            'seed_queries_text' => ['nullable', 'string', 'max:8000'],
            'detect_needles_text' => ['nullable', 'string', 'max:4000'],
            'primary_path' => ['nullable', 'string', 'max:255'],
            'related_paths_text' => ['nullable', 'string', 'max:4000'],
            'must_link_paths_text' => ['nullable', 'string', 'max:2000'],
            'claims_text' => ['nullable', 'string', 'max:4000'],
            'angle_hint' => ['nullable', 'string', 'max:500'],
            'seo_pages_text' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $blogCluster->update([
            'label' => trim($validated['label']),
            'seed_queries' => $this->parseLines($validated['seed_queries_text'] ?? '', 40),
            'detect_needles' => $this->parseLines($validated['detect_needles_text'] ?? '', 40),
            'landing_json' => $this->buildLanding($validated),
            'sort_order' => (int) ($validated['sort_order'] ?? $blogCluster->sort_order),
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $blogCluster->is_active,
        ]);

        $this->catalog->forgetCache();

        return redirect()
            ->route('blogPosts.clusters.index')
            ->with('success', 'Cluster updated.');
    }

    public function destroy(BlogCluster $blogCluster): RedirectResponse
    {
        if ($blogCluster->key === 'general') {
            return back()->withErrors(['cluster' => 'The general cluster cannot be deleted.']);
        }

        if (BlogPost::query()->where('cluster', $blogCluster->key)->exists()) {
            return back()->withErrors([
                'cluster' => 'This cluster is used by blog posts. Deactivate it or reassign those posts first.',
            ]);
        }

        $blogCluster->delete();
        $this->catalog->forgetCache();

        return redirect()
            ->route('blogPosts.clusters.index')
            ->with('success', 'Cluster deleted.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildLanding(array $validated): array
    {
        $primary = trim((string) ($validated['primary_path'] ?? ''));
        $related = $this->parseLines($validated['related_paths_text'] ?? '', 20);
        $must = $this->parseLines($validated['must_link_paths_text'] ?? '', 10);
        $claims = $this->parseLines($validated['claims_text'] ?? '', 12);
        $seoPages = $this->parseLines($validated['seo_pages_text'] ?? '', 12);
        $angle = trim((string) ($validated['angle_hint'] ?? ''));

        return array_filter([
            'primary_path' => $primary !== '' ? $primary : null,
            'related_paths' => $related !== [] ? $related : null,
            'must_link_paths' => $must !== [] ? $must : null,
            'claims' => $claims !== [] ? $claims : null,
            'angle_hint' => $angle !== '' ? $angle : null,
            'seo_pages' => $seoPages !== [] ? $seoPages : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @return list<string>
     */
    private function parseLines(string $text, int $max): array
    {
        $lines = preg_split('/[\r\n,]+/', $text) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->unique()
            ->take($max)
            ->values()
            ->all();
    }
}
