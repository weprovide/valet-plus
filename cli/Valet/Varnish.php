<?php

namespace Valet;

class Varnish
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
        if (!$this->brew->installed('varnish')) {
            $this->brew->installOrFail('varnish');
            $this->cli->quietly('brew services stop varnish');
        }
        $this->restart();
    }

    /**
     * Restart the service.
     *
     * @return void
     */
    function restart()
    {
        info('[varnish] Restarting');
        $this->cli->quietlyAsUser('brew services restart varnish');
    }

    /**
     * Stop the service.
     *
     * @return void
     */
    function stop()
    {
        info('[varnish] Stopping');
        $this->cli->quietlyAsUser('brew services stop varnish');
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
