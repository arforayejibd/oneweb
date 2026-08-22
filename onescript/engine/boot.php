<?php
/**
 * OneScript Core Bootloader Engine
 */

spl_autoload_register(function ($class) {
    $prefix = 'OneScript\\Engine\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use OneScript\Engine\OneScript;
use OneScript\Engine\Database;

$rootDir = dirname(__DIR__, 2);
$dbConfig = Database::loadConfigFromOneFile();

OneScript::boot([
    'db' => $dbConfig,
    'views_dir' => $rootDir . '/public',
    'debug' => true
]);

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = ltrim($requestUri, '/');

// 1. Serve static assets (CSS, JS, images, fonts) from public/ directly
$publicAsset = $rootDir . '/public/' . $path;
if (!empty($path) && file_exists($publicAsset) && !is_dir($publicAsset) && !preg_match('/\.one$/i', $path)) {
    $ext = strtolower(pathinfo($publicAsset, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($publicAsset);
    exit;
}

// 2. Render .one templates
if ($path === '' || $path === 'index.php') {
    $viewName = 'index.one';
} else {
    $viewName = preg_match('/\.one$/i', $path) ? $path : $path . '.one';
}

$publicDir = $rootDir . '/public';
$targetView = $publicDir . '/' . $viewName;

if (!file_exists($targetView)) {
    $targetView = $rootDir . '/' . $viewName;
}
if (!file_exists($targetView)) {
    $targetView = $rootDir . '/views/' . $viewName;
}
if (!file_exists($targetView)) {
    $targetView = $publicDir . '/index.one';
}

echo OneScript::render($targetView);
