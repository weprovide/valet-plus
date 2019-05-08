<?php

namespace Valet;

class Elasticsearch
{
    const NGINX_CONFIGURATION_STUB = __DIR__ . '/../stubs/elasticsearch.conf';
    CONST NGINX_CONFIGURATION_PATH = '/usr/local/etc/nginx/valet/elasticsearch.conf';

    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;

    /**
     * Create a new instance.
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
     * Install the service.
     *
     * @return void
     */
    function install()
    {
        if ($this->installed()) {
            info('[elasticsearch] already installed');
            return;
        }

        $this->cli->quietlyAsUser('brew cask install java');
        $this->brew->installOrFail('elasticsearch@2.4');
        $this->restart();
    }

    function installed() {
        return $this->brew->installed('elasticsearch@2.4');
    }

    /**
     * Restart the service.
     *
     * @return void
     */
    function restart()
    {
        if (!$this->installed()) {
            return;
        }

        info('[elasticsearch] Restarting');
        $this->cli->quietlyAsUser('brew services restart elasticsearch@2.4');
    }

    /**
     * Stop the service.
     *
     * @return void
     */
    function stop()
    {
        if (!$this->installed()) {
            return;
        }

        info('[elasticsearch] Stopping');
        $this->cli->quietly('sudo brew services stop elasticsearch@2.4');
        $this->cli->quietlyAsUser('brew services stop elasticsearch@2.4');
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

    function updateDomain($domain)
    {
        $this->files->putAsUser(
            self::NGINX_CONFIGURATION_PATH,
            str_replace(
                ['VALET_DOMAIN'],
                [$domain],
                $this->files->get(self::NGINX_CONFIGURATION_PATH)
            )
        );
    }
}
