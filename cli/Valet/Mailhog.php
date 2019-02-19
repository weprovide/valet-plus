<?php

namespace Valet;

class Mailhog extends AbstractService
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
            info('[mailhog] already installed');
        } else {
            $this->brew->installOrFail('mailhog');
        }
        $this->setEnabled(self::STATE_ENABLED);
        $this->restart();
    }

    /**
     * Returns wether mailhog is installed or not.
     *
     * @return bool
     */
    function installed()
    {
        return $this->brew->installed('mailhog');
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

        info('[mailhog] Restarting');
        $this->cli->quietlyAsUser('brew services restart mailhog');
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

        info('[mailhog] Stopping');
        $this->cli->quietlyAsUser('brew services stop mailhog');
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
