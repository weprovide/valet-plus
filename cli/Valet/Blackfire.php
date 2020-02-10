<?php

namespace Valet;

use DomainException;

class Blackfire extends AbstractService
{
    const BF_FORMULA_NAME = 'blackfire-agent';
    const BF_TAP = 'blackfireio/homebrew-blackfire';
    const BF_V1300_VERSION = '1.30.0';
    const BF_DEFAULT_VERSION = self::BF_V1300_VERSION;

    const SUPPORTED_BF_VERSIONS = [
        self::BF_V1300_VERSION => self::BF_V1300_VERSION
    ];

    public $brew;
    public $cli;
    /**
     * @var Pecl
     */
    private $pecl;

    /**
     * Create a new instance.
     *
     * @param Configuration $configuration
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Pecl $pecl
     */
    public function __construct(
        Configuration $configuration,
        Brew $brew,
        CommandLine $cli,
        Pecl $pecl
    ) {
        $this->cli           = $cli;
        $this->brew          = $brew;
        $this->pecl          = $pecl;
        parent::__construct($configuration);
    }

    /**
     * Install the service.
     *
     * @param string $version
     * @return void
     */
    public function install($version = self::BF_DEFAULT_VERSION)
    {
        if (!array_key_exists($version, self::SUPPORTED_BF_VERSIONS)) {
            warning('The Blackfire version you\'re installing is not supported.');

            return;
        }


        if ($this->installed($version)) {
            info('[' .  self::SUPPORTED_BF_VERSIONS[$version] . '] already installed');

            return;
        }

        $extensionFile = $this->pecl->getExtensionDirectory() . "/blackfire.so";
        if (!file_exists($extensionFile)) {
            $phpVersion = str_replace('.', '', $this->getPhpVersion());
            $url = "https://packages.blackfire.io/binaries/blackfire-php/{$version}/blackfire-php-darwin_amd64-php-{$phpVersion}.so";
            $this->cli->quietlyAsUser("wget {$url} -O $extensionFile");
            $this->pecl->enableExtension('blackfire');
            $this->cli->runAsUser('valet restart php');
        }

        $this->brew->installOrFail(self::BF_FORMULA_NAME, [], [self::BF_TAP]);
        if ($this->brew->isStartedService(self::BF_FORMULA_NAME)) {
            $this->cli->quietlyAsUser('brew services restart ' . self::BF_FORMULA_NAME);
        } else {
            $this->cli->quietlyAsUser('brew services start ' . self::BF_FORMULA_NAME);
        }
        $this->cli->passthru('blackfire-agent --register');
        $this->cli->passthru('blackfire config');
        $this->cli->quietlyAsUser('brew services restart ' . self::BF_FORMULA_NAME);

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
        $extensionFile = $this->pecl->getExtensionDirectory() . "/blackfire.so";
        if (!file_exists($extensionFile)) {
            return false;
        }
        if ($this->brew->installed(self::BF_FORMULA_NAME)) {
            return true;
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
        $version = $this->installed($version);
        if (!$version) {
            return;
        }

        $this->pecl->enableExtension('blackfire');
        $this->cli->runAsUser('valet restart php');
        info('[' .  self::BF_FORMULA_NAME . '] Restarting');
        $this->cli->quietlyAsUser('brew services restart ' . self::BF_FORMULA_NAME);
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
        $version = $this->installed($version);
        if (!$version) {
            return;
        }

        $this->pecl->disableExtension('blackfire');
        $this->cli->runAsUser('valet restart php');

        info('[' . self::BF_FORMULA_NAME . '] Stopping');
        $this->cli->quietly('sudo brew services stop ' . self::BF_FORMULA_NAME);
        $this->cli->quietlyAsUser('brew services stop ' . self::BF_FORMULA_NAME);
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

    /**
     * Returns the current running version.
     *
     * @return bool|int|string
     */
    public function getCurrentVersion()
    {
        $currentVersion = false;
        foreach (self::SUPPORTED_BF_VERSIONS as $version => $formula) {
            if ($this->brew->isStartedService($formula)) {
                $currentVersion = $version;
            }
        }

        return $currentVersion;
    }

    /**
     * Get the current PHP version from the PECL config.
     *
     * @return string
     *    The php version as string: 5.6, 7.0, 7.1, 7.2, 7.3
     */
    protected function getPhpVersion()
    {
        $version = $this->cli->runAsUser('pecl version | grep PHP');
        $version = str_replace('PHP Version:', '', $version);
        $version = str_replace(' ', '', $version);
        $version = substr($version, 0, 3);
        return $version;
    }
}
