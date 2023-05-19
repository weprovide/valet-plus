<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use Valet\Brew;
use Valet\CommandLine;
use Valet\Filesystem;
use Valet\PhpFpm;
use function Valet\warning;

class Xdebug extends AbstractPhpExtension
{
    /** @var string */
    protected const EXTENSION_NAME = PhpExtension::XDEBUG_EXTENSION;

    /** @var CommandLine */
    protected $cli;
    /** @var Filesystem */
    protected $files;

    /**
     * @param Brew $brew
     * @param PhpFpm $phpFpm
     * @param PhpExtension $phpExtension
     * @param CommandLine $cli
     * @param Filesystem $files
     */
    public function __construct(
        Brew         $brew,
        PhpFpm       $phpFpm,
        PhpExtension $phpExtension,
        CommandLine  $cli,
        Filesystem   $files
    ) {
        parent::__construct($brew, $phpFpm, $phpExtension);

        $this->cli   = $cli;
        $this->files = $files;
    }

    /**
     * @inheritdoc
     */
    public function install()
    {
        $installed = parent::install();
        $this->installXdebugConfiguration();

        return $installed;
    }

    /**
     * @inheritdoc
     */
    public function uninstall()
    {
        $this->uninstallXdebugConfiguration();
        return parent::uninstall();
    }


    /**
     * Remove xdebug configuration.
     */
    public function uninstallXdebugConfiguration()
    {
        $version = $this->getXdebugVersion();
        $destDir = dirname(dirname($this->phpIniPath)) . '/conf.d/';
        $this->files->unlink($destDir . 'xdebug-v' . $version . '.ini');
    }

    /**
     * Install xdebug configuration.
     */
    public function installXdebugConfiguration()
    {
        $version = $this->getXdebugVersion();
        if (!$version) {
            warning('Xdebug not found.');

            return;
        }

        $contents = $this->files->get(__DIR__ . '/../stubs/xdebug/v' . $version . '.ini');
        $destDir  = dirname(dirname($this->phpIniPath)) . '/conf.d/';
        $this->files->putAsUser(
            $destDir . 'xdebug-v' . $version . '.ini',
            $contents
        );
    }

    /**
     * Get version of xdebug.
     *
     * @return string|bool
     */
    public function getXdebugVersion()
    {
        $output = $this->cli->run('php -v | grep "with Xdebug"');
        // Extract the Xdebug version
        preg_match('/with Xdebug v(\d\.\d\.\d),/', $output, $matches);

        if (count($matches) === 2) {
            return (int)substr($matches[1], 0, 1);
        }

        return false;
    }
}
