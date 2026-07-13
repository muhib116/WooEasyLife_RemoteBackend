<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\MediaLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaLibraryController extends Controller
{
    public function __construct(
        private MediaLibraryService $mediaLibrary,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $items = MediaItem::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('filename', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('alt', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MediaItem $item) => $item->toAdminArray());

        return Inertia::render('MediaLibrary/Index', [
            'items' => $items,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    /**
     * JSON list for media pickers (blog editor, OG image, etc.).
     */
    public function list(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $items = MediaItem::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('filename', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('alt', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->limit(48)
            ->get()
            ->map(fn (MediaItem $item) => $item->toAdminArray())
            ->values();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->mediaLibrary->storeUpload(
            $request->file('file'),
            $request->user()?->id,
            [
                'alt' => $validated['alt'] ?? null,
                'title' => $validated['title'] ?? null,
            ],
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'media' => $media->toAdminArray(),
                'url' => $media->url(),
                'path' => $media->path,
            ]);
        }

        return back()->with('success', 'Media uploaded.');
    }

    /**
     * Fetch a remote image so the admin UI can crop it before storing.
     */
    public function fetchUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $remote = $this->mediaLibrary->fetchRemoteImage($validated['url']);

        return response()->json([
            'filename' => $remote['filename'],
            'mime' => $remote['mime'],
            'data' => base64_encode($remote['binary']),
        ]);
    }

    public function update(Request $request, MediaItem $mediaItem): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->mediaLibrary->updateMeta($mediaItem, $validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'media' => $media->toAdminArray(),
            ]);
        }

        return back()->with('success', 'Media updated.');
    }

    public function destroy(Request $request, MediaItem $mediaItem): JsonResponse|RedirectResponse
    {
        $this->mediaLibrary->delete($mediaItem);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Media deleted.');
    }
}
