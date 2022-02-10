<?php

namespace Valet;

use DomainException;

/**
 * Class Binaries is responsible for loading custom binaries.
 *
 * @package Valet
 */
class Binaries
{

    const N98_MAGERUN = 'magerun';
    const N98_MAGERUN_2 = 'magerun2';
    const DRUSH_LAUNCHER = 'drush';

    /**
     * Supported binaries for the binary manager. Example:
     *
     * @formatter:off
     *
     * 'bin_key_name' => [
     *    'url' => 'https://example.com/filename.extension'
     *    'shasum' => 'shasum of the downloaded file, for security',
     *    'bin_location' => '/usr/local/bin'
     * ]
     *
     * @formatter:on
     *
     */
    const SUPPORTED_CUSTOM_BINARIES = [
        self::N98_MAGERUN => [
            'url' => 'https://files.magerun.net/n98-magerun-latest.phar',
            'bin_location' => '/bin/'
        ],
        self::N98_MAGERUN_2 => [
            'url' => 'https://files.magerun.net/n98-magerun2-latest.phar',
            'bin_location' => '/bin/'
        ],
        self::DRUSH_LAUNCHER => [
            'url' => 'https://github.com/drush-ops/drush-launcher/releases/download/0.10.1/drush.phar',
            'shasum' => 'c6e3e520a33ac7790ab37e5af0fd91fc1108f0830a3a5ebe5b7c36e8782bea30',
            'bin_location' => '/bin/'
        ]
    ];

    /**
     * @var CommandLine
     */
    public $cli;
    /**
     * @var Filesystem
     */
    public $files;
    /**
     * @var Architecture
     */
    private $architecture;

    /**
     * Create a new Brew instance.
     *
     * @param Architecture $architecture
     * @param CommandLine $cli
     * @param Filesystem $files
     */
    public function __construct(Architecture $architecture, CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
        $this->architecture = $architecture;
    }

    /**
     * Check if the current binary is installed by the binary key name.
     *
     * @param $binary
     *    The binary key name.
     * @return bool
     *    True if installed, false if not installed.
     */
    public function installed($binary)
    {
        return $this->files->exists(self::SUPPORTED_CUSTOM_BINARIES[$binary]['bin_location'] . $binary);
    }

    /**
     * Install all binaries defined in SUPPORTED_CUSTOM_BINARIES
     */
    public function installBinaries()
    {
        info("[binaries] Installing binaries");
        foreach (self::SUPPORTED_CUSTOM_BINARIES as $binary => $versions) {
            if (!$this->installed($binary)) {
                $this->installBinary($binary);
            }
        }
    }

    /**
     * Install a single binary defined by the binary key name.
     *
     * @param $binary
     *    The binary key name.
     */
    public function installBinary($binary)
    {
        $url = $this->getUrl($binary);
        $urlSplit = explode('/', $url);
        $fileName = $urlSplit[count($urlSplit) - 1];

        // Download binary file.
        info("[binaries] $binary not found, installing from: $url");
        $this->cli->passthru("cd /tmp && curl -OL $url");

        // Check the checksum of downloaded file.
        if (!$this->checkShasum($binary, $fileName)) {
            $this->cli->runAsUser("rm /tmp/$fileName");
            warning("$binary could not be installed, $fileName checksum does not match: " .
                $this->getShasum($binary, $fileName));
            return;
        }

        $binLocation = $this->getBinLocation($binary);
        $this->cli->run("sudo mv /tmp/$fileName $binLocation");

        // Make file executable.
        $this->cli->run("sudo chmod +x $binLocation");

        info("[binaries] $binary installed to: $binLocation");
    }

    /**
     * Uninstall all binaries defined in SUPPORTED_CUSTOM_BINARIES
     */
    public function uninstallBinaries()
    {
        info("[binaries] Uninstalling binaries");
        foreach (self::SUPPORTED_CUSTOM_BINARIES as $binary => $versions) {
            if ($this->installed($binary)) {
                $this->uninstallBinary($binary);
            }
        }
    }

    /**
     * Uninstall a single binary defined by the binary key name.
     *
     * @param $binary
     *    The binary key name.
     */
    public function uninstallBinary($binary)
    {
        $binaryLocation = $this->getBinLocation($binary);
        $this->cli->runAsUser('rm ' . $binaryLocation);
        if ($this->files->exists($binaryLocation)) {
            throw new DomainException('Could not remove binary! Please remove manually using: rm ' . $binaryLocation);
        }
        info("[binaries] $binary succesfully uninstalled!");
    }

    /**
     * Get the shasum from the downloaded file by using the `shasum` command.
     *
     * @param $binary
     *    The binary key name.
     * @param $fileName
     *    The filename of the downloaded file.
     * @return bool
     *    True if matching, false if not matching.
     */
    private function checkShasum($binary, $fileName)
    {
        $checksum = $this->cli->runAsUser("shasum -a256 /tmp/$fileName");
        $checksum = str_replace("/tmp/$fileName", '', $checksum);
        $checksum = str_replace("\n", '', $checksum);
        $checksum = str_replace(' ', '', $checksum);
        return $checksum === $this->getShasum($binary, $fileName);
    }

    /**
     * Get the url that belongs to the binary key name.
     *
     * @param $binary
     *    The binary key name.
     * @return string
     *    The url as string defined within the binary key.
     */
    private function getUrl($binary)
    {
        if (array_key_exists('url', self::SUPPORTED_CUSTOM_BINARIES[$binary])) {
            return self::SUPPORTED_CUSTOM_BINARIES[$binary]['url'];
        }
        throw new DomainException('url key is required for binaries.');
    }

    /**
     * Get the shasum that belongs to the binary key name.
     *
     * @param $binary
     *    The binary key name.
     * @param $fileName
     *    The binary filename.
     * @return string
     *    The shasum as string defined within the binary key.
     */
    private function getShasum($binary,  $fileName)
    {
        //Check Shashum of Magerun to get the last version of it
        if (strpos($binary, self::N98_MAGERUN)!== false) {
            $shashum = $this->cli->runAsUser("curl -sS https://files.magerun.net/sha256.php\?file\=$fileName");
            $shashum = str_replace("$fileName", '', $shashum);
            $shashum = str_replace("\n", '', $shashum);
            $shashum = str_replace(' ', '', $shashum);
            return $shashum;
        }
        if (array_key_exists('shasum', self::SUPPORTED_CUSTOM_BINARIES[$binary])) {
            return self::SUPPORTED_CUSTOM_BINARIES[$binary]['shasum'];
        }
        throw new DomainException('shasum key is required for binaries.');
    }

    /**
     * Get the bin_location that belongs to the binary key name.
     *
     * @param $binary
     *    The binary key name.
     * @return string
     *    The bin_location as string defined within the binary key.
     */
    private function getBinLocation($binary)
    {
        if (array_key_exists('bin_location', self::SUPPORTED_CUSTOM_BINARIES[$binary])) {
            return $this->architecture->getBrewPath() . self::SUPPORTED_CUSTOM_BINARIES[$binary]['bin_location'] . $binary;
        }
        throw new DomainException('bin_location key is required for binaries.');
    }
}
