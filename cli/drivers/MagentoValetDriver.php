<?php

class MagentoValetDriver extends BasicValetDriver
{
    /**
     * Determine if the driver serves the request.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return bool
     */
    public function serves($sitePath, $siteName, $uri)
    {
        return is_dir($sitePath.'/app/code/core/Mage');
    }

    public function configure($devtools, $url) {
        info('Configuring Magento...');

        $sitePath = getcwd();

        if(!file_exists($sitePath.'/app/etc/local.xml')) {
            info('local.xml missing. Installing default local.xml...');
            $devtools->files->putAsUser(
                $sitePath.'/app/etc/local.xml',
                str_replace(
                    'DBNAME',
                    $devtools->mysql->getDirName(),
                    $devtools->files->get(__DIR__.'/../stubs/magento/local.xml')
                )
            );
        }

        info('Setting base url...');
        $devtools->cli->quietlyAsUser('n98-magerun config:set web/unsecure/base_url ' . $url . '/');
        $devtools->cli->quietlyAsUser('n98-magerun config:set web/secure/base_url ' . $url . '/');

        info('Flushing cache...');
        $devtools->cli->quietlyAsUser('n98-magerun cache:flush');

        info('Configured Magento');
    }
    
    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string
     */
    public function frontControllerPath($sitePath, $siteName, $uri)
    {
        $this->loadServerEnvironmentVariables($sitePath, $siteName);

        return $sitePath.'/index.php';
    }
}
