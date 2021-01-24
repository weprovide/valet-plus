<?php

namespace Valet;

use DomainException;

class Brew
{

    public $cli;
    public $files;

    /**
     * Create a new Brew instance.
     *
     * @param  CommandLine $cli
     * @param  Filesystem $files
     */
    public function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * Determine if the given formula is installed.
     *
     * @param  string $formula
     * @return bool
     */
    public function installed($formula)
    {
        return in_array($formula, explode(PHP_EOL, $this->cli->runAsUser('brew list --formula | grep ' . $formula)));
    }

    /**
     * Determine if a compatible nginx version is Homebrewed.
     *
     * @return bool
     */
    public function hasInstalledNginx()
    {
        return $this->installed('nginx')
            || $this->installed('nginx-full');
    }

    /**
     * Return name of the nginx service installed via Homebrewed.
     *
     * @return string
     */
    public function nginxServiceName()
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
    public function ensureInstalled($formula, $options = [], $taps = [])
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
    public function ensureUninstalled($formula, $options = [], $taps = [])
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
    public function installOrFail($formula, $options = [], $taps = [])
    {
        info('[' . $formula . '] Installing');

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
    public function uninstallOrFail($formula, $options = [], $taps = [])
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
    public function tap($formulas)
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
    public function unTap($formulas)
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
    public function hasTap($formula)
    {
        return strpos($this->cli->runAsUser("brew tap | grep $formula"), $formula) !== false;
    }

    /**
     * Restart the given Homebrew services.
     *
     * @param
     */
    public function restartService($services)
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
    public function stopService($services)
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
     * Checks wether the requested services is running.
     *
     * @param $formula
     * @return bool
     */
    public function isStartedService($formula)
    {
        $info = explode(" ", trim(str_replace($formula, "", $this->cli->runAsUser('brew services list | grep ' . $formula))));
        $state = array_shift($info);
        return ($state === 'started');
    }

    /**
     * Returns array with formulae available in Brew.
     *
     * @param $formula
     * @return string[]
     */
    public function getFormulae($formula)
    {
        return array_filter(
            explode(PHP_EOL, trim($this->cli->runAsUser("brew search $formula"))),
            function ($formula) {
                return ($formula != '==> Formulae');
            }
        );
    }

    /**
     * Returns array with available formulae in Brew and their stable and major version.
     *
     * @param $formula
     * @return array
     */
    public function getFormulaVersions($formula)
    {
        $formulae = $this->getFormulae($formula);

        $versions = [];
        foreach ($formulae as $_formula) {
            $stable  = trim($this->cli->runAsUser("brew info $_formula --json=v1 | jq '.[] | .versions | .stable'"), " \t\n\r\0\x0B\"");
            $version = explode('.', $stable);
            $major   = reset($version);

            $versions[$major] = [
                'stable'  => $stable,
                'major'   => $major,
                'formula' => $_formula,
            ];
        }

        return $versions;
    }
}
