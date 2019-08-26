<?php

namespace Valet;

class RabbitMq extends AbstractService
{
    public $brew;
    public $cli;
    public $files;
    public $site;

    /**
     * Create a new instance.
     *
     * @param  Brew          $brew
     * @param  CommandLine   $cli
     * @param  Filesystem    $files
     * @param  Configuration $configuration
     * @param  Site          $site
     */
    public function __construct(
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
    public function install()
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
    public function installed()
    {
        return $this->brew->installed('rabbitmq');
    }

    /**
     * Restart the service.
     *
     * @return void
     */
    public function restart()
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
    public function stop()
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
    public function uninstall()
    {
        $this->stop();
    }
}
