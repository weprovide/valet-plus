<?php declare(strict_types=1);

namespace Valet;

class Architecture
{
    public const ARM_BREW_PATH = '/opt/homebrew';
    public const INTEL_BREW_PATH = '/usr/local';

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
            define('BREW_PATH', $this->isArm64() ? self::ARM_BREW_PATH : self::INTEL_BREW_PATH);
        }
    }

    public function getBrewPath() : string
    {
        if (!defined('BREW_PATH')) {
            $this->defineBrewPath();
        }
        return BREW_PATH;
    }
}
