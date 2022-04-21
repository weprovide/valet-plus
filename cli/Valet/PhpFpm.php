<?php

namespace Valet;

use DomainException;

class PhpFpm
{
    const PHP_FORMULA_PREFIX = 'shivammathur/php/';
    const PHP_FORMULA_NAME = 'php';
//    const PHP_V56_VERSION = '5.6';
//    const PHP_V70_VERSION = '7.0';
//    const PHP_V71_VERSION = '7.1';
    const PHP_V72_VERSION = '7.2';
    const PHP_V73_VERSION = '7.3';
    const PHP_V74_VERSION = '7.4';
    const PHP_V80_VERSION = '8.0';
    const PHP_V81_VERSION = '8.1';

    const SUPPORTED_PHP_FORMULAE = [
//        self::PHP_V56_VERSION => self::PHP_FORMULA_NAME . self::PHP_V56_VERSION,
//        self::PHP_V70_VERSION => self::PHP_FORMULA_NAME . self::PHP_V70_VERSION,
//        self::PHP_V71_VERSION => self::PHP_FORMULA_NAME . self::PHP_V71_VERSION,
        self::PHP_V72_VERSION => self::PHP_FORMULA_NAME .'@'. self::PHP_V72_VERSION,
        self::PHP_V73_VERSION => self::PHP_FORMULA_NAME .'@'. self::PHP_V73_VERSION,
        self::PHP_V74_VERSION => self::PHP_FORMULA_NAME .'@'. self::PHP_V74_VERSION,
        self::PHP_V80_VERSION => self::PHP_FORMULA_NAME .'@'. self::PHP_V80_VERSION,
        self::PHP_V81_VERSION => self::PHP_FORMULA_NAME
    ];

    const EOL_PHP_VERSIONS = [
//        self::PHP_V56_VERSION,
//        self::PHP_V70_VERSION,
//        self::PHP_V71_VERSION,
        self::PHP_V72_VERSION,
        self::PHP_V73_VERSION
    ];

    const LOCAL_PHP_FOLDER = '/etc/php/';

    public $brew;
    public $cli;
    public $files;
    public $pecl;
    public $peclCustom;
    public $brewDir;

//    const DEPRECATED_PHP_TAP = 'homebrew/php';
//    const VALET_PHP_BREW_TAP = 'henkrehorst/php';
    const SHIVAMMATHUR_PHP_BREW_TAP = 'shivammathur/php';

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
            $this->brew->ensureInstalled($this->getFormulaName(self::PHP_V74_VERSION));
        }

//        if (!$this->brew->hasTap(self::VALET_PHP_BREW_TAP)) {
//            info("[BREW TAP] Installing " . self::VALET_PHP_BREW_TAP);
//            $this->brew->tap(self::VALET_PHP_BREW_TAP);
//        } else {
//            info("[BREW TAP] " . self::VALET_PHP_BREW_TAP . " already installed");
//        }

        if (!$this->brew->hasTap(self::SHIVAMMATHUR_PHP_BREW_TAP)) {
            info("[BREW TAP] Installing " . self::SHIVAMMATHUR_PHP_BREW_TAP);
            $this->brew->tap(self::SHIVAMMATHUR_PHP_BREW_TAP);
        } else {
            info("[BREW TAP] " . self::SHIVAMMATHUR_PHP_BREW_TAP . " already installed");
        }

        $version = $this->linkedPhp();
//var_dump($version);
        $this->files->ensureDirExists($this->architecture->getBrewPath() . '/var/log', user());
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
//var_dump('restarting '.self::SUPPORTED_PHP_FORMULAE[$this->linkedPhp()], $this->linkedPhp());
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
        $brewPath = $this->architecture->getBrewPath();
        $confLookup = [
            self::PHP_V81_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '8.1/php-fpm.d/www.conf',
            self::PHP_V80_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '8.0/php-fpm.d/www.conf',
            self::PHP_V74_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '7.4/php-fpm.d/www.conf',
            self::PHP_V73_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '7.3/php-fpm.d/www.conf',
            self::PHP_V72_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '7.2/php-fpm.d/www.conf',
//            self::PHP_V71_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '7.1/php-fpm.d/www.conf',
//            self::PHP_V70_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '7.0/php-fpm.d/www.conf',
//            self::PHP_V56_VERSION => $brewPath . self::LOCAL_PHP_FOLDER . '5.6/php-fpm.conf',
        ];

//        var_dump($confLookup[$this->linkedPhp()]);
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
//var_dump($currentVersion);
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

        $installed = $this->brew->installed(self::PHP_FORMULA_PREFIX. self::SUPPORTED_PHP_FORMULAE[$version]);
