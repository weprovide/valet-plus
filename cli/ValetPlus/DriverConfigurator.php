<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use Valet\CommandLine;
use Valet\Drivers\ValetDriver;
use Valet\Filesystem;
use Valet\Site;
use function Valet\info;

class DriverConfigurator
{
    /** @var CommandLine */
    protected $cli;
    /** @var Filesystem */
    protected $files;
    /** @var Site */
    protected $site;
    /** @var Redis */
    protected $redis;

    /**
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Site $site
     * @param Redis $redis
     */
    public function __construct(
        CommandLine $cli,
        Filesystem  $files,
        Site        $site,
        Redis       $redis
    ) {
        $this->cli   = $cli;
        $this->files = $files;
        $this->site  = $site;
        $this->redis = $redis;
    }

    /**
     * Configure application running in current directory according to driver.
     *
     * @return mixed|void
     */
    public function configure()
    {
        $valetDriver  = ValetDriver::assign(getcwd(), basename(getcwd()), '/');
        $classNameArr = explode('\\', get_class($valetDriver));
        $className    = end($classNameArr);
        $className    = "WeProvide\ValetPlus\DriverConfigs\\{$className}";

        try {
            $driver = new $className(
                $this->cli,
                $this->files,
                $this->site,
                $this->redis
            );

            return $driver->configure();
        } catch (\Throwable $throwable) {

        }

        info('No configuration settings found.');
    }

    /**
     * Returns the absolute current working path.
     *
     * @return string
     */
    protected function getPath()
    {
        return getcwd();
    }

    /**
     * Returns basename of the current directory.
     *
     * @return string
     */
    protected function getDir()
    {
        return basename($this->getPath());
    }

    /**
     * Returns the domain of the current directory.
     *
     * @return string
     */
    protected function getDomain()
    {
        return $this->site->getSiteUrl($this->getDir());
    }

    /**
     * Returns (un)secure URL of the current directory.
     *
     * @return string
     */
    protected function getUrl()
    {
        $secured  = $this->site->secured();
        $domain   = $this->getDomain();
        $isSecure = in_array($domain, $secured);
        return ($isSecure ? 'https://' : 'http://') . $domain;
    }
}
