<?php
/**
 * OneWeb Core Bootloader Engine
 */

spl_autoload_register(function ($class) {
    $prefix = 'OneWeb\\Engine\\';
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

use OneWeb\Engine\OneWeb;
use OneWeb\Engine\Database;

$rootDir = OneWeb::getRootDir();

// Auto-create VS Code settings for HTML association
$vscodeDir = $rootDir . '/.vscode';
if (!is_dir($vscodeDir)) {
    @mkdir($vscodeDir, 0777, true);
}
$vscodeSettingsFile = $vscodeDir . '/settings.json';
if (!file_exists($vscodeSettingsFile)) {
    $vscodeSettings = [
        "files.associations" => [
            "*.one" => "html"
        ]
    ];
    @file_put_contents($vscodeSettingsFile, json_encode($vscodeSettings, JSON_PRETTY_PRINT));
}

// Auto-create public directory and default index.one template
$publicDir = $rootDir . '/public';
if (!is_dir($publicDir)) {
    @mkdir($publicDir, 0777, true);
}
$indexOne = $publicDir . '/index.one';
if (!file_exists($indexOne)) {
    $defaultIndexContent = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to OneWeb</title>
</head>
<body>
    <one-container max-width="5xl" class="py-12">
        <div class="text-center">
            <one-badge variant="purple">Successfully Installed</one-badge>
            <h1 class="text-4xl font-extrabold my-4 text-gradient-primary">Welcome to OneWeb!</h1>
            <p class="text-slate-400 text-lg mb-8">Your HTML-first PHP template engine is ready to go.</p>
        </div>
        
        <one-card title="Start Customizing" price="Step 1">
            <p class="mb-4">Open the following file and edit it to change this page:</p>
            <code class="block bg-slate-950 p-4 rounded text-emerald-400 mb-4">public/index.one</code>
            <p>You can create other pages like <code>public/about.one</code> which will resolve to <code>/about</code>.</p>
        </one-card>
    </one-container>
</body>
</html>
EOT;
    @file_put_contents($indexOne, $defaultIndexContent);
}

$dbConfig = Database::loadConfigFromOneFile();

OneWeb::boot([
    'db' => $dbConfig,
    'views_dir' => $rootDir . '/public',
    'debug' => true
]);

if (defined('ONEWEB_NO_AUTO_RENDER')) {
    return;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = ltrim($requestUri, '/');

// Security Shield: Block direct access to internal partials, db.json, or engine files
if (preg_match('/^(includes\/|oneweb\/|\.env|db\.json|\.git)/i', $path)) {
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

$output = \OneWeb\Engine\OneWeb::render($targetView);
echo $output;
