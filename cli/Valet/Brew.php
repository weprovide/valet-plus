<?php

namespace Valet;

use Exception;
use DomainException;

class Brew
{
    const PHP_V56_FORMULAE = 'php@5.6';
    const PHP_V70_FORMULAE = 'php@7.0';
    const PHP_V71_FORMULAE = 'php@7.1';
    const PHP_V72_FORMULAE = 'php@7.2';

    const SUPPORTED_PHP_FORMULAE = [
        self::PHP_V56_FORMULAE,
        self::PHP_V70_FORMULAE,
        self::PHP_V71_FORMULAE,
        self::PHP_V72_FORMULAE
    ];

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
    }

    /**
     * Determine if the given formula is installed.
     *
     * @param  string  $formula
     * @return bool
     */
    function installed($formula)
    {
        return in_array($formula, explode(PHP_EOL, $this->cli->runAsUser('brew list | grep '.$formula)));
    }

    /**
     * Determine if a compatible PHP version is Homebrewed.
     *
     * @return bool
     */
    function hasInstalledPhp()
    {
        foreach (Brew::SUPPORTED_PHP_FORMULAE as $version) {
            if ($this->installed($version)) {
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
     * @param  string  $formula
     * @param  array  $options
     * @param  array  $taps
     * @return void
     */
    function ensureInstalled($formula, $options = [], $taps = [])
    {
        if (! $this->installed($formula)) {
            $this->installOrFail($formula, $options, $taps);
        }
    }

    /**
     * Install the given formula and throw an exception on failure.
     *
     * @param  string  $formula
     * @param  array  $options
     * @param  array  $taps
     * @return void
     */
    function installOrFail($formula, $options = [], $taps = [])
    {
        info('['.$formula.'] Installing');

        if (count($taps) > 0) {
            $this->tap($taps);
        }

        $this->cli->runAsUser(trim('brew install '.$formula.' '.implode(' ', $options)), function ($exitCode, $errorOutput) use ($formula) {
            output($errorOutput);

            throw new DomainException('Brew was unable to install ['.$formula.'].');
        });
    }

    /**
     * Tap the given formulas.
     *
     * @param  dynamic[string]  $formula
     * @return void
     */
    function tap($formulas)
    {
        $formulas = is_array($formulas) ? $formulas : func_get_args();

        foreach ($formulas as $formula) {
            $this->cli->passthru('sudo -u '.user().' brew tap '.$formula);
        }
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
            // Brew list doesn't show php@7.2 eventhough it is installed.
            if ($this->installed($service) || $service === self::PHP_V72_FORMULAE) {
                info('['.$service.'] Restarting');

                $this->cli->quietly('sudo brew services stop '.$service);
                $this->cli->quietly('sudo brew services start '.$service);
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
            // Brew list doesn't show php@7.2 eventhough it is installed.
            if ($this->installed($service) || $service === self::PHP_V72_FORMULAE) {
                info('['.$service.'] Stopping');

                $this->cli->quietly('sudo brew services stop '.$service);
            }
        }
    }

    /**
     * Determine which version of PHP is linked in Homebrew.
     *
     * @param bool $asFormula
     *
     * @return string
     */
    function linkedPhp($asFormula = FALSE)
    {
        if (!$this->files->isLink('/usr/local/bin/php')) {
            throw new DomainException("Unable to determine linked PHP.");
        }

        $resolvedPath = $this->files->readLink('/usr/local/bin/php');

        $versions = self::SUPPORTED_PHP_FORMULAE;

        foreach ($versions as $version) {
            $version = str_replace('php@', '', $version);
            if (strpos($resolvedPath, $version) !== FALSE) {
                if ($asFormula) {
                    $version = 'php@' . $version;
                }

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
        $this->restartService($this->linkedPhp(true));
    }
}
