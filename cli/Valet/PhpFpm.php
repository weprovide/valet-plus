<?php

namespace Valet;

use DomainException;

class PhpFpm
{
    const PHP_FORMULA_NAME = 'valet-php@';
    const PHP_V56_VERSION = '5.6';
    const PHP_V70_VERSION = '7.0';
    const PHP_V71_VERSION = '7.1';
    const PHP_V72_VERSION = '7.2';
    const PHP_V73_VERSION = '7.3';
    const PHP_V74_VERSION = '7.4';
    const PHP_V80_VERSION = '8.0';

    const SUPPORTED_PHP_FORMULAE = [
        self::PHP_V56_VERSION => self::PHP_FORMULA_NAME . self::PHP_V56_VERSION,
        self::PHP_V70_VERSION => self::PHP_FORMULA_NAME . self::PHP_V70_VERSION,
        self::PHP_V71_VERSION => self::PHP_FORMULA_NAME . self::PHP_V71_VERSION,
        self::PHP_V72_VERSION => self::PHP_FORMULA_NAME . self::PHP_V72_VERSION,
        self::PHP_V73_VERSION => self::PHP_FORMULA_NAME . self::PHP_V73_VERSION,
        self::PHP_V74_VERSION => self::PHP_FORMULA_NAME . self::PHP_V74_VERSION,
        self::PHP_V80_VERSION => self::PHP_FORMULA_NAME . self::PHP_V80_VERSION
    ];

    const EOL_PHP_VERSIONS = [
        self::PHP_V56_VERSION,
        self::PHP_V70_VERSION,
        self::PHP_V71_VERSION,
        self::PHP_V72_VERSION,
        self::PHP_V73_VERSION
    ];

    const LOCAL_PHP_FOLDER = '/etc/valet-php/';

    public $brew;
    public $cli;
    public $files;
    public $pecl;
    public $peclCustom;
    public $brewDir;

    const DEPRECATED_PHP_TAP = 'homebrew/php';
    const VALET_PHP_BREW_TAP = 'henkrehorst/php';

    /**
     * @param Architecture $architecture
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Pecl $pecl
     * @param PeclCustom $peclCustom
     */
    public function __construct(
        Architecture $architecture,
        Brew $brew,
        CommandLine $cli,
        Filesystem $files,
        Pecl $pecl,
        PeclCustom $peclCustom
    ) {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->files = $files;
        $this->pecl = $pecl;
        $this->peclCustom = $peclCustom;
        $this->architecture = $architecture;
    }

    /**
     * Install and configure DnsMasq.
     *
     * @return void
     */
    public function install()
    {
        if (!$this->hasInstalledPhp()) {
            $this->brew->ensureInstalled($this->getFormulaName(self::PHP_V73_VERSION), ['--build-from-source']);
        }

        if (!$this->brew->hasTap(self::VALET_PHP_BREW_TAP)) {
            info("[BREW TAP] Installing " . self::VALET_PHP_BREW_TAP);
            $this->brew->tap(self::VALET_PHP_BREW_TAP);
        } else {
            info("[BREW TAP] " . self::VALET_PHP_BREW_TAP . " already installed");
        }

        $version = $this->linkedPhp();

        $this->files->ensureDirExists(Architecture::getBrewPath() . '/var/log', user());
        $this->updateConfiguration();
        $this->pecl->updatePeclChannel();
        $this->pecl->installExtensions($version);
        $this->peclCustom->installExtensions($version);
        $this->restart();
    }

    public function iniPath()
    {
        $destFile = dirname($this->fpmConfigPath());
        $destFile = str_replace('/php-fpm.d', '', $destFile);
        $destFile = $destFile . '/conf.d/';

        return $destFile;
    }

    /**
     * Restart the currently linked PHP FPM process.
     *
     * @return void
     */
    public function restart()
    {
        $this->brew->restartService(self::SUPPORTED_PHP_FORMULAE[$this->linkedPhp()]);
    }

    /**
     * Stop all the PHP FPM processes.
     *
     * @return void
     */
    public function stop()
    {
        $this->brew->stopService(self::SUPPORTED_PHP_FORMULAE);
    }

