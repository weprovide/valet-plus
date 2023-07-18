<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use DomainException;
use Valet\Brew;
use Valet\CommandLine;
use Valet\Filesystem;
use function Valet\info;
use function Valet\warning;

class Binary
{
    /** @var string */
    protected const N98_MAGERUN = 'magerun';
    /** @var string */
    protected const N98_MAGERUN_2 = 'magerun2';
    /** @var string */
    protected const DRUSH_LAUNCHER = 'drush';
    /** @var string */
    protected const WP_CLI = 'wp';

    /**
     * Supported binaries for the binary manager. Example:
     *
     * 'bin_key_name' => [
     *    'url' => 'https://example.com/filename.extension'
     *    'shasum' => 'shasum of the downloaded file, for security',
     *    'bin_location' => '/usr/local/bin'
     * ]
     */
    protected const SUPPORTED_BINARIES = [
        self::N98_MAGERUN    => [
            'url'          => 'https://files.magerun.net/n98-magerun-2.3.0.phar',
            'shasum'       => 'b3e09dafccd4dd505a073c4e8789d78ea3def893cfc475a214e1154bff3aa8e4',
            'bin_location' => BREW_PREFIX . '/bin/',
            'framework'    => 'Magento'
        ],
        self::N98_MAGERUN_2  => [
            'url'          => 'https://files.magerun.net/n98-magerun2-7.0.3.phar',
            'shasum'       => '4aa39aa33d9cd5f2d5e22850df76543791cf4f31c7dbdb7f9de698500f74307b',
            'bin_location' => BREW_PREFIX . '/bin/',
            'framework'    => 'Magento 2'
        ],
        self::DRUSH_LAUNCHER => [
            'url'          => 'https://github.com/drush-ops/drush-launcher/releases/download/0.10.2/drush.phar',
            'shasum'       => '0ae18cd3f8745fdd58ab852481b89428b57be6523edf4d841ebef198c40271be',
            'bin_location' => BREW_PREFIX . '/bin/',
            'framework'    => 'Drupal'
        ],
        self::WP_CLI         => [
            'brew_formula' => 'wp-cli'
        ]
    ];

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
     * Returns supported binaries.
     *
     * @return string[]
     */
    public function getSupported()
    {
        return array_keys(static::SUPPORTED_BINARIES);
    }

    /**
     * Check if the current binary is installed by the binary key name.
     *
     * @param string $binary
     * @return bool
     */
    public function installed($binary): bool
    {
        if (isset(static::SUPPORTED_BINARIES[$binary]['bin_location'])) {
            return $this->files->exists(static::SUPPORTED_BINARIES[$binary]['bin_location'] . $binary);
        }

        if (isset(static::SUPPORTED_BINARIES[$binary]['brew_formula'])) {
            return $this->brew->installed(static::SUPPORTED_BINARIES[$binary]['brew_formula']);
        }

        return false;
    }

    /**
     * Install all binaries.
     */
    public function install()
    {
        foreach (static::SUPPORTED_BINARIES as $binary => $binInfo) {
            $this->installBinary($binary);
        }
    }

    /**
     * Install a binary.
     *
     * @param $binary
     */
    public function installBinary($binary)
    {
        if (!isset(static::SUPPORTED_BINARIES[$binary])) {
            throw new DomainException(
                sprintf(
                    'Invalid binary given. Available binaries: %s',
                    implode(', ', $this->getSupported())
                )
            );
        }
        if ($this->installed($binary)) {
            $this->update($binary);

            return;
        }

        // Download and install binary.
        if (isset(static::SUPPORTED_BINARIES[$binary]['bin_location'])) {
            $url      = $this->getUrl($binary);
            $urlSplit = explode('/', $url);
            $fileName = $urlSplit[count($urlSplit) - 1];

            // Download binary file.
            info("Binary $binary installing from: $url");
            $this->cli->passthru("cd /tmp && curl -OL $url");

            // Check the checksum of downloaded file.
            if (!$this->checkShasum($binary, $fileName)) {
                $this->cli->runAsUser("rm /tmp/$fileName");
                warning("Binary $binary could not be installed, $fileName checksum does not match: " . $this->getShasum($binary));

                return;
            }

            // Move file.
            $binLocation = $this->getBinLocation($binary);
            $this->cli->run("sudo mv /tmp/$fileName $binLocation");

            // Make file executable.
            $this->cli->run("sudo chmod +x $binLocation");
            info("Binary $binary installed to: $binLocation");
        }

        // Install brew formula.
        if (isset(static::SUPPORTED_BINARIES[$binary]['brew_formula'])) {
            $formula = static::SUPPORTED_BINARIES[$binary]['brew_formula'];
            $this->brew->ensureInstalled($formula);
        }
    }

