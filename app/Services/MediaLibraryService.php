<?php

namespace App\Services;

use App\Models\MediaItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaLibraryService
{
    public const DISK = 'public';

    public const MAX_EDGE = 1920;

    public const WEBP_QUALITY = 82;

    public const MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Download a remote image for the cropper (does not store yet).
     *
     * @return array{binary: string, mime: string, filename: string}
     */
    public function fetchRemoteImage(string $url): array
    {
        $this->assertSafeRemoteUrl($url);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'image/*,*/*;q=0.8',
                    'User-Agent' => 'WooEasyLife-MediaLibrary/1.0',
                ])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'url' => 'Could not download the image from that URL.',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'url' => 'Remote server returned HTTP '.$response->status().'.',
            ]);
        }

        $binary = $response->body();
        if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'url' => 'Image is empty or larger than 8MB.',
            ]);
        }

        $mime = strtolower((string) ($response->header('Content-Type') ?: ''));
        $mime = trim(explode(';', $mime)[0]);

        if (str_contains($mime, 'svg')) {
            throw ValidationException::withMessages([
                'url' => 'SVG images are not supported.',
            ]);
        }

        $probe = @imagecreatefromstring($binary);
        if ($probe === false) {
            throw ValidationException::withMessages([
                'url' => 'URL did not return a supported image.',
            ]);
        }
        imagedestroy($probe);

        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $basename = basename((string) $path);
        $filename = $basename !== '' && $basename !== '/'
            ? $basename
            : 'remote-image.'.($this->extensionForMime($mime));

        return [
            'binary' => $binary,
            'mime' => $mime,
            'filename' => $filename,
        ];
    }

    private function assertSafeRemoteUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw ValidationException::withMessages([
                'url' => 'Enter a valid http(s) image URL.',
            ]);
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.localhost')) {
            throw ValidationException::withMessages([
                'url' => 'Local or private URLs are not allowed.',
            ]);
        }

        // Skip live DNS private-IP checks in unit tests (Http::fake cannot satisfy DNS).
        if (app()->runningUnitTests()) {
            return;
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host) ?: [];
            $ips = array_values(array_unique($resolved));
        }

        if ($ips === []) {
            throw ValidationException::withMessages([
                'url' => 'Could not resolve that host.',
            ]);
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw ValidationException::withMessages([
                    'url' => 'Local or private URLs are not allowed.',
                ]);
            }
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => 'jpg',
        };
    }

    /**
     * Store raw image bytes as optimized WebP (used by AI image generation).
     *
     * @param  array{alt?: string|null, title?: string|null, original_name?: string|null}  $meta
     */
    public function storeFromBinary(string $binary, ?int $userId = null, array $meta = []): MediaItem
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages([
                'file' => 'Image processing (GD WebP) is not available on this server.',
            ]);
        }

        if ($binary === '') {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded image.',
            ]);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'Image is larger than 8MB.',
            ]);
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw ValidationException::withMessages([
                'file' => 'Unsupported or corrupt image file.',
            ]);
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($source);
        }

        if (function_exists('imagealphablending') && function_exists('imagesavealpha')) {
            imagealphablending($source, true);
            imagesavealpha($source, true);
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            imagedestroy($source);
            throw ValidationException::withMessages([
                'file' => 'Invalid image dimensions.',
            ]);
        }

        $processed = $this->resizeIfNeeded($source, $width, $height);
        if ($processed !== $source) {
            imagedestroy($source);
            $source = $processed;
            $width = imagesx($source);
            $height = imagesy($source);
        }

        $webp = $this->encodeWebp($source);
        imagedestroy($source);

        $folder = 'media/'.now()->format('Y/m');
        $filename = Str::lower(Str::ulid()).'.webp';
        $path = $folder.'/'.$filename;

        if (! Storage::disk(self::DISK)->put($path, $webp, 'public')) {
            throw new RuntimeException('Failed to store media file.');
        }

        $originalName = (string) ($meta['original_name'] ?? 'ai-blog-image.png');
        $title = filled($meta['title'] ?? null)
            ? (string) $meta['title']
            : pathinfo($originalName, PATHINFO_FILENAME);

        return MediaItem::query()->create([
            'disk' => self::DISK,
            'path' => $path,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size' => strlen($webp),
            'width' => $width,
            'height' => $height,
            'alt' => $meta['alt'] ?? null,
            'title' => $title !== '' ? $title : $filename,
            'created_by' => $userId,
        ]);
    }

    /**
     * Store an uploaded image as optimized WebP under media/Y/m/.
     *
     * @param  array{alt?: string|null, title?: string|null}  $meta
     */
    public function storeUpload(UploadedFile $file, ?int $userId = null, array $meta = []): MediaItem
    {
        $binary = @file_get_contents($file->getRealPath());
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded image.',
            ]);
        }

        return $this->storeFromBinary($binary, $userId, [
            'alt' => $meta['alt'] ?? null,
            'title' => $meta['title'] ?? null,
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * @param  array{alt?: string|null, title?: string|null}  $meta
     */
    public function updateMeta(MediaItem $media, array $meta): MediaItem
    {
        $media->update([
            'alt' => array_key_exists('alt', $meta) ? ($meta['alt'] ?: null) : $media->alt,
            'title' => array_key_exists('title', $meta) ? ($meta['title'] ?: null) : $media->title,
        ]);

        return $media->refresh();
    }

    public function delete(MediaItem $media): void
    {
        if ($media->path && Storage::disk($media->disk ?: self::DISK)->exists($media->path)) {
            Storage::disk($media->disk ?: self::DISK)->delete($media->path);
        }

        $media->delete();
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function resizeIfNeeded($source, int $width, int $height)
    {
        $maxEdge = max($width, $height);
        if ($maxEdge <= self::MAX_EDGE) {
            return $source;
        }

        $scale = self::MAX_EDGE / $maxEdge;
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newW, $newH);
        if ($resized === false) {
            return $source;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
        imagealphablending($resized, true);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

        return $resized;
    }

    /**
     * @param  \GdImage  $image
     */
    private function encodeWebp($image): string
    {
        ob_start();
        $ok = imagewebp($image, null, self::WEBP_QUALITY);
        $data = ob_get_clean();

        if (! $ok || ! is_string($data) || $data === '') {
            throw ValidationException::withMessages([
                'file' => 'Failed to convert image to WebP.',
            ]);
        }

        return $data;
    }
}
