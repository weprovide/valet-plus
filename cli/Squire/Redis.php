<?php

namespace Squire;

class Redis
{
    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;
    const REDIS_CONF = '/usr/local/etc/redis.conf';

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
        if (!$this->brew->installed('redis')) {
            $this->brew->installOrFail('redis');
            $this->cli->quietly('sudo brew services stop redis');
        }

        $this->installConfiguration();
        $this->restart();
    }

    /**
     * Install the configuration file.
     *
     * @return void
     */
    function installConfiguration()
    {
        $this->files->copy(__DIR__.'/../stubs/redis.conf', static::REDIS_CONF);
    }

    /**
     * Restart the service.
     *
     * @return void
     */
    function restart()
    {
        info('[redis] Restarting');
        $this->cli->quietlyAsUser('brew services restart redis');
    }

    /**
     * Stop the service.
     *
     * @return void
     */
    function stop()
    {
        info('[redis] Stopping');
        $this->cli->quietly('sudo brew services stop redis');
        $this->cli->quietlyAsUser('brew services stop redis');
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
