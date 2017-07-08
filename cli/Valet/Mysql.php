<?php

namespace Valet;

use DomainException;
use mysqli;

class Mysql
{
    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;
    const MYSQL_CONF_DIR = '/usr/local/etc';
    const MYSQL_CONF = '/usr/local/etc/my.cnf';
    const MAX_FILES_CONF = '/Library/LaunchDaemons/limit.maxfiles.plist';
    const MYSQL_DIR = '/usr/local/var/mysql';

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
     * Install the configuration files for Mysql.
     *
     * @return void
     */
    function install()
    {
        $this->removeConfiguration();
        $this->files->copy(__DIR__.'/../stubs/limit.maxfiles.plist', static::MAX_FILES_CONF);

        if (!$this->brew->installed('mysql')) {
            $this->brew->installOrFail('mysql');
        }

        if (!$this->brew->installed('mysql-utilities')) {
            $this->brew->installOrFail('mysql-utilities');
        }

        $this->stop();
        $this->installConfiguration();
        $this->restart();
    }

    /**
     * Install the Mysql configuration file.
     *
     * @return void
     */
    function installConfiguration()
    {
        info('Installing mysql configuration...');

        // TODO: Fix this, currently needed because MySQL will crash otherwise
        $this->files->chmodPath(static::MYSQL_DIR, 0777);

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
        info('Removing mysql configuration...');

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
        info('Restarting mysql...');
        $this->cli->quietlyAsUser('brew services restart mysql');
    }

    /**
     * Stop the Nginx service.
     *
     * @return void
     */
    function stop()
    {
        info('Stopping mysql....');

        $this->cli->quietly('sudo brew services stop mysql');
        $this->cli->quietlyAsUser('brew services stop mysql');
    }

    function setRootPassword() {
        $this->cli->quietlyAsUser("mysqladmin -u root --password='' password root");
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

    /**
     * Return Mysql connection
     *
     * @return void
     */
    function getConnection() {
        // Create connection
        $link = new mysqli('localhost', 'root', 'root');
        // Check connection
        if ($link->connect_error) {
            warning('Failed to connect to database');
            return false;
        }

        return $link;
    }

    function getDirName($name = '') {
        if($name) {
            return $name;
        }
        
        $gitDir = $this->cli->runAsUser('git rev-parse --show-toplevel 2>/dev/null');

        if($gitDir) {
            return trim(basename($gitDir));
        }

        return trim(basename(getcwd()));
    }

    /**
     * Create Mysql database
     *
     * @param string $name
     * @return void
     */
    function createDatabase($name) {
        $name = $this->getDirName($name);
        $link = $this->getConnection();
        $sql = mysqli_real_escape_string($link, 'CREATE DATABASE `' . $name . '`');

        if(!$link->query($sql)) {
            return false;
        }

        return $name;
    }

    /**
     * Create Mysql database
     *
     * @param string $name
     * @return void
     */
    function dropDatabase($name) {
        $name = $this->getDirName($name);
        $link = $this->getConnection();
        $sql = mysqli_real_escape_string($link, 'DROP DATABASE `' . $name . '`');
        if(!$link->query($sql)) {
            return false;
        }

        return $name;
    }

    function joinPaths() {
        $args = func_get_args();
        $paths = array();
        foreach ($args as $arg) {
            $paths = array_merge($paths, (array)$arg);
        }

        $paths = array_map(create_function('$p', 'return trim($p, "/");'), $paths);
        $paths = array_filter($paths);
        return join('/', $paths);
    }

    function importDatabase($file, $database) {
        $database = $database ?: $this->getDirName();
        $this->cli->passthru('pv ' . escapeshellarg($file) . ' | mysql ' . escapeshellarg($database));
    }

    function exportDatabase($database, $filename) {
        $database = $database ?: $this->getDirName();
        $this->cli->passthru('mysqldump ' . escapeshellarg($database) . ' > ' . escapeshellarg($filename ?: $database) . '.sql');

        return [
            'database' => $database,
            'filename' => ($filename ?: $database).'.sql'
            ];
    }

    function openSequelPro($name = '') {
        $name = $this->getDirName($name);
        $tmpName = tempnam(sys_get_temp_dir(), 'sequelpro').'.spf';

        $contents = $this->files->get(__DIR__.'/../stubs/sequelpro.spf');

        $this->files->putAsUser(
            $tmpName,
            str_replace(['DB_NAME', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_PORT'], [$name, '127.0.0.1', 'root', 'root', '3306'], $contents)
        );

        $this->cli->quietly('open ' . $tmpName);
    }
}