    /**
     * Uninstall all binaries.
     */
    public function uninstall()
    {
        foreach (static::SUPPORTED_BINARIES as $binary => $binInfo) {
            if ($this->installed($binary)) {
                $this->uninstallBinary($binary);
            }
        }
    }

    /**
     * Uninstall a single binary defined by the binary key name.
     *
     * @param string $binary
     */
    public function uninstallBinary($binary)
    {
        // Remove downloaded binary.
        if (isset(static::SUPPORTED_BINARIES[$binary]['bin_location'])) {
            $binaryLocation = $this->getBinLocation($binary);
            $this->cli->runAsUser('rm ' . $binaryLocation);
            if ($this->files->exists($binaryLocation)) {
                throw new DomainException('Could not remove binary! Please remove manually using: rm ' . $binaryLocation);
            }
            info("Binary $binary successfully uninstalled!");
        }

        // Uninstall brew formula.
        if (isset(static::SUPPORTED_BINARIES[$binary]['brew_formula'])) {
            $formula = static::SUPPORTED_BINARIES[$binary]['brew_formula'];
            $this->brew->uninstallFormula($formula);
        }
    }

    /**
     * @param string $binary
     */
    protected function update($binary)
    {
        if (isset(static::SUPPORTED_BINARIES[$binary]['bin_location'])) {
            info("Binary $binary updating...");
            $binLocation = $this->getBinLocation($binary);
            $this->cli->run("sudo $binLocation self-update");
        }
    }

    /**
     * Get the url that belongs to the binary key name.
     *
     * @param string $binary
     * @return string
     */
    protected function getUrl($binary)
    {
        if (array_key_exists('url', static::SUPPORTED_BINARIES[$binary])) {
            return static::SUPPORTED_BINARIES[$binary]['url'];
        }
        throw new DomainException('url key is required for binaries.');
    }

    /**
     * Get the shasum that belongs to the binary key name.
     *
     * @param string $binary
     * @return string
     */
    protected function getShasum($binary)
    {
        if (array_key_exists('shasum', static::SUPPORTED_BINARIES[$binary])) {
            return static::SUPPORTED_BINARIES[$binary]['shasum'];
        }
        throw new DomainException('shasum key is required for binaries.');
    }

    /**
     * Get the bin_location that belongs to the binary key name.
     *
     * @param string $binary
     * @return string
     */
    protected function getBinLocation($binary)
    {
        if (array_key_exists('bin_location', static::SUPPORTED_BINARIES[$binary])) {
            return static::SUPPORTED_BINARIES[$binary]['bin_location'] . $binary;
        }
        throw new DomainException('bin_location key is required for binaries.');
    }

    /**
     * Get the shasum from the downloaded file by using the `shasum` command.
     *
     * @param string $binary
     * @param string $fileName
     * @return bool
     */
    protected function checkShasum($binary, $fileName)
    {
        $checksum = $this->cli->runAsUser("shasum -a256 /tmp/$fileName");
        $checksum = str_replace("/tmp/$fileName", '', $checksum);
        $checksum = str_replace("\n", '', $checksum);
        $checksum = str_replace(' ', '', $checksum);

        return $checksum === $this->getShasum($binary);
    }
}
