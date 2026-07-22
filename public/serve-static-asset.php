<?php

/**
 * Emit long-lived cache headers and stream a public static asset, then exit.
 * Used for Vite-hashed /build/assets/* files (safe to cache immutably).
 */
if (! isset($welStaticAssetPath) || ! is_string($welStaticAssetPath) || $welStaticAssetPath === '') {
    http_response_code(500);
    exit;
}

if (! is_file($welStaticAssetPath)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($welStaticAssetPath, PATHINFO_EXTENSION));
$contentTypes = [
    'css' => 'text/css; charset=UTF-8',
    'js' => 'application/javascript; charset=UTF-8',
    'mjs' => 'application/javascript; charset=UTF-8',
    'map' => 'application/json; charset=UTF-8',
];

if (! isset($contentTypes[$ext])) {
    http_response_code(404);
    exit;
}

$mtime = filemtime($welStaticAssetPath) ?: time();
$etag = '"'.md5($welStaticAssetPath.'|'.$mtime.'|'.filesize($welStaticAssetPath)).'"';
$inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;

header('Content-Type: '.$contentTypes[$ext]);
header('Cache-Control: public, max-age=31536000, immutable');
header('Expires: '.gmdate('D, d M Y H:i:s', time() + 31536000).' GMT');
header('ETag: '.$etag);
header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
header('X-Content-Type-Options: nosniff');

if (is_string($inm) && trim($inm) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Length: '.(string) filesize($welStaticAssetPath));
readfile($welStaticAssetPath);
exit;
