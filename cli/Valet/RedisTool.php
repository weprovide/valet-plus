<?php

namespace Valet;

class RedisTool extends AbstractService
{
    public $brew;
    public $cli;
    public $files;
    public $site;

    const REDIS_CONF = 'etc/redis.conf';
    /**
     * @var Architecture
     */
    private $architecture;

    /**
     * Create a new instance.
     *
     * @param Architecture $architecture
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Configuration $configuration
     * @param Site $site
     */
    public function __construct(
        Architecture $architecture,
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
        $this->architecture = $architecture;
        parent::__construct($configuration);
        $this->configuration = $configuration;
    }

    /**
     * Install the service.
     *
     * @return void
     */
    public function install()
    {
        if ($this->installed()) {
            info('[redis] already installed');
        } else {
            $this->brew->installOrFail('redis');
            $this->cli->quietly('sudo brew services stop redis');
        }

        $this->installConfiguration();
        $this->setEnabled(self::STATE_ENABLED);
        $this->restart();
    }

    /**
     * Returns wether redis is installed or not.
     *
     * @return bool
     */
    public function installed()
    {
        return $this->brew->installed('redis');
    }

    /**
     * Install the configuration file.
     *
     * @return void
     */
    public function installConfiguration()
    {
        $contents = $this->files->get(__DIR__.'/../stubs/redis.conf');

        $this->files->put(
            $this->architecture->getBrewPath() . "/" . static::REDIS_CONF,
            str_replace('BREW_PATH', $this->architecture->getBrewPath(), $contents)
        );
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

        info('[redis] Restarting');
        $this->cli->quietlyAsUser('brew services restart redis');
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

        info('[redis] Stopping');
        $this->cli->quietly('sudo brew services stop redis');
        $this->cli->quietlyAsUser('brew services stop redis');
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
