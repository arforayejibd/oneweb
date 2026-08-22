<?php

namespace OneScript\Engine;

class OneScript {
    private static array $config = [];

    public static function boot(array $config): void {
        self::$config = $config;
        Database::init($config['db'] ?? []);
        FormHandler::handlePostRequest();
    }

    public static function render(string $viewPath, array $context = []): string {
        if (!file_exists($viewPath)) {
            return "<div style='color:red; font-family:sans-serif; padding:2rem;'>
                <h2>OneScript Engine Error</h2>
                <p>Template file not found: <strong>" . htmlspecialchars($viewPath) . "</strong></p>
            </div>";
        }

        $viewsDir = self::$config['views_dir'] ?? (dirname($viewPath));
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $fileMtime = filemtime($viewPath);
        $cacheKey = md5($viewPath . '_' . $fileMtime);
        $cacheFile = $cacheDir . '/' . $cacheKey . '.ast';

        if (file_exists($cacheFile)) {
            $ast = unserialize(file_get_contents($cacheFile));
        } else {
            $source = file_get_contents($viewPath);
            $tokens = Lexer::tokenize($source);
            $ast = Parser::parse($tokens);
            @file_put_contents($cacheFile, serialize($ast), LOCK_EX);
        }

        $renderer = new Renderer($viewsDir);
        return $renderer->renderNodes($ast, $context);
    }
}