    /**
     * Get the path to the FPM configuration file for the current PHP version.
     *
     * @return string
     */
    public function fpmConfigPath()
    {
        $confLookup = [
            self::PHP_V80_VERSION => Architecture::getBrewPath() . self::LOCAL_PHP_FOLDER . '8.0/php-fpm.d/www.conf',
            self::PHP_V74_VERSION => Architecture::getBrewPath() . self::LOCAL_PHP_FOLDER . '7.4/php-fpm.d/www.conf',
            self::PHP_V73_VERSION => Architecture::getBrewPath() . self::LOCAL_PHP_FOLDER . '7.3/php-fpm.d/www.conf',
            self::PHP_V72_VERSION => Architecture::getBrewPath() . self::LOCAL_PHP_FOLDER . '7.2/php-fpm.d/www.conf',
            self::PHP_V71_VERSION => Architecture::getBrewPath() . self::LOCAL_PHP_FOLDER . '7.1/php-fpm.d/www.conf',
            self::PHP_V70_VERSION => Architecture::getBrewPath() . self::LOCAL_PHP_FOLDER . '7.0/php-fpm.d/www.conf',
            self::PHP_V56_VERSION => Architecture::getBrewPath() . self::LOCAL_PHP_FOLDER . '5.6/php-fpm.conf',
        ];

        return $confLookup[$this->linkedPhp()];
    }

    /**
     * Get the formula name for a PHP version.
     *
     * @param $version
     * @return string Formula name
     */
    public function getFormulaName($version)
    {
        return self::SUPPORTED_PHP_FORMULAE[$version];
    }

    /**
     * Switch between versions of installed PHP. Switch to the provided version.
     *
     * @param $version
     */
    public function switchTo($version)
    {
        $currentVersion = $this->linkedPhp();

        if (!array_key_exists($version, self::SUPPORTED_PHP_FORMULAE)) {
            throw new DomainException(
                "This version of PHP not available. The following versions are available: " . implode(
                    ' ',
                    array_keys(self::SUPPORTED_PHP_FORMULAE)
                )
            );
        }

        // If the current version equals that of the current PHP version, do not switch.
        if ($version === $currentVersion) {
            info('Already on this version');
            return;
        }

        if (in_array($version, self::EOL_PHP_VERSIONS)) {
            warning('Caution! The PHP version you\'re switching to is EOL.');
            warning('Please check http://php.net/supported-versions.php for more information.');
        }

        $installed = $this->brew->installed(self::SUPPORTED_PHP_FORMULAE[$version]);
        if (!$installed) {
            $this->brew->ensureInstalled(self::SUPPORTED_PHP_FORMULAE[$version], ['--build-from-source']);
        }

        // Unlink the current PHP version.
        if (!$this->unlinkPhp($currentVersion)) {
            return;
        }

        // Relink libjpeg
        info('[libjpeg] Relinking');
        $this->cli->passthru(
            'sudo ln -fs /usr/local/Cellar/jpeg/8d/lib/libjpeg.8.dylib /usr/local/opt/jpeg/lib/libjpeg.8.dylib'
        );

        if (!$this->linkPHP($version, $currentVersion)) {
            return;
        }

        $this->stop();
        $this->install();
        info("Valet is now using " . self::SUPPORTED_PHP_FORMULAE[$version]);
    }

    /**
     * Link a PHP version to be used as binary.
     *
     * @param $version
     * @param $currentVersion
     * @return bool
     */
    private function linkPhp($version, $currentVersion = null)
    {
        $isLinked = true;
        info("[php@$version] Linking");
        $output = $this->cli->runAsUser(
            'brew link ' . self::SUPPORTED_PHP_FORMULAE[$version] . ' --force --overwrite',
            function () use (&$isLinked) {
                $isLinked = false;
            }
        );

        // The output is about how many symlinks were created.
        // Sanitize the second half to prevent users from being confused.
        // So the only output would be:
        // Linking /usr/local/Cellar/valet-php@7.3/7.3.8... 25 symlinks created
        // Without the directions to create exports pointing towards the binaries.
        if (strpos($output, 'symlinks created')) {
            $output = substr($output, 0, strpos($output, 'symlinks created') + 8);
        }
        output($output);

        if ($isLinked === false) {
            warning(
                "Could not link PHP version!" . PHP_EOL .
                "There appears to be an issue with your PHP $version installation!" . PHP_EOL .
                "See the output above for more information." . PHP_EOL
            );
        }

        if ($currentVersion !== null && $isLinked === false) {
            info("Linking back to previous version to prevent broken installation!");
            $this->linkPhp($currentVersion);
        }

        return $isLinked;
    }

    /**
     * Unlink a PHP version, removing the binary symlink.
     *
     * @param $version
     * @return bool
     */
    private function unlinkPhp($version)
    {
        $isUnlinked = true;
        info("[php@$version] Unlinking");
        output(
            $this->cli->runAsUser(
                'brew unlink ' . self::SUPPORTED_PHP_FORMULAE[$version], function () use (&$isUnlinked) {
                $isUnlinked = false;
            }
            )
        );
        if ($isUnlinked === false) {
            warning(
                "Could not unlink PHP version!" . PHP_EOL .
                "There appears to be an issue with your PHP $version installation!" . PHP_EOL .
                "See the output above for more information."
            );
        }

        return $isUnlinked;
    }

