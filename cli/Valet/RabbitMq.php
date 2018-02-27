<?php

namespace Valet;

class RabbitMq
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
        if (!$this->brew->installed('rabbitmq')) {
            $this->brew->installOrFail('rabbitmq');
            $this->cli->quietly('sudo brew services stop rabbitmq');
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
        info('[rabbitmq] Restarting');
        $this->cli->quietlyAsUser('brew services restart rabbitmq');
    }

    /**
     * Stop the service.
     *
     * @return void
     */
    function stop()
    {
        info('[rabbitmq] Stopping');
        $this->cli->quietlyAsUser('brew services stop rabbitmq');
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
