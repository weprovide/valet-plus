<?php

namespace Valet;

class RabbitMq extends AbstractService
{
    var $brew;
    var $cli;
    var $files;
    var $site;

    /**
     * Create a new instance.
     *
     * @param  Brew          $brew
     * @param  CommandLine   $cli
     * @param  Filesystem    $files
     * @param  Configuration $configuration
     * @param  Site          $site
     */
    function __construct(
        Brew $brew,
        CommandLine $cli,
        Filesystem $files,
        Configuration $configuration,
        Site $site
    ) {
        $this->cli   = $cli;
        $this->brew  = $brew;
        $this->site  = $site;
        $this->files = $files;
        parent::__construct($configuration);
    }

    /**
     * Install the service.
     *
     * @return void
     */
    function install()
    {
        if ($this->installed()) {
            info('[rabbitmq] already installed');
        } else {
            $this->brew->installOrFail('rabbitmq');
            $this->cli->quietly('sudo brew services stop rabbitmq');
        }
        $this->setEnabled(self::STATE_ENABLED);
        $this->restart();
    }

    /**
     * Returns wether rabbitmq is installed or not.
     *
     * @return bool
     */
    function installed()
    {
        return $this->brew->installed('rabbitmq');
    }

    /**
     * Restart the service.
     *
     * @return void
     */
    function restart()
    {
        if (!$this->installed() || !$this->isEnabled()) {
            return;
        }

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
        if (!$this->installed()) {
            return;
        }

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
