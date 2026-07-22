<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CachedBuildAssetController extends Controller
{
    /**
     * Serve Vite fingerprinted assets with long-lived browser cache headers.
     * Used when the request reaches Laravel (tests, or hosts without static headers).
     */
    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        if (preg_match('/^[A-Za-z0-9._\-]+$/', $file) !== 1) {
            abort(404);
        }

        $path = public_path('build/assets/'.$file);
        if (! File::isFile($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'css' => 'text/css; charset=UTF-8',
            'js', 'mjs' => 'application/javascript; charset=UTF-8',
            'map' => 'application/json; charset=UTF-8',
            default => null,
        };

        if ($contentType === null) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000).' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ])->setStatusCode(Response::HTTP_OK);
    }
}
