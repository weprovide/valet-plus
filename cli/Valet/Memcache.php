<?php

namespace Valet;

class Memcache
{
    public $brew;
    public $cli;
    public $pecl;

    /**
     * Memcached.
     *
     * @param  Brew $brew
     * @param  CommandLine $cli
     * @param  Pecl $pecl
     */
    public function __construct(Brew $brew, CommandLine $cli, Pecl $pecl)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->pecl = $pecl;
    }

    /**
     * Install service.
     *
     * @return bool
     */
    public function install()
    {
        $restart = false;
        if ($this->brew->installed('libmemcached')) {
            info('[libmemcached] (brew) already installed');
        } else {
            $restart = true;
            $this->brew->ensureInstalled('libmemcached');
            info('[libmemcached] Successfully installed');
        }
        info('[memcached] Installing');
        $peclInstalled = $this->pecl->installExtension('memcached');
        if ($restart || $peclInstalled) {
            return true;
        }
        return false;
    }

    /**
     * Uninstall memcached.
     *
     * @return bool
     */
    public function uninstall()
    {
        info('[memcached] Uninstalling');
        $removed = $this->pecl->disableExtension('memcached');
        if ($removed) {
            info('[memcached] Successfully uninstalled');
        } else {
            info('[memcached] was already uninstalled');
        }
        $this->brew->ensureUninstalled('libmemcached');
        info('[libmemcached] Successfully uninstalled');
        return true;
    }
}
