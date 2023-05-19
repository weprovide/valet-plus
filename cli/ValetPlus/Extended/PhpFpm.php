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


        // todo; add performance configuration
    }
}
