<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use Valet\Brew;
use Valet\PhpFpm;
use function Valet\info;
use function Valet\output;
use function Valet\warning;

abstract class AbstractPhpExtension
{
    /** @var string|null */
    protected const EXTENSION_NAME = null;

    /** @var Brew */
    protected $brew;
    /** @var PhpFpm */
    protected $phpFpm;
    /** @var PhpExtension */
    protected $phpExtension;

    /** @var string */
    protected $phpVersion;
    /** @var string */
    protected $phpIniPath;

    /**
     * @param Brew $brew
     * @param PhpFpm $phpFpm
     * @param PhpExtension $phpExtension
     */
    public function __construct(
        Brew         $brew,
        PhpFpm       $phpFpm,
        PhpExtension $phpExtension
    ) {
        $this->brew         = $brew;
        $this->phpFpm       = $phpFpm;
        $this->phpExtension = $phpExtension;

        $this->phpVersion = $this->phpFpm->parsePhpVersion($this->brew->linkedPhp());
        $this->phpIniPath = $this->phpFpm->fpmConfigPath($this->phpVersion);
    }

    /**
     * Install php extension.
     *
     * @return bool
     */
    public function install()
    {
        if (!$this->phpExtension->isInstalled(static::EXTENSION_NAME, $this->phpVersion)) {
            return $this->phpExtension->installExtension(
                static::EXTENSION_NAME,
                $this->phpVersion
            );
        }

        info(static::EXTENSION_NAME . " extension is already installed!");

        return false;
    }

    /**
     * Uninstall php extension.
     *
     * @return bool
     */
    public function uninstall()
    {
        if ($this->phpExtension->isInstalled(static::EXTENSION_NAME, $this->phpVersion)) {
            return $this->phpExtension->uninstallExtension(
                static::EXTENSION_NAME,
                $this->phpVersion,
                $this->phpIniPath
            );
        }

        info(static::EXTENSION_NAME . " extension is already uninstalled!");

        return false;
    }
}
