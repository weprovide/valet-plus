<?php

namespace Squire;

use DomainException;

class Nginx
{
    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;
    const NGINX_CONF = '/usr/local/etc/nginx/nginx.conf';

    /**
     * Create a new Nginx instance.
     *
     * @param  Brew $brew
     * @param  CommandLine $cli
     * @param  Filesystem $files
     * @param  Configuration $configuration
     * @param  Site $site
     */
    function __construct(Brew $brew, CommandLine $cli, Filesystem $files,
                         Configuration $configuration, Site $site)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->site = $site;
        $this->files = $files;
        $this->configuration = $configuration;
    }

    /**
     * Install service.
     *
     * @return void
     */
    function install()
    {
        if (!$this->brew->hasInstalledNginx()) {
            $this->brew->installOrFail('nginx', ['--with-http2']);
        }

        $this->installConfiguration();
        $this->installServer();
        $this->installNginxDirectory();
    }

    /**
     * Install the configuration files.
     *
     * @return void
     */
    function installConfiguration()
    {
        $contents = $this->files->get(__DIR__.'/../stubs/nginx.conf');

        $this->files->putAsUser(
            static::NGINX_CONF,
            str_replace(['SQUIRE_USER', 'SQUIRE_HOME_PATH'], [user(), SQUIRE_HOME_PATH], $contents)
        );
    }

    /**
     * Install the Squire server configuration file.
     *
     * @return void
     */
    function installServer()
    {
        $this->files->ensureDirExists('/usr/local/etc/nginx/squire');

        $this->files->putAsUser(
            '/usr/local/etc/nginx/squire/squire.conf',
            str_replace(
                ['SQUIRE_HOME_PATH', 'SQUIRE_SERVER_PATH', 'SQUIRE_STATIC_PREFIX'],
                [SQUIRE_HOME_PATH, SQUIRE_SERVER_PATH, SQUIRE_STATIC_PREFIX],
                $this->files->get(__DIR__.'/../stubs/squire.conf')
            )
        );

        $this->files->putAsUser(
            '/usr/local/etc/nginx/squire/mailhog.conf',
            $this->files->get(__DIR__.'/../stubs/mailhog.conf')
        );

        $this->files->putAsUser(
            '/usr/local/etc/nginx/squire/elasticsearch.conf',
            $this->files->get(__DIR__.'/../stubs/elasticsearch.conf')
        );

        $this->files->putAsUser(
            '/usr/local/etc/nginx/fastcgi_params',
            $this->files->get(__DIR__.'/../stubs/fastcgi_params')
        );
    }

    /**
     * Install the configuration directory to the ~/.squire directory.
     *
     * This directory contains all site-specific Nginx servers.
     *
     * @return void
     */
    function installNginxDirectory()
    {
        if (! $this->files->isDir($nginxDirectory = SQUIRE_HOME_PATH.'/Nginx')) {
            $this->files->mkdirAsUser($nginxDirectory);
        }

        $this->files->putAsUser($nginxDirectory.'/.keep', "\n");

        $this->rewriteSecureNginxFiles();
    }

    /**
     * Check nginx.conf for errors.
     */
    private function lint()
    {
        $this->cli->quietly(
            'sudo nginx -c '.static::NGINX_CONF.' -t',
            function ($exitCode, $outputMessage) {
                throw new DomainException("Nginx cannot start, please check your nginx.conf [$exitCode: $outputMessage].");
            }
        );
    }

    /**
     * Generate fresh Nginx servers for existing secure sites.
     *
     * @return void
     */
    function rewriteSecureNginxFiles()
    {
        $domain = $this->configuration->read()['domain'];

        $this->site->resecureForNewDomain($domain, $domain);
    }

    /**
     * Restart the service.
     *
     * @return void
     */
    function restart()
    {
        $this->lint();

        $this->brew->restartService($this->brew->nginxServiceName());
    }

    /**
     * Stop the service.
     *
     * @return void
     */
    function stop()
    {
        info('[nginx] Stopping');

        $this->cli->quietly('sudo brew services stop '. $this->brew->nginxServiceName());
    }

    /**
     * Prepare for uninstallation.
     *
     * @return void
     */
    function uninstall()
    {
        $this->stop();
    }
}
