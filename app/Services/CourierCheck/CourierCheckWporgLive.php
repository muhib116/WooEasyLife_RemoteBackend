<?php

namespace App\Services\CourierCheck;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class CourierCheckWporgLive
{
    public const SETTING_KEY = 'courier_check.wporg_live';

    public const DOWNLOAD_URL = 'https://wordpress.org/plugins/easylife-courier-check/';

    private const CACHE_KEY = 'courier_check.wporg_live.stored';

    public function enabled(): bool
    {
        return $this->stored() ?? false;
    }

    public function source(): string
    {
        return $this->stored() !== null ? 'settings' : 'default';
    }

    public function isStoredOn(): bool
    {
        return $this->stored() === true;
    }

    public function update(bool $enabled): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => ['enabled' => $enabled]],
        );
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * WordPress.org redirects a missing plugin slug to search. Do not turn the
     * public download on until the listing itself answers.
     */
    public function ensureListingIsPublic(): void
    {
        try {
            $response = Http::timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->head(self::DOWNLOAD_URL);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'courier_check_wporg_live' => 'WordPress.org could not be reached, so the download link was not turned on.',
            ]);
        }

        if ($this->responseIsPublicListing($response)) {
            return;
        }

        throw ValidationException::withMessages([
            'courier_check_wporg_live' => 'That WordPress.org page is not public yet, so the download link was not turned on.',
        ]);
    }

    private function responseIsPublicListing(Response $response): bool
    {
        if ($response->successful()) {
            return true;
        }

        if (! $response->redirect()) {
            return false;
        }

        $location = (string) $response->header('Location');
        if ($location === '' || str_contains($location, '/plugins/search/')) {
            return false;
        }

        return str_contains($location, '/plugins/easylife-courier-check');
    }

    private function stored(): ?bool
    {
        $cached = $this->cached();
        if (is_array($cached)) {
            return $this->unpack($cached);
        }

        try {
            $row = PlatformSetting::query()->where('key', self::SETTING_KEY)->first();
        } catch (Throwable) {
            return null;
        }

        if (! $row) {
            $this->remember(['missing' => true]);

            return null;
        }

        $value = $row->value;
        $enabled = is_array($value) ? ($value['enabled'] ?? false) : $value;
        $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        $this->remember(['enabled' => $enabled]);

        return $enabled;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            return null;
        }

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function unpack(array $cached): ?bool
    {
        if (($cached['missing'] ?? false) === true) {
            return null;
        }

        return (bool) ($cached['enabled'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function remember(array $payload): void
    {
        try {
            Cache::put(self::CACHE_KEY, $payload, 300);
        } catch (Throwable) {
            // The database value still wins on the next read.
        }
    }
}
