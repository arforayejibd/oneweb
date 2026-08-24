<?php

class LocalValetDriver extends Valet\Drivers\ValetDriver
{
    /**
     * Get the boot script path, checking vendor first then local.
     */
    private function getBootPath(string $sitePath): ?string
    {
        if (file_exists($sitePath . '/vendor/arforayejibd/oneweb/onescript/engine/boot.php')) {
            return $sitePath . '/vendor/arforayejibd/oneweb/onescript/engine/boot.php';
        }
        if (file_exists($sitePath . '/onescript/engine/boot.php')) {
            return $sitePath . '/onescript/engine/boot.php';
        }
        return null;
    }

    /**
     * Determine if the driver serves the request.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return bool
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return $this->getBootPath($sitePath) !== null;
    }

    /**
     * Determine if the incoming request is for a static file.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string|false
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        $staticPath = $sitePath . '/public' . $uri;

        if (file_exists($staticPath) && !is_dir($staticPath) && pathinfo($staticPath, PATHINFO_EXTENSION) !== 'one') {
            return $staticPath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string|null
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        return $this->getBootPath($sitePath);
    }
}
