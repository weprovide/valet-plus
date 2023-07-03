<?php

namespace Valet\Drivers\Custom;

use Valet\Drivers\ValetDriver;

/** Valet driver for Pimcore 5 */
class PimcoreValetDriver extends ValetDriver
{
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
        if (file_exists($sitePath.'/pimcore')) {
             return true;
        }

        return false;
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
        // remove cache busting part from url
        if (strpos($uri, '/cache-buster') === 0) {
            // https://stackoverflow.com/questions/25543974/how-to-get-string-after-second-slash-in-url-using-php
            $last = explode("/", $uri, 3);
            $uri = '/'.$last[2];
        }
        if (file_exists($staticFilePath = $sitePath.'/var/assets'.$uri) || file_exists($staticFilePath = $sitePath.$uri)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): string
    {
        $this->loadServerEnvironmentVariables($sitePath, $siteName);

        if (strpos($uri, '/install') === 0) {
            return $sitePath.'/install.php';
        }

        return $sitePath.'/app.php';
    }
}
