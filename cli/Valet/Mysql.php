<?php

namespace Valet;

use DateTime;
use MYSQLI_ASSOC;
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

    function supportedVersions() {
        return ['mysql', 'mariadb'];
    }

    function verifyType($type) {
        if(!in_array($type, $this->supportedVersions())) {
            throw new DomainException('Invalid Mysql type given. Available: mysql/mariadb');
        }
    }

    function installedVersion() {
        $versions = $this->supportedVersions();
        foreach($versions as $version) {
            if($this->brew->installed($version)) {
                return $version;
            }
        }

        return false;
    }

    /**
     * Install the service..
     *
     * @param $type
     * @return void
     */
    function install($type = 'mysql')
    {
        $this->verifyType($type);
        $currentlyInstalled = $this->installedVersion();
        if($currentlyInstalled) {
            $type = $currentlyInstalled;
        }

        $this->removeConfiguration($type);
        $this->files->copy(__DIR__.'/../stubs/limit.maxfiles.plist', static::MAX_FILES_CONF);
        $this->cli->quietly('launchctl load -w '.static::MAX_FILES_CONF);

        if (!$this->installedVersion()) {
            $this->brew->installOrFail($type);
        }

        if (!$this->brew->installed('mysql-utilities')) {
            $this->brew->installOrFail('mysql-utilities');
        }

        $this->stop();
        $this->installConfiguration($type);
        $this->restart();
    }

    /**
     * Install the configuration files.
     *
     * @param string $type
     * @return void
     */
    function installConfiguration($type = 'mysql')
    {
        info('['.$type.'] Configuring');

        $this->files->chmodPath(static::MYSQL_DIR, 0777);

        if (! $this->files->isDir($directory = static::MYSQL_CONF_DIR)) {
            $this->files->mkdirAsUser($directory);
        }

        $contents = $this->files->get(__DIR__.'/../stubs/my.cnf');
        if($type === 'mariadb') {
            $contents = str_replace('show_compatibility_56=ON', '', $contents);
        }

        $this->files->putAsUser(
            static::MYSQL_CONF,
            str_replace('VALET_HOME_PATH', VALET_HOME_PATH, $contents)
        );
    }

    function removeConfiguration($type = 'mysql') {
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
        $version = $this->installedVersion() ?: 'mysql';
        info('['.$version.'] Restarting');
        $this->cli->quietlyAsUser('brew services restart '.$version);
    }

    /**
     * Stop the Nginx service.
     *
     * @return void
     */
    function stop()
    {
        $version = $this->installedVersion() ?: 'mysql';
        info('['.$version.'] Stopping');

        $this->cli->quietly('sudo brew services stop '.$version);
        $this->cli->quietlyAsUser('brew services stop '.$version);
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
     * @return boolean|mysqli
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
     * @return boolean|string
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
     * @return bool|string
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

    function getDatabases() {
        $link = $this->getConnection();
        $sql = mysqli_real_escape_string($link, 'SHOW DATABASES');
        $result = $link->query($sql);

        if(!$result) {
            return false;
        }

        $databases = [];

        foreach($result->fetch_all(MYSQLI_ASSOC) as $row) {
            if($row['Database'] === 'sys' || $row['Database'] === 'performance_schema' || $row['Database'] === 'information_schema' || $row['Database'] === 'mysql') {
                continue;
            }
            
            $databases[] = [$row['Database']];
        }

        $result->free();

        return $databases;
    }

    function listDatabases() {
        $databases = $this->getDatabases();
        table(['Database'], $databases);
    }

    function importDatabase($file, $database) {
        $database = $database ?: $this->getDirName();
        $this->createDatabase($database);
        $gzip = ' | ';
        if (stristr($file, '.gz')) {
          $gzip = ' | gzip -cd | ';
        }
        $this->cli->passthru('pv ' . escapeshellarg($file) . $gzip . 'mysql ' . escapeshellarg($database));
    }

    function reimportDatabase($file, $database) {
        $database = $database ?: $this->getDirName();
        $this->dropDatabase($database);
        $this->createDatabase($database);

        $this->importDatabase($file, $database);
    }

    function exportDatabase($filename, $database) {
        $database = $database ?: $this->getDirName();

        if(!$filename || $filename === '-') {
            $filename = $database.'-'.date('Y-m-d-His', time());
        }

        if(!stristr($filename, '.sql')) {
            $filename = $filename.'.sql.gz';
        }

        if(!stristr($filename, '.gz')) {
            $filename = $filename.'.gz';
        }

        $this->cli->passthru('mysqldump ' . escapeshellarg($database) . ' | gzip > ' . escapeshellarg($filename ?: $database));

        return [
            'database' => $database,
            'filename' => $filename
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
