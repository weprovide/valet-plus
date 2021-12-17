<?php

namespace Valet;

class Architecture
{
    const ARM_BREW_PATH = '/opt/homebrew';
    const INTEL_BREW_PATH = '/usr/local';

    const ARM_64 = 'arm64';

    /**
     * @var string|null
     */
    private static $brewPath = null;

    /**
     * @return string
     */
    public static function getBrewPath()
    {
        if (Architecture::$brewPath === null) {
            Architecture::defineBrewPath();
        }
        return Architecture::$brewPath;
    }

    /**
     * @return bool
     */
    public static function isArm64()
    {
        $cli = new CommandLine();
        if (strpos($cli->run('uname -m'), self::ARM_64) !== false) {
            info('ARM Mac detected');
            return true;
        }
        info('Intel Mac detected');
        return false;
    }

    /**
     * @return void
     */
    private static function defineBrewPath()
    {
            Architecture::$brewPath = Architecture::isArm64() ?
                Architecture::ARM_BREW_PATH :
                Architecture::INTEL_BREW_PATH;
    }
}