//var_dump($installed);
        if (!$installed) {
            $this->brew->ensureInstalled(self::PHP_FORMULA_PREFIX.self::SUPPORTED_PHP_FORMULAE[$version]);
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
//var_dump($version, $currentVersion);
        if (!$this->linkPhp($version, $currentVersion)) {
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
            'brew link ' . self::PHP_FORMULA_PREFIX.self::SUPPORTED_PHP_FORMULAE[$version] . ' --force --overwrite',
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
        output($this->cli->runAsUser('brew unlink ' . self::PHP_FORMULA_PREFIX.self::SUPPORTED_PHP_FORMULAE[$version], function () use (&$isUnlinked) {
            $isUnlinked = false;
        }));
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

    /**
     * Xdebug 3 has different configuration fields in z-performance.ini than Xdebug 2.0.
     * This function will enable/disable the version specific settings in z-performance.ini.
     * @see https://xdebug.org/docs/upgrade_guide#New-Concepts
     * @return void
     */
    public function installXdebugConfiguration()
    {
        $version = $this->getXdebugVersion();
        if (!$version) {
            warning('Xdebug not found. You have to fix your z-performance.ini yourself if you are using Xdebug 3');
        }

        $iniPath = $this->iniPath();
        $zPerformancePath = $iniPath . 'z-performance.ini';
        $majorVersion = (int) substr($version, 0, 1);

        if (!$this->files->exists($zPerformancePath)) {
            warning('Cannot find z-performance.ini, please re-install Valet+');
        }
        info('Patching z-performance.ini so it will work with Xdebug '. $version);
        $content = $this->files->get($zPerformancePath);

        if ($majorVersion === 3) {
            // Disable Xdebug 2 options
            $content = preg_replace('/(xdebug.remote_enable=1)/', ';$1', $content);
            $content = preg_replace('/(xdebug.remote_host=localhost)/', ';$1', $content);
            $content = preg_replace('/(xdebug.remote_port=9000)/', ';$1', $content);
            $content = preg_replace('/(xdebug.remote_autostart=1)/', ';$1', $content);

            // Enable Xdebug 3 options
            $content = preg_replace('/;+(xdebug.mode=debug)/', '$1', $content);
            $content = preg_replace('/;+(xdebug.client_host=localhost)/', '$1', $content);
            $content = preg_replace('/;+(xdebug.client_port=9003)/', '$1', $content);
            $content = preg_replace('/;+(xdebug.start_with_request=1)/', '$1', $content);
            $content = preg_replace('/;+(xdebug.log_level=0)/', '$1', $content);
        }

        if ($majorVersion === 2) {
            // Enable Xdebug 2 options
            $content = preg_replace('/;+(xdebug.remote_enable=1)/', '$1', $content);
            $content = preg_replace('/;+(xdebug.remote_host=localhost)/', '$1', $content);
            $content = preg_replace('/;+(xdebug.remote_port=9000)/', '$1', $content);
            $content = preg_replace('/;+(xdebug.remote_autostart=1)/', '$1', $content);

            // Disable Xdebug 3 options
            $content = preg_replace('/(xdebug.mode=debug)/', ';$1', $content);
            $content = preg_replace('/(xdebug.client_host=localhost)/', ';$1', $content);
            $content = preg_replace('/(xdebug.client_port=9003)/', ';$1', $content);
            $content = preg_replace('/(xdebug.start_with_request=1)/', ';$1', $content);
            $content = preg_replace('/(xdebug.log_level=0)/', ';$1', $content);
        }

        $this->files->put($zPerformancePath, $content);
        info('z-performance.ini patched and ready to use with Xdebug ' . $version);
    }

    /**
     * @return string|bool
     */
    public function getXdebugVersion()
    {
        $output = $this->cli->run('php -v | grep "with Xdebug"');
        // Extract the Xdebug version
        preg_match('/with Xdebug v(\d\.\d\.\d),/', $output, $matches);

        if (count($matches) === 2) {
            return $matches[1];
        }

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
     * Determine which version of PHP is linked with Homebrew.
     *
     * @return string
     * @internal param bool $asFormula
     */
    public function linkedPhp()
    {
        $phpPath = $this->architecture->getBrewPath() . '/bin/php';
        if (!$this->files->isLink($phpPath)) {
            throw new DomainException("Unable to determine linked PHP.");
        }
//var_dump($phpPath);
        $resolvedPath = $this->files->readLink($phpPath);
//var_dump($resolvedPath);
        $versions = self::SUPPORTED_PHP_FORMULAE;
//var_dump($versions);
        foreach ($versions as $version => $brewname) {
            if (strpos($resolvedPath, '/php@' . $version . '/') !== false) {
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
            if ($this->brew->installed(self::PHP_FORMULA_PREFIX.self::PHP_FORMULA_PREFIX.$brewName)) {
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
//var_dump($phpIniPath);
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
        // Fix brew path in z-performance.ini
        $contents = str_replace('BREW_PATH', $this->architecture->getBrewPath(), $contents);

        $iniPath = $this->iniPath();
        $this->files->ensureDirExists($iniPath, user());
        $this->files->putAsUser($path, $contents);
    }

    /**
     * Fixes common problems with php installations from Homebrew.
     */
    public function fix($reinstall)
    {
        // If the current php is not 7.4, link 7.4.
        info('Check Valet+ PHP version...');
        if (!$reinstall) {
            info('Run valet fix with the --reinstall option to trigger a full reinstall of the default PHP version.');
        }

        // If the reinstall flag was passed, uninstall PHP.
        // If any error occurs return the error for debugging purposes.
        if ($reinstall) {
            $this->brew->ensureUninstalled(self::PHP_FORMULA_PREFIX.self::SUPPORTED_PHP_FORMULAE[self::PHP_V74_VERSION]);
            $this->brew->ensureInstalled(self::PHP_FORMULA_PREFIX.self::SUPPORTED_PHP_FORMULAE[self::PHP_V74_VERSION]);
        }

        // Check the current linked PHP version. If the current version is not the default version.
        // Then relink the default version.
        if ($this->linkedPhp() !== self::PHP_V74_VERSION) {
            $this->unlinkPhp(self::PHP_V74_VERSION);
            $this->linkPhp(self::PHP_V74_VERSION);
        }

//        // Untap the deprecated brew tap.
//        if ($this->brew->hasTap(self::DEPRECATED_PHP_TAP)) {
//            info('[brew] untapping formulae ' . self::DEPRECATED_PHP_TAP);
//            $this->brew->unTap(self::DEPRECATED_PHP_TAP);
//        }

        warning(
            "Please check your linked php version, you might need to restart your terminal!" .
            "\nLinked PHP should be php 7.4:"
        );
        output($this->cli->runAsUser('php -v'));
    }
}
