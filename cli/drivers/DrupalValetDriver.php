<?php

class DrupalValetDriver extends ValetDriver
{
    /**
     * Determine if the driver serves the request.
     *
     * @param  string $sitePath
     * @param  string $siteName
     * @param  string $uri
     * @return void
     */
    public function serves($sitePath, $siteName, $uri)
    {
        /**
         * /misc/drupal.js = Drupal 7
         * /core/lib/Drupal.php = Drupal 8
         */
        if (file_exists($sitePath . '/misc/drupal.js') ||
            file_exists($sitePath . '/core/lib/Drupal.php')) {
            return true;
        }
    }

    /**
     * Determine if the incoming request is for a static file.
     *
     * @param  string $sitePath
     * @param  string $siteName
     * @param  string $uri
     * @return string|false
     */
    public function isStaticFile($sitePath, $siteName, $uri)
    {
        if (file_exists($sitePath . $uri) &&
            !is_dir($sitePath . $uri) &&
            pathinfo($sitePath . $uri)['extension'] != 'php') {
            return $sitePath . $uri;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param  string $sitePath
     * @param  string $siteName
     * @param  string $uri
     * @return string
     */
    public function frontControllerPath($sitePath, $siteName, $uri)
    {
        $this->loadServerEnvironmentVariables($sitePath, $siteName);

        $_SERVER['DOCUMENT_ROOT'] = $sitePath;

        $matches = [];
        if (preg_match('/^\/(.*?)\.php/', $uri, $matches)) {
            $filename = $matches[0];
            if (file_exists($sitePath . $filename) && !is_dir($sitePath . $filename)) {
                $_SERVER['SCRIPT_FILENAME'] = $sitePath . $filename;
                $_SERVER['SCRIPT_NAME'] = $filename;
                return $sitePath . $filename;
            }
        }

        // Fallback
        $_SERVER['SCRIPT_FILENAME'] = $sitePath . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        // Check for multisite folder structure
        if (file_exists($sitePath . '/sites/sites.php')) {
            include $sitePath . '/sites/sites.php';

            if (!isset($sites)) {
                return $sitePath . '/index.php';
            }

            $sites = array_keys($sites);
            $urlPath = $siteName . '.' . $GLOBALS['valetConfig']['domain'];
            $subfolder = explode('/', $uri)[1];
            $isInUrlPath = in_array($urlPath, $sites);
            $isInSubFolderPath = in_array($subfolder, $sites);


            if ($isInUrlPath === false && $isInSubFolderPath === false) {
                return $sitePath . '/index.php';
            }

            if ($isInSubFolderPath) {
                $this->forceTrailingSlash($uri);
            }

            $subfolder = '/' . explode('/', $uri)[1];

            if ($isInSubFolderPath) {
                $_SERVER['SCRIPT_NAME'] = $subfolder . '/index.php';
                $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
            }

            $_SERVER['SCRIPT_FILENAME'] = $sitePath . $subfolder . '/index.php';
            unset($_SERVER['DOCUMENT_URI']);
        }

        return $sitePath . '/index.php';
    }


    /**
     * Redirect to uri with trailing slash.
     *
     * @param  string $uri
     * @return string
     */
    private function forceTrailingSlash($uri)
    {
        if (substr($uri, -1) !== '/') {
            header('Location: ' . $uri . '/');
            die;
        }

        return $uri;
    }
}