    /**
     * @deprecated Deprecated in favor of Pecl#installExtension();
     *
     * @param $extension
     * @return bool
     */
    public function enableExtension($extension)
    {
        $currentPhpVersion = $this->linkedPhp();

        if (!$this->brew->installed($currentPhpVersion . '-' . $extension)) {
            $this->brew->ensureInstalled($currentPhpVersion . '-' . $extension);
        }

        $iniPath = $this->iniPath();

        if ($this->files->exists($iniPath . 'ext-' . $extension . '.ini')) {
            info($extension . ' was already enabled.');
            return false;
        }

        if ($this->files->exists($iniPath . 'ext-' . $extension . '.ini.disabled')) {
            $this->files->move(
                $iniPath . 'ext-' . $extension . '.ini.disabled',
                $iniPath . 'ext-' . $extension . '.ini'
            );
        }

        info('Enabled ' . $extension);
        return true;
    }

    /**
     * @deprecated Deprecated in favor of Pecl#uninstallExtesnion();
     *
     * @param $extension
     * @return bool
     */
    public function disableExtension($extension)
    {
        $iniPath = $this->iniPath();
        if ($this->files->exists($iniPath . 'ext-' . $extension . '.ini.disabled')) {
            info($extension . ' was already disabled.');
            return false;
        }

        if ($this->files->exists($iniPath . 'ext-' . $extension . '.ini')) {
            $this->files->move(
                $iniPath . 'ext-' . $extension . '.ini',
                $iniPath . 'ext-' . $extension . '.ini.disabled'
            );
        }

        info('Disabled ' . $extension);
        return true;
    }

    /**
     * @deprecated Deprecated in favor of Pecl#installed();
     *
     * @param $extension
     * @return bool
     */
    public function isExtensionEnabled($extension)
    {

        $currentPhpVersion = $this->brew->linkedPhp();

        if (!$this->brew->installed($currentPhpVersion . '-' . $extension)) {
            $this->brew->ensureInstalled($currentPhpVersion . '-' . $extension);
        }

        $iniPath = $this->iniPath();

        if ($this->files->exists($iniPath . 'ext-' . $extension . '.ini')) {
            info($extension . ' is enabled.');
        } else {
            info($extension . ' is disabled.');
        }

        return true;
    }

    public function enableAutoStart()
    {
        $iniPath = $this->iniPath();
        if ($this->files->exists($iniPath . 'z-performance.ini')) {
            $this->cli->passthru(
                'sed -i "" "s/xdebug.remote_autostart=0/xdebug.remote_autostart=1/g" ' . $iniPath . 'z-performance.ini'
            );
            info('xdebug.remote_autostart is now enabled.');
            return true;
        }
        warning('Cannot find z-performance.ini, please re-install Valet+');
        return false;
    }

    public function disableAutoStart()
    {
        $iniPath = $this->iniPath();
        if ($this->files->exists($iniPath . 'z-performance.ini')) {
            $this->cli->passthru(
                'sed -i "" "s/xdebug.remote_autostart=1/xdebug.remote_autostart=0/g" ' . $iniPath . 'z-performance.ini'
            );
            info('xdebug.remote_autostart is now disabled.');
            return true;
        }
        warning('Cannot find z-performance.ini, please re-install Valet+');
        return false;
    }

    /**
     * The send mail path in z-performance.ini is incorrect for M1 macs with ARM64 processors, let's fix that.
     * @return bool
     */
    public function arm64FixMailPath()
    {
        if ($this->architecture->isArm64() === false) {
            return false;
        }
        $initPath = $this->iniPath();
        if (!$this->files->exists($initPath . 'z-performance.ini')) {
            warning('Cannot find z-performance.ini, please re-install Valet+');
            return false;
        }

        $zPerformanceLocation = $initPath . 'z-performance.ini';

        $this->cli->passthru(
            // phpcs:ignore
            sprintf('sed -i "" "s|%s|%s|" %s', Architecture::INTEL_BREW_PATH, Architecture::ARM_BREW_PATH, $zPerformanceLocation)
        );
        info('Sendmail path updated to work with M1 mac\'s');
        return true;
    }

    /**
     * Determine which version of PHP is linked with Homebrew.
     *
     * @return string
     * @internal param bool $asFormula
     */
    public function linkedPhp()
    {
        $phpPath = Architecture::getBrewPath() . '/bin/php';
        if (!$this->files->isLink($phpPath)) {
            throw new DomainException("Unable to determine linked PHP.");
        }

        $resolvedPath = $this->files->readLink($phpPath);

        $versions = self::SUPPORTED_PHP_FORMULAE;

        foreach ($versions as $version => $brewname) {
            if (strpos($resolvedPath, '/' . $brewname . '/') !== false) {
                return $version;
            }
        }

        throw new DomainException("Unable to determine linked PHP.");
    }

