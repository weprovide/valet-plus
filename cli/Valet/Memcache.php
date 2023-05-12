<?php

namespace Valet;

class Memcache
{
    /** @var PhpExtension */
    protected $phpExtension;

    /**
     * @param PhpExtension $phpExtension
     */
    public function __construct(
        PhpExtension $phpExtension
    ) {
        $this->phpExtension = $phpExtension;
    }

    /**
     * Install memcache.
     * @param $phpVersion
     * @return bool
     */
    public function install($phpVersion)
    {
        if (!$this->phpExtension->isInstalled(PhpExtension::MEMCACHE_EXTENSION, $phpVersion)) {
            return $this->phpExtension->installExtension(
                PhpExtension::MEMCACHE_EXTENSION,
                $phpVersion
            );
        }

        info("[EXTENSION] Memcache extension is already installed!");

        return false;
    }

    /**
     * Uninstall memcache.
     * @param $phpVersion
     * @param $phpIniConfigPath
     * @return bool
     */
    public function uninstall($phpVersion, $phpIniConfigPath)
    {
        if ($this->phpExtension->isInstalled(PhpExtension::MEMCACHE_EXTENSION, $phpVersion)) {
            return $this->phpExtension->uninstallExtension(
                PhpExtension::MEMCACHE_EXTENSION,
                $phpVersion,
                $phpIniConfigPath
            );
        }

        info("[EXTENSION] Memcache extension is already uninstalled!");

        return false;
    }
}
