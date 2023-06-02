<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use DomainException;
use Valet\Brew;
use Valet\CommandLine;
use Valet\Filesystem;
use function Valet\info;

class Elasticsearch
{
    /** @var string */
    protected const ES_DEFAULT_VERSION = 'opensearch';
    /** @var string[] */
    protected const ES_SUPPORTED_VERSIONS = ['opensearch', 'elasticsearch@6'];

    /** @var Brew */
    protected $brew;
    /** @var CommandLine */
    protected $cli;
    /** @var Filesystem */
    protected $files;

    /**
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     */
    public function __construct(
        Brew        $brew,
        CommandLine $cli,
        Filesystem  $files
    ) {
        $this->brew  = $brew;
        $this->cli   = $cli;
        $this->files = $files;
    }

    /**
     * Returns supported elasticsearch versions.
     *
     * @return string[]
     */
    public function getSupportedVersions()
    {
        return static::ES_SUPPORTED_VERSIONS;
    }

    /**
     * Returns if provided version is supported.
     *
     * @param $version
     * @return bool
     */
    public function isSupportedVersion($version)
    {
        return in_array($version, static::ES_SUPPORTED_VERSIONS);
    }

    /**
     * Returns running elasticsearch version.
     *
     * @return string|null
     */
    public function getCurrentVersion()
    {
        $runningServices = $this->brew->getAllRunningServices()->filter(function ($service) {
            return $this->isSupportedVersion($service);
        });

        return $runningServices->first();
    }

    /**
     * Installs the requested version and switches to it.
     *
     * @param $version
     */
    public function useVersion($version = self::ES_DEFAULT_VERSION)
    {
        if (!$this->isSupportedVersion($version)) {
            throw new DomainException(
                sprintf('Invalid Elasticsearch version given. Available versions: %s', implode(', ', static::ES_SUPPORTED_VERSIONS))
            );
        }

        $currentVersion = $this->getCurrentVersion();
        // If the requested version equals that of the current running version, do not switch.
        if ($version === $currentVersion) {
            info('Already on this version');

            return;
        }
        if ($currentVersion) {
            // Stop current version.
            $this->stop($currentVersion);
        }

        $this->install($version);
    }


    /**
     * Stop elasticsearch.
     *
     * @param string|null $version
     */
    public function stop($version = null)
    {
        $version = ($version ? $version : $this->getCurrentVersion());
        if (!$version) {
            return;
        }
        if (!$this->brew->installed($version)) {
            return;
        }

        $this->brew->stopService($version);
        $this->cli->quietlyAsUser('brew services stop ' . $version);
    }

    /**
     * Restart elasticsearch.
     *
     * @param string|null $version
     */
    public function restart($version = null)
    {
        $version = ($version ? $version : $this->getCurrentVersion());
        if (!$version) {
            return;
        }
        if (!$this->brew->installed($version)) {
            return;
        }

        info("Restarting {$version}...");
        $this->cli->quietlyAsUser('brew services restart ' . $version);
    }

    /**
     * Install the requested version of elasticsearch.
     *
     * @param string $version
     */
    public function install($version = self::ES_DEFAULT_VERSION)
    {
        if (!$this->isSupportedVersion($version)) {
            throw new DomainException(
                sprintf('Invalid Elasticsearch version given. Available versions: %s', implode(', ', static::ES_SUPPORTED_VERSIONS))
            );
        }

        // todo; install java dependency? and remove other java deps? seems like there can be only one running.
        // opensearch requires openjdk (installed automatically)
        // elasticsearch@6 requires openjdk@17 (installed automatically)
        //      seems like there can be only one openjdk when installing. after installing it doesn't matter.
        //      if this dependency is installed we need to launch es with this java version, see https://github.com/Homebrew/homebrew-core/issues/100260

        $this->brew->ensureInstalled($version);

        // todo: switch config still needed? > not between opensearch and elasticsearch@6
        // ==> opensearch
        //Data:    /usr/local/var/lib/opensearch/
        //Logs:    /usr/local/var/log/opensearch/*.log
        //Plugins: /usr/local/var/opensearch/plugins/
        //Config:  /usr/local/etc/opensearch/
        // ==> elasticsearch@6
        //Data:    /usr/local/var/lib/elasticsearch/
        //Logs:    /usr/local/var/log/elasticsearch/*.log
        //Plugins: /usr/local/var/elasticsearch/plugins/
        //Config:  /usr/local/etc/elasticsearch/

        // todo; add support for adding plugins like 'analysis-icu' and 'analysis-phonetic'?

        $this->restart($version);
    }

    /**
     * Uninstall all supported versions.
     */
    public function uninstall()
    {
        foreach ($this->getSupportedVersions() as $version) {
            $this->stop($version);
            $this->brew->uninstallFormula($version);
        }

        if (file_exists(BREW_PREFIX . '/var/elasticsearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/elasticsearch');
        }
        $this->files->unlink(BREW_PREFIX . '/var/log/elasticsearch.log');
        if (file_exists(BREW_PREFIX . '/var/log/elasticsearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/log/elasticsearch');
        }
        if (file_exists(BREW_PREFIX . '/var/lib/elasticsearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/lib/elasticsearch');
        }
        if (file_exists(BREW_PREFIX . '/etc/elasticsearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/etc/elasticsearch');
        }

        if (file_exists(BREW_PREFIX . '/var/opensearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/opensearch');
        }
        $this->files->unlink(BREW_PREFIX . '/var/log/opensearch.log');
        if (file_exists(BREW_PREFIX . '/var/log/opensearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/log/opensearch');
        }
        if (file_exists(BREW_PREFIX . '/var/lib/opensearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/lib/opensearch');
        }
        if (file_exists(BREW_PREFIX . '/etc/opensearch')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/etc/opensearch');
        }
    }
}
