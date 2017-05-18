<?php

namespace Valet;

use DomainException;

class Mysql
{
    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;
    const MYSQL_CONF_DIR = '/usr/local/etc';
    const MYSQL_CONF = '/usr/local/etc/my.cnf';

    /**
     * Create a new Nginx instance.
     *
     * @param  Brew  $brew
     * @param  CommandLine  $cli
     * @param  Filesystem  $files
     * @param  Configuration  $configuration
     * @param  Site  $site
     * @return void
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
     * Install the configuration files for Nginx.
     *
     * @return void
     */
    function install()
    {
        $this->removeConfiguration();
        if (!$this->brew->installed('mysql')) {
            $this->brew->installOrFail('mysql');
        }

        if (!$this->brew->installed('mysql-utilities')) {
            $this->brew->installOrFail('mysql-utilities');
        }

        $this->cli->quietly('brew services stop mysql');
        $this->stop();
        $this->installConfiguration();
        $this->restart();
        $this->setRootPassword();
    }

    /**
     * Install the Mysql configuration file.
     *
     * @return void
     */
    function installConfiguration()
    {
        info('Installing Mysql configuration...');

        if (! $this->files->isDir($directory = static::MYSQL_CONF_DIR)) {
            $this->files->mkdirAsUser($directory);
        }

        $contents = $this->files->get(__DIR__.'/../stubs/my.cnf');

        $this->files->putAsUser(
            static::MYSQL_CONF,
            str_replace('VALET_HOME_PATH', VALET_HOME_PATH, $contents)
        );
    }

    function removeConfiguration() {
        info('Removing Mysql configuration...');

        $this->files->unlink(static::MYSQL_CONF);
        $this->files->unlink(static::MYSQL_CONF.'.default');
    }

    /**
     * Restart the Mysql service.
     *
     * @return void
     */
    function restart()
    {
        info('Restarting Mysql...');
        $this->cli->quietly('brew services restart mysql');
    }

    /**
     * Stop the Nginx service.
     *
     * @return void
     */
    function stop()
    {
        info('Stopping Mysql....');

        $this->cli->quietly('sudo brew services stop mysql');
    }

    function setRootPassword() {
        $this->cli->quietly("mysqladmin -u root --password='' password root");
    }

    /**
     * Prepare Mysql for uninstallation.
     *
     * @return void
     */
    function uninstall()
    {
        $this->stop();
    }
}
