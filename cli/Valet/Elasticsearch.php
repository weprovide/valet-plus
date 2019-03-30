<?php

namespace Valet;

class Elasticsearch
{
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
        $this->brew->installOrFail('elasticsearch@5.6');
        $this->restart();
    }

    function installed() {
        return $this->brew->installed('elasticsearch@5.6');
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
        $this->cli->quietlyAsUser('brew services restart elasticsearch@5.6');
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
        $this->cli->quietly('sudo brew services stop elasticsearch@5.6');
        $this->cli->quietlyAsUser('brew services stop elasticsearch@5.6');
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
