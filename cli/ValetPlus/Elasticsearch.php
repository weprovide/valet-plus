<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use DomainException;
use Valet\Brew;
use Valet\CommandLine;
use function Valet\info;

class Elasticsearch
{
    /** @var string */
    protected const ES_DEFAULT_VERSION = 'opensearch';
    /** @var string[] */
    protected const ES_SUPPORTED_VERSIONS = ['opensearch'];


    /** @var Brew */
    protected $brew;
    /** @var CommandLine  */
    protected $cli;

    /**
     * @param Brew $brew
     * @param CommandLine $cli
     */
    public function __construct(
        Brew $brew,
        CommandLine $cli
    ) {
        $this->brew = $brew;
        $this->cli = $cli;
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

        $this->brew->ensureInstalled($version);

        // todo: switch config still needed?
        // ==> opensearch
        //Data:    /usr/local/var/lib/opensearch/
        //Logs:    /usr/local/var/log/opensearch/opensearch_homebrew.log
        //Plugins: /usr/local/var/opensearch/plugins/
        //Config:  /usr/local/etc/opensearch/

        // todo; add support for adding plugins like 'analysis-icu' and 'analysis-phonetic'?

        $this->restart($version);
    }

    /**
     * Prepare for uninstallation.
     */
    public function uninstall()
    {
        $this->stop();
        // todo; should do a 'brew remove <formula>' and 'rm -rf <stuff>'?
    }
}
