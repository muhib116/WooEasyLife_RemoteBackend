<?php

/**
 * Custom PHP built-in server router for `php artisan serve`.
 * Serves static JS/CSS (and related assets) with long-lived Cache-Control
 * headers so local/prod parity matches Semrush browser-caching checks.
 */

$publicPath = is_file(getcwd().DIRECTORY_SEPARATOR.'index.php')
    ? getcwd()
    : __DIR__.DIRECTORY_SEPARATOR.'public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && $uri !== '' && file_exists($publicPath.$uri) && is_file($publicPath.$uri)) {
    $path = $publicPath.$uri;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $basename = basename($path);

    $immutableTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    if (isset($immutableTypes[$ext])) {
        $mtime = filemtime($path) ?: time();
        header('Content-Type: '.$immutableTypes[$ext]);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 31536000).' GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
        header('Content-Length: '.(string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            readfile($path);
        }
        exit;
    }

    if ($basename === 'manifest.json') {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: public, max-age=3600, must-revalidate');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 3600).' GMT');
        header('Content-Length: '.(string) filesize($path));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            readfile($path);
        }
        exit;
    }

    // Non-cached static files (e.g. other json) — let the built-in server handle them.
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteAddress = ($_SERVER['REMOTE_ADDR'] ?? '-').':'.($_SERVER['REMOTE_PORT'] ?? '-');

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
