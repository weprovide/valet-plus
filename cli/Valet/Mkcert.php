<?php

namespace Valet;

class Mkcert
{
    public $brew;
    public $cli;

    /**
     * Create a new instance.
     *
     * @param  Brew          $brew
     * @param  CommandLine   $cli
     */
    public function __construct(
        Brew $brew,
        CommandLine $cli
    ) {
        $this->brew  = $brew;
        $this->cli   = $cli;
    }

    /**
     * Install mkcert.
     *
     * @return void
     */
    public function install()
    {
        if ($this->installed()) {
            info('[mkcert] already installed');
        } else {
            $this->brew->installOrFail('mkcert');
        }

        $this->cli->runAsUser('mkcert -install');
    }

    /**
     * Returns wether mkcert is installed or not.
     *
     * @return bool
     */
    public function installed()
    {
        return $this->brew->installed('mkcert');
    }

    /**
     * Uninstall mkcert.
     *
     * @return void
     */
    public function uninstall()
    {
        return $this->brew->uninstallOrFail('mkcert');
    }
}