    /**
     * Determine if a compatible PHP version is installed through Homebrew.
     *
     * @return bool
     */
    public function hasInstalledPhp()
    {
        foreach (self::SUPPORTED_PHP_FORMULAE as $version => $brewName) {
            if ($this->brew->installed($brewName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the PHP FPM configuration.
     *
     * @return void
     */
    public function updateConfiguration()
    {
        $contents = $this->files->get($this->fpmConfigPath());

        $contents = preg_replace('/^user = .+$/m', 'user = ' . user(), $contents);
        $contents = preg_replace('/^group = .+$/m', 'group = staff', $contents);
        $contents = preg_replace('/^listen = .+$/m', 'listen = ' . VALET_HOME_PATH . '/valet.sock', $contents);
        $contents = preg_replace('/^;?listen\.owner = .+$/m', 'listen.owner = ' . user(), $contents);
        $contents = preg_replace('/^;?listen\.group = .+$/m', 'listen.group = staff', $contents);
        $contents = preg_replace('/^;?listen\.mode = .+$/m', 'listen.mode = 0777', $contents);
        $contents = preg_replace(
            '/^;?php_admin_value\[error_log\] = .+$/m',
            'php_admin_value[error_log] = ' . VALET_HOME_PATH . '/Log/php.log',
            $contents
        );
        $this->files->put($this->fpmConfigPath(), $contents);

        $this->writePerformanceConfiguration();

        // Get php.ini file.
        $extensionDirectory = $this->pecl->getExtensionDirectory();
        $phpIniPath = $this->pecl->getPhpIniPath();
        $contents = $this->files->get($phpIniPath);

        // Replace all extension_dir directives with nothing. And place extension_dir directive for valet+
        $contents = preg_replace(
            "/ *extension_dir = \"(.*)\"\n/",
            '',
            $contents
        );
        $contents = "extension_dir = \"$extensionDirectory\"\n" . $contents;

        // Save php.ini file.
        $this->files->putAsUser($phpIniPath, $contents);
    }

    public function writePerformanceConfiguration()
    {
        $path = $this->iniPath() . 'z-performance.ini';

        if (file_exists($path)) {
            return;
        }

        $systemZoneName = readlink('/etc/localtime');
        // All versions below High Sierra
        $systemZoneName = str_replace('/usr/share/zoneinfo/', '', $systemZoneName);
        // macOS High Sierra has a new location for the timezone info
        $systemZoneName = str_replace('/var/db/timezone/zoneinfo/', '', $systemZoneName);
        $contents = $this->files->get(__DIR__ . '/../stubs/z-performance.ini');
        $contents = str_replace('TIMEZONE', $systemZoneName, $contents);

        $iniPath = $this->iniPath();
        $this->files->ensureDirExists($iniPath, user());
        $this->files->putAsUser($path, $contents);
    }

    /**
     * Fixes common problems with php installations from Homebrew.
     */
    public function fix($reinstall)
    {
        // If the current php is not 7.3, link 7.3.
        info('Check Valet+ PHP version...');
        if (!$reinstall) {
            info('Run valet fix with the --reinstall option to trigger a full reinstall of the default PHP version.');
        }

        // If the reinstall flag was passed, uninstall PHP.
        // If any error occurs return the error for debugging purposes.
        if ($reinstall) {
            $this->brew->ensureUninstalled(self::SUPPORTED_PHP_FORMULAE[self::PHP_V73_VERSION]);
            $this->brew->ensureInstalled(self::SUPPORTED_PHP_FORMULAE[self::PHP_V73_VERSION], ['--build-from-source']);
        }

        // Check the current linked PHP version. If the current version is not the default version.
        // Then relink the default version.
        if ($this->linkedPhp() !== self::PHP_V73_VERSION) {
            $this->unlinkPhp(self::PHP_V73_VERSION);
            $this->linkPhp(self::PHP_V73_VERSION);
        }

        // Untap the deprecated brew tap.
        if ($this->brew->hasTap(self::DEPRECATED_PHP_TAP)) {
            info('[brew] untapping formulae ' . self::DEPRECATED_PHP_TAP);
            $this->brew->unTap(self::DEPRECATED_PHP_TAP);
        }

        warning(
            "Please check your linked php version, you might need to restart your terminal!" .
            "\nLinked PHP should be php 7.3:"
        );
        output($this->cli->runAsUser('php -v'));
    }
}
