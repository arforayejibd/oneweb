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

if (defined('ONESCRIPT_NO_AUTO_RENDER')) {
    return;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = ltrim($requestUri, '/');

// Security Shield: Block direct access to internal partials, db.json, or engine files
if (preg_match('/^(includes\/|onescript\/|\.env|db\.json|\.git)/i', $path)) {
    header("HTTP/1.1 404 Not Found");
    echo "<div style='font-family:sans-serif; background:#090d16; color:#f43f5e; padding:4rem; text-align:center;'>
        <h2 style='font-size:2rem; font-weight:bold;'>404 Not Found</h2>
        <p style='color:#94a3b8; margin-top:1rem;'>Direct access to internal partials or engine files is strictly prohibited.</p>
    </div>";
    exit;
}

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

// 2. Render .one templates with Nested File-Based Route Resolution
$cleanPath = trim($path, '/');
if ($cleanPath === '' || $cleanPath === 'index.php') {
    $viewName = 'index.one';
} else {
    $viewName = preg_match('/\.one$/i', $cleanPath) ? $cleanPath : $cleanPath . '.one';
}

$publicDir = $rootDir . '/public';

$routeCandidates = [
    $publicDir . '/' . $viewName,
    $publicDir . '/' . rtrim($cleanPath, '/') . '/index.one',
    $rootDir . '/' . $viewName,
    $rootDir . '/views/' . $viewName,
    $publicDir . '/index.one',
];

$targetView = $publicDir . '/index.one';
foreach ($routeCandidates as $candidate) {
    if (file_exists($candidate) && !is_dir($candidate)) {
        $targetView = $candidate;
        break;
    }
}

$output = \OneScript\Engine\OneScript::render($targetView);
echo $output;
