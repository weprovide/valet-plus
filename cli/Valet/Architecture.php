<?php declare(strict_types=1);

namespace Valet;

class Architecture
{
    /**
     * @var CommandLine
     */
    private $cli;

    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    public function isArm64() : bool
    {
        $architechture = $this->cli->run('uname -m');
        if ($architechture = 'arm64') {
            info('ARM Mac detected');
            return true;
        }
        info('Intel Mac detected');
        return false;
    }

    public function defineBrewPath() : void
    {
        if (!defined('BREW_PATH')) {
            define('BREW_PATH', $this->isArm64() ? '/opt/homebrew' : '/usr/local');
        }
    }
}
