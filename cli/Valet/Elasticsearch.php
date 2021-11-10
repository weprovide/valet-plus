<?php

namespace Valet;

use DomainException;

class Elasticsearch
{
    const NGINX_CONFIGURATION_STUB = __DIR__ . '/../stubs/elasticsearch.conf';
    const NGINX_CONFIGURATION_PATH = 'etc/nginx/valet/elasticsearch.conf';

    const ES_CONFIG_YAML          = '/usr/local/etc/elasticsearch/elasticsearch.yml';
    const ES_CONFIG_DATA_PATH     = 'path.data';
    const ES_CONFIG_DATA_BASEPATH = '/usr/local/var/';

    const ES_FORMULA_NAME = 'elasticsearch';

    protected $versions;

    public $brew;
    public $cli;
    public $files;
    public $configuration;
    public $site;
    public $phpFpm;

    /**
     * Elasticsearch constructor.
     * @param Brew          $brew
     * @param CommandLine   $cli
     * @param Filesystem    $files
     * @param Configuration $configuration
     * @param Site          $site
     * @param PhpFpm        $phpFpm
     */
    public function __construct(
        Brew $brew,
        CommandLine $cli,
        Filesystem $files,
        Configuration $configuration,
        Site $site,
        PhpFpm $phpFpm
    ) {
        $this->cli           = $cli;
        $this->brew          = $brew;
        $this->site          = $site;
        $this->files         = $files;
        $this->configuration = $configuration;
        $this->phpFpm        = $phpFpm;
    }

    /**
     * Install the service.
     *
     * @param string $version
     * @return void
     */
    public function install($version = null)
    {
        $versions = $this->getVersions();
        $version  = ($version ? $version : $this->getLatestVersion());

        if (!$this->isSupportedVersion($version)) {
            warning('The Elasticsearch version you\'re installing is not supported.');
            warning('Available versions are: ' . implode(', ', array_keys($this->getVersions())));

            return;
        }

        if ($this->installed($version)) {
            info('[' . $versions[$version]['formula'] . '] (version: ' . $versions[$version]['stable'] . ') already installed');

            return;
        }

        // Install dependencies
        $this->cli->quietlyAsUser('brew cask install java');
        $this->cli->quietlyAsUser('brew cask install homebrew/cask-versions/adoptopenjdk8');
        $this->brew->installOrFail('libyaml');
        // Install elasticsearch
        $this->brew->installOrFail($versions[$version]['formula']);
        // Restart just to make sure
        $this->restart($version);
    }

    /**
     * Returns wether Elasticsearch is installed.
     *
     * @param string $version
     * @return bool
     */
    public function installed($version = null)
    {
        // todo; if we have let's say version 5.6 installed the check can give a false-positive
        //  return when current version (7.10) in Brew has the same formula now as 5.6 at the time.

        $versions = $this->getVersions();
        $majors   = ($version ? [$version] : array_keys($versions));
        foreach ($majors as $version) {
            if ($this->brew->installed($versions[$version]['formula'])) {
                return $version;
            }
        }

        return false;
    }

    /**
     * Restart the service.
     *
     * @param string $version
     * @return void
     */
    public function restart($version = null)
    {
        $version = ($version ? $version : $this->getCurrentVersion());

        if (!$this->installed($version)) {
            return;
        }

        $versions = $this->getVersions();
        info('[' . $versions[$version]['formula'] . '] Restarting');
        $this->cli->quietlyAsUser('brew services restart ' . $versions[$version]['formula']);
    }

    /**
     * Stop the service.
     *
     * @param string $version
     * @return void
     */
    public function stop($version = null)
    {
        $version = ($version ? $version : $this->getCurrentVersion());
        if (!$version) {
            return;
        }

        if (!$this->installed($version)) {
            return;
        }

        $versions = $this->getVersions();
        info('[' . $versions[$version]['formula'] . '] Stopping');
        $this->cli->quietlyAsUser('brew services stop ' . $versions[$version]['formula']);
    }

    /**
     * Prepare for uninstallation.
     *
     * @return void
     */
    public function uninstall()
    {
        $this->stop();
        // todo; should do a 'brew remove <formula>' and 'rm -rf <stuff>'?
    }

    /**
     * @param $domain
     */
    public function updateDomain($domain)
    {
        $this->files->putAsUser(
            BREW_PATH . '/' . self::NGINX_CONFIGURATION_PATH,
            str_replace(
                ['VALET_DOMAIN'],
                [$domain],
                $this->files->get(self::NGINX_CONFIGURATION_STUB)
            )
        );
    }

    /**
     * Switch between versions of installed Elasticsearch. Switch to the provided version.
     *
     * @param $version
     */
    public function switchTo($version)
    {
        $currentVersion = $this->getCurrentVersion();
        if (!$this->isSupportedVersion($version)) {
            throw new DomainException("This version of Elasticsearch is not supported. The following versions are supported: " . implode(', ', array_keys($this->getVersions())) . ($currentVersion ? "\nCurrent version is " . $currentVersion : ""));
        }

        // If the requested version equals that of the current running version, do not switch.
        if ($version === $currentVersion) {
            info('Already on this version');

            return;
        }

        // Make sure the requested version is installed.
        $versions  = $this->getVersions();
        $installed = $this->installed($version);
        if (!$installed) {
            $this->brew->ensureInstalled($versions[$version]['formula']);
        }

        if ($currentVersion) {
            // Stop current version.
            $this->stop($currentVersion);
        }

        // Alter elasticsearch data path in config yaml.
        // Elasticsearch stores the indices on disk. In this yaml the path to those indices is configured.
        // The indices are not compatible accross different Elasticsearch versions. So, we configure a data
        // path for each Elasticsearch version to keep them stored and thus prevent having to index after
        // switching or even break Elasticsearch (it can't start properly with indices from another version).
        // todo; hmmm maybe we should do this also when installing?
        if (extension_loaded('yaml')) {
            $config                            = yaml_parse_file(self::ES_CONFIG_YAML);
            $config[self::ES_CONFIG_DATA_PATH] = self::ES_CONFIG_DATA_BASEPATH . self::ES_FORMULA_NAME . '@' . $version . '/';
            yaml_emit_file(self::ES_CONFIG_YAML, $config);
        } else {
            // Install PHP dependencies through installation of PHP.
            $this->phpFpm->install();
            warning("Switching Elasticsearch requires PECL extension yaml. Try switching again.");

            return;
        }

        // Start requested version.
        $this->restart($version);

        info("Valet is now using [" . $versions[$version]['formula'] . "]. You might need to reindex your data.");
    }

    /**
     * Returns the current running major version.
     *
     * @return bool|int|string
     */
    public function getCurrentVersion()
    {
        $currentVersion = false;
        $versions       = $this->getVersions();

        foreach ($versions as $major => $version) {
            if ($this->brew->isStartedService($version['formula'])) {
                $currentVersion = $major;
            }
        }

        return $currentVersion;
    }

    /**
     * Returns array with available formulae in Brew and their stable and major version.
     *
     * @return array
     */
    public function getVersions()
    {
        if ($this->versions === null) {
            $this->versions = $this->brew->getFormulaVersions(self::ES_FORMULA_NAME);
        }

        return $this->versions;
    }

    /**
     * Returns the major of the latest version.
     */
    public function getLatestVersion()
    {
        $versions = $this->getVersions();

        return max(array_keys($versions));
    }

    /**
     * Returns wether the version is supported in Brew.
     *
     * @param $version
     * @return bool
     */
    public function isSupportedVersion($version)
    {
        $versions = $this->getVersions();

        return in_array($version, array_keys($versions));
    }
}
