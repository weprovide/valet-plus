<?php

class Magento2ValetDriver extends ValetDriver
{
    public function configure($devtools, $url)
    {
        info('Configuring Magento 2...');
        $devtools->cli->quietlyAsUser('chmod +x bin/magento');

        $sitePath = getcwd();

        if (!$this->envExists($sitePath)) {
            info('env.php missing. Installing default env.php...');
            $devtools->files->putAsUser(
                $sitePath.'/app/etc/env.php',
                str_replace(
                    'DBNAME',
                    $devtools->mysql->getDirName(),
                    $devtools->files->get(__DIR__.'/../stubs/magento2/env.php')
                )
            );
        }

        if (!$this->moduleConfigExists($sitePath)) {
            info('config.php missing. Enabling all modules...');
            $devtools->cli->quietlyAsUser('bin/magento module:enable --all');
        }

        info('Setting base url...');
        $devtools->cli->quietlyAsUser('n98-magerun2 config:store:set web/unsecure/base_url ' . $url . '/');
        $devtools->cli->quietlyAsUser('n98-magerun2 config:store:set web/secure/base_url ' . $url . '/');

        info('Setting elastic search hostname...');
        $devtools->cli->quietlyAsUser('n98-magerun2 config:store:set catalog/search/elasticsearch_server_hostname 127.0.0.1');

        info('Enabling URL rewrites...');
        $devtools->cli->quietlyAsUser('n98-magerun2 config:store:set web/seo/use_rewrites 1');

        info('Flushing cache...');
        $devtools->cli->quietlyAsUser('n98-magerun2 cache:flush');

        info('Configured Magento 2');
    }

    /**
     * Determine if the driver serves the request.
     *
     * @param  string $sitePath
     * @param  string $siteName
     * @param  string $uri
     * @return bool
     */
    public function serves($sitePath, $siteName, $uri)
    {
        return file_exists($sitePath . '/pub/index.php') &&
            file_exists($sitePath . '/bin/magento');
    }

    public function envExists($sitePath)
    {
        return file_exists($sitePath.'/app/etc/env.php');
    }

    public function moduleConfigExists($sitePath)
    {
        return file_exists($sitePath.'/app/etc/config.php');
    }

    public function installed($sitePath)
    {
        return $this->envExists($sitePath) && $this->moduleConfigExists($sitePath);
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
        $this->loadServerEnvironmentVariables($sitePath, $siteName);

        if (!preg_match('#^(pub|setup|update)#', $uri)) {
            $uri = '/pub' . $uri;
        }

        if (preg_match('#^/pub/media/(downloadable|customer|import|custom_options|theme_customization/.*\.xml)#', $uri)) {
            include($sitePath . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'errors/404.php');
            exit;
        }

        header('X-Frame-Options: SAMEORIGIN');

        if (strpos($uri, '/js-translation.json') !== false) {
            header('Cache-Control: no-store, must-revalidate');
        }

        if (strpos($uri, '/pub/static') === 0) {
            $uri = preg_replace('#^/pub/static/(version\d*/)?#', '/pub/static/', $uri, 1);
            if (preg_match('#\.(zip|gz|gzip|bz2|csv|xml)$#', $uri)) {
                header('Cache-Control: no-store, must-revalidate');
            }
        }

        if ($this->isActualFile($sitePath.$uri)) {
            return $sitePath.$uri;
        }

        if (strpos($uri, '/pub/static/') === 0) {
            $_GET['resource'] = preg_replace('#^/pub/static#','',$uri);
            include($sitePath . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'static.php');
            exit;
        }

        if (strpos($uri, '/pub/media/') === 0) {
            include($sitePath . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'get.php');
            exit;
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
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];

        if (isset($_GET['profile'])) {
            $_SERVER['MAGE_PROFILER'] = 'html';
        }

        if (strpos($uri, '/errors') === 0) {
            $file = $sitePath . '/pub' . $uri;
            if (file_exists($file)) {
                return $file;
            }
            return $sitePath . '/pub/errors/404.php';
        }

        if ($uri === '/setup') {
            Header('HTTP/1.1 301 Moved Permanently');
            Header('Location: http://' . $_SERVER['HTTP_HOST'] . $uri . '/');
            die;
        }

        if (strpos($uri, '/setup') === 0) {
            $_SERVER['SCRIPT_FILENAME'] = $sitePath.'/setup/index.php';
            $_SERVER['SCRIPT_NAME'] = '/index.php';
            $_SERVER['DOCUMENT_ROOT'] = $sitePath.'/setup/';
            $_SERVER['REQUEST_URI'] = str_replace('/setup', '', $_SERVER['REQUEST_URI']);

            if ($_SERVER['REQUEST_URI'] === '') {
                $_SERVER['REQUEST_URI'] = '/';
            }
            return $sitePath.'/setup/index.php';
        }

        if (!$this->installed($sitePath)) {
            http_response_code(404);
            require __DIR__.'/../templates/magento2.php';
            exit;
        }

        if (strpos($uri, '/dev/tests/acceptance/utils/command.php') !== false) {
            return $sitePath . '/dev/tests/acceptance/utils/command.php';
        }

        $_SERVER['DOCUMENT_ROOT'] = $sitePath . '/pub/';

        return $sitePath . '/pub/index.php';
    }
}
