<?php

class Magento2ValetDriver extends ValetDriver
{
    public function configure($devtools, $url) {
        info('Configuring Magento 2...');
        $devtools->cli->quietlyAsUser('chmod +x bin/magento');

        $sitePath = getcwd();

        if(!$this->envExists($sitePath)) {
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

        if(!$this->moduleConfigExists($sitePath)) {
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

    public function envExists($sitePath) {
        return file_exists($sitePath.'/app/etc/env.php');
    }

    public function moduleConfigExists($sitePath) {
        return file_exists($sitePath.'/app/etc/config.php');
    }

    public function installed($sitePath) {
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
        $isMagentoStatic = false;
        $resource = $uri;
        
        if(strpos($uri,'/errors') === 0 && file_exists($sitePath.'/pub'.$uri)) {
            return $sitePath.'/pub'.$uri;
        }

        if(strpos($uri,'/pub') === 0 && file_exists($sitePath.'/setup'.$uri)) {
            return $sitePath.'/setup'.$uri;
        }

        if (strpos($uri, '/static/') !== false) {
            $isMagentoStatic = true;
        }

        if (!$isMagentoStatic && strpos($uri, '/media/') === false) {
            return false;
        }

        if ($isMagentoStatic) {
            $resource = preg_replace('#static(/version[0-9]+)?/#', '', $uri, 1);
            $uri = '/static' . $resource;
        }

        if (strpos($uri, '/js-translation.json') !== false) {
            header('Cache-Control: no-store, must-revalidate');
        }

        if (file_exists($staticFilePath = $sitePath . '/pub' . $uri)) {
            return $staticFilePath;
        }

        if (strpos($uri, '/static/') === 0) {
            $_GET['resource'] = $resource;
            include($sitePath . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'static.php');
            exit;
        }

        if (strpos($uri, '/media/') === 0) {
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

        if(isset($_GET['profile'])) {
            $_SERVER['MAGE_PROFILER'] = 'html';
        }
        
        if(strpos($uri, '/errors') === 0) {
            $file = $sitePath . '/pub' . $uri;
            if (file_exists($file)) {
                return $file;
            }
            return $sitePath . '/pub/errors/404.php';
        }

        if($uri === '/setup') {
            Header('HTTP/1.1 301 Moved Permanently');
            Header('Location: http://' . $_SERVER['HTTP_HOST'] . $uri . '/');
            die;
        }

        if(strpos($uri, '/setup') === 0) {
            $_SERVER['SCRIPT_FILENAME'] = $sitePath.'/setup/index.php';
            $_SERVER['SCRIPT_NAME'] = '/index.php';
            $_SERVER['DOCUMENT_ROOT'] = $sitePath.'/setup/';
            $_SERVER['REQUEST_URI'] = str_replace('/setup', '', $_SERVER['REQUEST_URI']);

            if($_SERVER['REQUEST_URI'] === '') {
                $_SERVER['REQUEST_URI'] = '/';
            }
            return $sitePath.'/setup/index.php';
        }

        if(!$this->installed($sitePath)) {
            http_response_code(404);
            require __DIR__.'/../templates/magento2.php';
            exit;
        }

        if(strpos($uri, '/dev/tests/acceptance/utils/command.php') !== false) {
            return $sitePath . '/dev/tests/acceptance/utils/command.php';
        }

        $_SERVER['DOCUMENT_ROOT'] = $sitePath;

        return $sitePath . '/pub/index.php';
    }

    /**
     * @param $app
     * @param $config
     * @param $domain
     * @param string $edition
     * @throws Exception
     */
    public function createWebsite($app, $config, $domain, $edition = 'community') {

        $app->runCommand('db create '. $domain);
        // Magento2 2.3 requires at least PHP 7.1
        $app->runCommand('use 7.1');

        $version = ($edition == 'enterprise') ? 'magento/project-enterprise-edition' : 'magento/project-community-edition';
        $paths = $config['paths'];
        $sitesDirectory = end($paths);
        $directory = rtrim($sitesDirectory, '/') . DIRECTORY_SEPARATOR . ltrim($domain, '/');
        $url = 'https://' . rtrim($domain, '/') . '.' . rtrim($config['domain'], '/') . '/';

        if (file_exists($directory)) {
            throw new Exception('Directory already exists.');
        }
        $app->runCommand('secure '. $domain);

        $cli = new \Valet\CommandLine();
        $cli->runAsUser("mkdir {$directory}");

        info('Composer Create Project...');
        $cli->runAsUser("composer create-project --repository-url=https://repo.magento.com/ {$version} {$directory}");

        info('Deploy Sample Data...');
        $cli->runAsUser("n98-magerun2 sampledata:deploy");

        info('Installing Magento 2...');
        $install = [
            'admin' => [
                'firstname' => 'Admin',
                'lastname' => 'Admin',
                'email' => "info@{$domain}.{$config['domain']}",
                'user' => 'admin',
                'password' => "{$domain}123",
                'frontname' => 'admin'
            ]
        ];

        $cli->runAsUser("n98-magerun2 setup:install --root-dir={$directory} --backend-frontname={$install['admin']['frontname']} --admin-firstname='{$install['admin']['firstname']}' --admin-lastname='{$install['admin']['lastname']}' --admin-email='{$install['admin']['email']}' --admin-user='{$install['admin']['user']}' --admin-password='{$install['admin']['password']}' --base-url='{$url}' --db-host='localhost' --db-name='{$domain}' --db-user='root' --db-password='{$this->getRootPassword($config)}' --use-rewrites=1 --session-save=files --use-sample-data");



        $cli->runAsUser('n98-magerun2 setup:upgrade');

        info('Enabling Developer Settings...');
        $cli->runAsUser('n98-magerun2 deploy:mode:set developer');
        $cli->runAsUser('n98-magerun2 cache:disable layout block_html collections full_page');

        $developerConfig = [
            'dev/static/sign' => '0',
            'dev/css/merge_css_files' => '0',
            'dev/js/merge_files' => '0',
            'dev/js/minify_files' => '0',
            'dev/js/enable_js_bundling' => '0',
            'system/smtp/disable' => '1',
        ];

        foreach ($developerConfig as $path => $value) {
            $cli->runAsUser("n98-magerun2 config:store:set {$path} {$value}");
        }

        info("Admin information:");
        info("\turl: {$url}{$install['admin']['frontname']} ");
        info("\tuser: {$install['admin']['user']} ");
        info("\tpassword: {$install['admin']['password']} ");

    }

    /**
     * Returns the stored password from the config. If not configured returns the default root password.
     *
     * @param $config
     * @return string
     */
    private function getRootPassword($config)
    {
        if (isset($config['mysql']) && isset($config['mysql']['password'])) {
            return $config['mysql']['password'];
        }

        return \Valet\Mysql::MYSQL_ROOT_PASSWORD;
    }
}
