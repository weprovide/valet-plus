<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Valet\Brew;
use Valet\CommandLine;
use Valet\Configuration;
use Valet\Filesystem;
use Valet\Nginx;
use Valet\PhpFpm as ValetPhpFpm;
use Valet\Site;
use WeProvide\ValetPlus\PhpExtension;

class PhpFpm extends ValetPhpFpm
{
    /** @var PhpExtension */
    protected $phpExtension;

    /**
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Configuration $config
     * @param Site $site
     * @param Nginx $nginx
     * @param PhpExtension $phpExtension
     */
    public function __construct(
        Brew          $brew,
        CommandLine   $cli,
        Filesystem    $files,
        Configuration $config,
        Site          $site,
        Nginx         $nginx,
        PhpExtension  $phpExtension
    ) {
        parent::__construct($brew, $cli, $files, $config, $site, $nginx);

        $this->phpExtension = $phpExtension;
    }

    /**
     * @param $phpVersion
     * @return string
     */
    public function parsePhpVersion($phpVersion)
    {
        return preg_replace('~[^\d\.]~', '', $phpVersion);
    }

    /**
     * @inheritdoc
     */
    public function install(): void
    {
        parent::install();

        $phpVersion = $this->brew->linkedPhp();

        // Install php extensions.
        $this->phpExtension->installExtensions(
            $this->parsePhpVersion($phpVersion)
        );
    }

    /**
     * @inheritdoc
     * @todo; should be tested
     */
    public function uninstall(): void
    {
        $this->brew->supportedPhpVersions()->each(function ($formula) {
            $phpVersion = $this->parsePhpVersion($formula);
            $phpIniPath = $this->fpmConfigPath($phpVersion);
            $this->phpExtension->uninstallExtensions($phpVersion, $phpIniPath);
        });

        parent::uninstall();
    }

    /**
     * @inheritdoc
     */
    public function createConfigurationFiles(string $phpVersion): void
    {
        parent::createConfigurationFiles($phpVersion);

        // Get local timezone
        $systemZoneName = readlink('/etc/localtime');
        // All versions below High Sierra
        $systemZoneName = str_replace('/usr/share/zoneinfo/', '', $systemZoneName);
        // macOS High Sierra has a new location for the timezone info
        $systemZoneName = str_replace('/var/db/timezone/zoneinfo/', '', $systemZoneName);


        // Add performance ini settings.
        $contents = $this->files->get(__DIR__ . '/../../stubs/z-performance.ini');
        $contents = str_replace('TIMEZONE', $systemZoneName, $contents);


        $fpmConfigFile = $this->fpmConfigPath($phpVersion);
        $destDir       = dirname(dirname($fpmConfigFile)) . '/conf.d/';
        $this->files->putAsUser(
            $destDir . 'z-performance.ini',
            $contents
        );
    }
}
