<?php

namespace OneScript\Engine;

class OneScript {
    private static array $config = [];
    private static ?string $rootDir = null;

    public static function getRootDir(): string {
        if (self::$rootDir === null) {
            $normalizedPath = str_replace('\\', '/', __DIR__);
            if (strpos($normalizedPath, '/vendor/arforayejibd/oneweb/') !== false) {
                $parts = explode('/vendor/arforayejibd/oneweb/', $normalizedPath);
                self::$rootDir = $parts[0];
            } else {
                self::$rootDir = dirname(__DIR__, 2);
            }
        }
        return self::$rootDir;
    }

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
        $cacheDir = self::getRootDir() . '/onescript/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $fileMtime = filemtime($viewPath);
        $cacheKey = md5($viewPath . '_' . $fileMtime);
        $cacheFile = $cacheDir . '/' . $cacheKey . '.ast';

        $debug = self::$config['debug'] ?? true;

        if (!$debug && file_exists($cacheFile)) {
            $ast = unserialize(file_get_contents($cacheFile));
        } else {
            $source = file_get_contents($viewPath);
            $tokens = Lexer::tokenize($source);
            $ast = Parser::parse($tokens);
            if (!$debug) {
                @file_put_contents($cacheFile, serialize($ast), LOCK_EX);
            }
        }

        $renderer = new Renderer($viewsDir);
        $output = $renderer->renderNodes($ast, $context);
        
        return self::injectEngineAssets($output);
    }

    private static function injectEngineAssets(string $html): string {
        if (strpos($html, 'cdn.tailwindcss.com') !== false) {
            return $html;
        }

        $assets = "    <script src=\"https://cdn.tailwindcss.com\"></script>\n"
                . "    <script>\n"
                . "        tailwind.config = {\n"
                . "            darkMode: 'class',\n"
                . "            theme: {\n"
                . "                extend: {\n"
                . "                    fontFamily: {\n"
                . "                        sans: ['Plus Jakarta Sans', 'sans-serif'],\n"
                . "                        mono: ['Fira Code', 'monospace'],\n"
                . "                    },\n"
                . "                    colors: {\n"
                . "                        brand: {\n"
                . "                            50: '#eef2ff',\n"
                . "                            100: '#e0e7ff',\n"
                . "                            500: '#6366f1',\n"
                . "                            600: '#4f46e5',\n"
                . "                            700: '#4338ca',\n"
                . "                            900: '#312e81',\n"
                . "                        }\n"
                . "                    }\n"
                . "                }\n"
                . "            }\n"
                . "        };\n"
                . "    </script>\n"
                . "    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css\">\n"
                . "    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n"
                . "    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n"
                . "    <link href=\"https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">\n"
                . "    <style>\n"
                . "        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #090d16; color: #f1f5f9; }\n"
                . "        .glass-card { background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }\n"
                . "        .glass-card:hover { border-color: rgba(99, 102, 241, 0.4); box-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.15); }\n"
                . "        .glow-orb { position: absolute; border-radius: 9999px; filter: blur(100px); pointer-events: none; opacity: 0.3; }\n"
                . "        .text-gradient { background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 50%, #818cf8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }\n"
                . "        .text-gradient-primary { background: linear-gradient(135deg, #818cf8 0%, #c084fc 50%, #f472b6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }\n"
                . "        html, body { max-width: 100vw; overflow-x: hidden; }\n"
                . "        main { transition: opacity 0.2s ease; overflow-x: hidden; }\n"
                . "    </style>\n";

        $spaScript = "<script>\n"
                   . "document.addEventListener('DOMContentLoaded', () => {\n"
                   . "    const pBar = document.createElement('div');\n"
                   . "    pBar.id = 'onescript-progress';\n"
                   . "    pBar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0%;background:linear-gradient(90deg,#6366f1,#c084fc,#f472b6);z-index:9999;transition:width 0.3s ease, opacity 0.3s ease;box-shadow:0 0 10px #6366f1;';\n"
                   . "    document.body.appendChild(pBar);\n\n"
                   . "    function loadPage(url, push = true) {\n"
                   . "        pBar.style.opacity = '1';\n"
                   . "        pBar.style.width = '40%';\n"
                   . "        fetch(url)\n"
                   . "            .then(res => res.text())\n"
                   . "            .then(html => {\n"
                   . "                pBar.style.width = '90%';\n"
                   . "                const parser = new DOMParser();\n"
                   . "                const doc = parser.parseFromString(html, 'text/html');\n"
                   . "                if (doc.title) document.title = doc.title;\n"
                   . "                const newMain = doc.querySelector('main');\n"
                   . "                const currentMain = document.querySelector('main');\n"
                   . "                if (newMain && currentMain) {\n"
                   . "                    currentMain.style.opacity = '0';\n"
                   . "                    setTimeout(() => {\n"
                   . "                        currentMain.innerHTML = newMain.innerHTML;\n"
                   . "                        currentMain.style.opacity = '1';\n"
                   . "                    }, 120);\n"
                   . "                }\n"
                   . "                if (push) history.pushState({}, '', url);\n"
                   . "                pBar.style.width = '100%';\n"
                   . "                setTimeout(() => {\n"
                   . "                    pBar.style.opacity = '0';\n"
                   . "                    pBar.style.width = '0%';\n"
                   . "                }, 200);\n"
                   . "                if (url.includes('#')) {\n"
                   . "                    const hash = url.split('#')[1];\n"
                   . "                    const target = document.getElementById(hash);\n"
                   . "                    if (target) target.scrollIntoView({ behavior: 'smooth' });\n"
                   . "                } else {\n"
                   . "                    window.scrollTo({ top: 0, behavior: 'smooth' });\n"
                   . "                }\n"
                   . "            })\n"
                   . "            .catch(() => {\n"
                   . "                pBar.style.opacity = '0';\n"
                   . "                window.location.href = url;\n"
                   . "            });\n"
                   . "    }\n\n"
                   . "    document.addEventListener('click', e => {\n"
                   . "        const link = e.target.closest('a');\n"
                   . "        if (!link) return;\n"
                   . "        const href = link.getAttribute('href');\n"
                   . "        if (!href || href.startsWith('#') || href.startsWith('http') || href.startsWith('javascript:')) return;\n"
                   . "        e.preventDefault();\n"
                   . "        loadPage(href);\n"
                   . "    });\n\n"
                   . "    window.addEventListener('popstate', () => {\n"
                   . "        loadPage(location.pathname + location.search + location.hash, false);\n"
                   . "    });\n"
                   . "});\n"
                   . "</script>\n";

        if (strpos($html, '</head>') !== false) {
            $html = str_replace('</head>', "{$assets}</head>", $html);
        } else {
            $html = $assets . $html;
        }

        if (strpos($html, '</body>') !== false) {
            $html = str_replace('</body>', "{$spaScript}</body>", $html);
        } else {
            $html .= $spaScript;
        }

        return $html;
    }
}
