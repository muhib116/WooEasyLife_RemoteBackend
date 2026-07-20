<?php

namespace App\Services;

use App\Models\TutorialCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TutorialService
{
    private const CACHE_KEY = 'plugin_tutorials_payload';

    /**
     * Matches the WooEasyLife plugin extractVideoId() regex so saved URLs are playable.
     */
    public const YOUTUBE_URL_PATTERN = '/(?:youtube\.com\/.*v=|youtu\.be\/)([^?&\s]+)/i';

    /**
     * Plugin API payload: { categoryKey: [{ title, path }, ...] }
     *
     * Falls back to tutorial.json when tables are missing or empty.
     *
     * @return object|array<string, list<array{title: string, path: string}>>
     */
    public function toPluginPayload(): object|array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            try {
                if (! Schema::hasTable('tutorial_categories')) {
                    return $this->loadFromJsonFile();
                }

                if (! TutorialCategory::query()->exists()) {
                    return $this->loadFromJsonFile();
                }

                $categories = TutorialCategory::query()
                    ->with('videos')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                return $this->buildPayloadFromCategories($categories);
            } catch (Throwable) {
                return $this->loadFromJsonFile();
            }
        });
    }

    /**
     * Build payload from an already-loaded category collection (no cache).
     * Use this for admin previews so the UI always matches the DB.
     *
     * @param  Collection<int, TutorialCategory>  $categories
     * @return object|array<string, list<array{title: string, path: string}>>
     */
    public function buildPayloadFromCategories(Collection $categories): object|array
    {
        $payload = [];

        foreach ($categories as $category) {
            $videos = $category->relationLoaded('videos')
                ? $category->videos
                : $category->videos()->get();

            $payload[$category->key] = $videos
                ->map(fn ($video) => [
                    'title' => (string) ($video->title ?? ''),
                    'path' => (string) $video->path,
                ])
                ->values()
                ->all();
        }

        return $payload === [] ? (object) [] : $payload;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isPlayableYoutubeUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        return (bool) preg_match(self::YOUTUBE_URL_PATTERN, $url);
    }

    /**
     * @return object|array<string, list<array{title: string, path: string}>>
     */
    private function loadFromJsonFile(): object|array
    {
        $path = app_path('Http/Controllers/Data/tutorial.json');

        if (! is_readable($path)) {
            return (object) [];
        }

        $decoded = json_decode((string) file_get_contents($path));

        return $decoded ?? (object) [];
    }
}
