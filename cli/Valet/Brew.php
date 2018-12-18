<?php

namespace Valet;

use DomainException;

class Brew
{
    const PHP_DEFAULT_BREWNAME = 'php';
    const SUPPORTED_PHP_VERSIONS = [
        '5.6',
        '7' // will dynamically add all minor versions of 7
    ];

    var $supported_php_formulae = [];
    var $cli, $files;

    /**
     * Create a new Brew instance.
     *
     * @param  CommandLine $cli
     * @param  Filesystem $files
     */
    function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;

        $core_php_version = $this->corePhpVersion();

        $supported_php_versions = [];

        foreach (self::SUPPORTED_PHP_VERSIONS as $version) {
            // reached core php version, so stop
            if ($version > $core_php_version) {
                break;
            }

            if (strpos($version, '.') !== false) {
                $supported_php_versions[] = $version;
                continue;
            }

            // add all minor versions of major number, with the core php version as max
            for ($i = 0; $i < 10; $i++) {
                $supported_php_versions[] = $version . '.' . $i;

                // reached core php version, so stop
                if ($version . '.' . $i >= $core_php_version) {
                    break;
                }
            }
        }

        foreach ($supported_php_versions as $version) {
            $this->supported_php_formulae[$version] = 'php@' . $version;
        }

        $this->supported_php_formulae[$this->corePhpVersion()] = self::PHP_DEFAULT_BREWNAME;
    }

    /**
     * Determine if the given formula is installed.
     *
     * @param  string $formula
     * @return bool
     */
    function installed($formula)
    {
        return in_array($formula, explode(PHP_EOL, $this->cli->runAsUser('brew list | grep ' . $formula)));
    }

    /**
     * Determine if a compatible PHP version is Homebrewed.
     *
     * @return bool
     */
    function hasInstalledPhp()
    {
        foreach ($this->supported_php_formulae as $version => $brewname) {
            if ($this->installed($brewname)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a compatible nginx version is Homebrewed.
     *
     * @return bool
     */
    function hasInstalledNginx()
    {
        return $this->installed('nginx')
            || $this->installed('nginx-full');
    }

    /**
     * Return name of the nginx service installed via Homebrewed.
     *
     * @return string
     */
    function nginxServiceName()
    {
        return $this->installed('nginx-full') ? 'nginx-full' : 'nginx';
    }

    /**
     * Ensure that the given formula is installed.
     *
     * @param  string $formula
     * @param  array $options
     * @param  array $taps
     * @return void
     */
    function ensureInstalled($formula, $options = [], $taps = [])
    {
        if (!$this->installed($formula)) {
            $this->installOrFail($formula, $options, $taps);
        }
    }

    /**
     * Ensure that the given formula is uninstalled.
     *
     * @param  string $formula
     * @param  array $options
     * @param  array $taps
     * @return void
     */
    function ensureUninstalled($formula, $options = [], $taps = [])
    {
        if ($this->installed($formula)) {
            $this->uninstallOrFail($formula, $options, $taps);
        }
    }

    /**
     * Install the given formula and throw an exception on failure.
     *
     * @param  string $formula
     * @param  array $options
     * @param  array $taps
     * @return void
     */
    function installOrFail($formula, $options = [], $taps = [])
    {
        info('[' . $formula . '] Installing');
        info('brew install ' . $formula . ' ' . implode(' ', $options));

        if (count($taps) > 0) {
            $this->tap($taps);
        }

        $this->cli->runAsUser(
            trim('brew install ' . $formula . ' ' . implode(' ', $options)),
            function ($exitCode, $errorOutput) use ($formula) {
                output($errorOutput);

                throw new DomainException('Brew was unable to install [' . $formula . '].');
            }
        );
    }

    /**
     * Uninstall the given formula and throw an exception on failure.
     *
     * @param  string $formula
     * @param  array $options
     * @param  array $taps
     * @return void
     */
    function uninstallOrFail($formula, $options = [], $taps = [])
    {
        info('[' . $formula . '] Uninstalling');

        if (count($taps) > 0) {
            $this->tap($taps);
        }

        $this->cli->runAsUser(
            trim('brew uninstall ' . $formula . ' ' . implode(' ', $options)),
            function ($exitCode, $errorOutput) use ($formula) {
                output($errorOutput);

                throw new DomainException('Brew was unable to uninstall [' . $formula . '].');
            }
        );
    }

    /**
     * Tap the given formulas.
     *
     * @param  dynamic [string]  $formula
     * @return void
     */
    function tap($formulas)
    {
        $formulas = is_array($formulas) ? $formulas : func_get_args();

        foreach ($formulas as $formula) {
            $this->cli->passthru('sudo -u ' . user() . ' brew tap ' . $formula);
        }
    }

    /**
     * Untap the given formulas.
     *
     * @param  dynamic [string]  $formula
     * @return void
     */
    function unTap($formulas)
    {
        $formulas = is_array($formulas) ? $formulas : func_get_args();

        foreach ($formulas as $formula) {
            $this->cli->passthru('sudo -u ' . user() . ' brew untap ' . $formula);
        }
    }

    /**
     * Check if brew has the given tap.
     *
     * @param $formula
     * @return bool
     */
    function hasTap($formula)
    {
        return strpos($this->cli->runAsUser("brew tap | grep $formula"), $formula) !== false;
    }

    /**
     * Restart the given Homebrew services.
     *
     * @param
     */
    function restartService($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            if ($this->installed($service)) {
                info('[' . $service . '] Restarting');

                $this->cli->quietly('sudo brew services stop ' . $service);
                $this->cli->quietly('sudo brew services start ' . $service);
            }
        }
    }

    /**
     * Stop the given Homebrew services.
     *
     * @param
     */
    function stopService($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            if ($this->installed($service)) {
                info('[' . $service . '] Stopping');

                $this->cli->quietly('sudo brew services stop ' . $service);
            }
        }
    }

    /**
     * Determine which version of PHP is the default in Homebrew.
     *
     * @return string
     */
    function corePhpVersion()
    {
        $grep = $this->cli->runAsUser('brew info php | grep Cellar');
        preg_match('/Cellar\/php\/([0-9]+\.[0-9]+)/', $grep, $match);

        if (empty($match[1])) {
            // Fall back to 7.3 if no version is found
            return '7.3';
        }

        return trim($match[1]);
    }

    /**
     * Determine which version of PHP is linked in Homebrew.
     *
     * @return string
     */
    function linkedPhp()
    {
        if (!$this->files->isLink('/usr/local/bin/php')) {
            throw new DomainException("Unable to determine linked PHP.");
        }

        $resolvedPath = $this->files->readLink('/usr/local/bin/php');

        $versions = $this->supported_php_formulae;

        foreach ($versions as $version => $brewname) {
            if (strpos($resolvedPath, '/' . $brewname . '/') !== false) {
                return $version;
            }
        }


        throw new DomainException("Unable to determine linked PHP.");
    }

    /**
     * Restart the linked PHP-FPM Homebrew service.
     *
     * @return void
     */
    function restartLinkedPhp()
    {
        $this->restartService($this->supported_php_formulae[$this->linkedPhp()]);
    }
}
