<?php

namespace Valet;

use DomainException;

class PhpFpm
{
    var $brew, $cli, $files, $pecl, $peclCustom;

    const DEPRECATED_PHP_TAP = 'homebrew/php';

    /**
     * Create a new PHP FPM class instance.
     *
     * @param  Brew $brew
     * @param  CommandLine $cli
     * @param  Filesystem $files
     */
    function __construct(Brew $brew, CommandLine $cli, Filesystem $files, Pecl $pecl, PeclCustom $peclCustom)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->files = $files;
        $this->pecl = $pecl;
        $this->peclCustom = $peclCustom;
    }

    /**
     * Install and configure DnsMasq.
     *
     * @return void
     */
    function install()
    {
        if (! $this->brew->hasInstalledPhp()) {
            $this->brew->ensureInstalled(Brew::PHP_V71_FORMULAE);
        }

        $version = $this->brew->linkedPhp();

        $this->files->ensureDirExists('/usr/local/var/log', user());
        $this->updateConfiguration();
        $this->pecl->updatePeclChannel();
        $this->pecl->installExtensions($version);
        $this->peclCustom->installExtensions($version);
        $this->restart();
    }

    /**
     * Update the PHP FPM configuration.
     *
     * @return void
     */
    function updateConfiguration()
    {
        $contents = $this->files->get($this->fpmConfigPath());

        $contents = preg_replace('/^user = .+$/m', 'user = '.user(), $contents);
        $contents = preg_replace('/^group = .+$/m', 'group = staff', $contents);
        $contents = preg_replace('/^listen = .+$/m', 'listen = '.VALET_HOME_PATH.'/valet.sock', $contents);
        $contents = preg_replace('/^;?listen\.owner = .+$/m', 'listen.owner = '.user(), $contents);
        $contents = preg_replace('/^;?listen\.group = .+$/m', 'listen.group = staff', $contents);
        $contents = preg_replace('/^;?listen\.mode = .+$/m', 'listen.mode = 0777', $contents);
        $contents = preg_replace('/^;?php_admin_value\[error_log\] = .+$/m', 'php_admin_value[error_log] = '.VALET_HOME_PATH.'/Log/php.log', $contents);
        $this->files->put($this->fpmConfigPath(), $contents);

        $systemZoneName = readlink('/etc/localtime');
        // All versions below High Sierra
        $systemZoneName = str_replace('/usr/share/zoneinfo/', '', $systemZoneName);
        // macOS High Sierra has a new location for the timezone info
        $systemZoneName = str_replace('/var/db/timezone/zoneinfo/', '', $systemZoneName);
        $contents = $this->files->get(__DIR__.'/../stubs/z-performance.ini');
        $contents = str_replace('TIMEZONE', $systemZoneName, $contents);

        $iniPath = $this->iniPath();
        $this->files->ensureDirExists($iniPath, user());
        $this->files->putAsUser($this->iniPath().'z-performance.ini', $contents);

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

    function iniPath() {
        $destFile = dirname($this->fpmConfigPath());
        $destFile = str_replace('/php-fpm.d', '', $destFile);
        $destFile = $destFile . '/conf.d/';

        return $destFile;
    }

    /**
     * Restart the PHP FPM process.
     *
     * @return void
     */
    function restart()
    {
        $this->brew->restartLinkedPhp();
    }

    /**
     * Stop the PHP FPM process.
     *
     * @return void
     */
    function stop()
    {
        $this->brew->stopService(Brew::SUPPORTED_PHP_FORMULAE);
    }

    /**
     * Get the path to the FPM configuration file for the current PHP version.
     *
     * @return string
     */
    function fpmConfigPath()
    {
        $confLookup = [
            $this->sanitizeVersion(Brew::PHP_V72_FORMULAE) => '/usr/local/etc/php/7.2/php-fpm.d/www.conf',
            $this->sanitizeVersion(Brew::PHP_V71_FORMULAE) => '/usr/local/etc/php/7.1/php-fpm.d/www.conf',
            $this->sanitizeVersion(Brew::PHP_V70_FORMULAE) => '/usr/local/etc/php/7.0/php-fpm.d/www.conf',
            $this->sanitizeVersion(Brew::PHP_V56_FORMULAE) => '/usr/local/etc/php/5.6/php-fpm.conf',
        ];

        return $confLookup[$this->brew->linkedPhp()];
    }

    /**
     * Switch between versions of installed PHP
     */
    function switchTo($version)
    {
        $versions = $this->sanitizeVersion(Brew::SUPPORTED_PHP_FORMULAE);
        $currentVersion = $this->brew->linkedPhp();

        if(!in_array($version, $versions)){
            throw new DomainException("This version of PHP not available. The following versions are available: " . implode(' ', $versions));
        }

        // If the current version equals that of the current PHP version, do not switch.
        if ($version === $currentVersion) {
            info('Already on this version');
            return;
        }

        info("[php@$currentVersion] Unlinking");
        output($this->cli->runAsUser('brew unlink php@' . $currentVersion));

        info('[libjpeg] Relinking');
        $this->cli->passthru('sudo ln -fs /usr/local/Cellar/jpeg/8d/lib/libjpeg.8.dylib /usr/local/opt/jpeg/lib/libjpeg.8.dylib');

        if (!$this->brew->installed('php@' . $version)) {
            $this->brew->ensureInstalled('php@' . $version);
        }

        info("[php@$version] Linking");
        output($this->cli->runAsUser('brew link php@' . $version.' --force --overwrite'));

        $this->stop();
        $this->install();
        info("Valet is now using php@$version");
    }

    /**
     * @deprecated Deprecated in favor of Pecl#installExtesnion();
     *
     * @param $extension
     * @return bool
     */
    function enableExtension($extension) {
        $currentPhpVersion = $this->brew->linkedPhp();

        if(!$this->brew->installed($currentPhpVersion.'-'.$extension)) {
            $this->brew->ensureInstalled($currentPhpVersion.'-'.$extension);
        }

        $iniPath = $this->iniPath();

        if($this->files->exists($iniPath.'ext-'.$extension.'.ini')) {
            info($extension.' was already enabled.');
            return false;
        }

        if($this->files->exists($iniPath.'ext-'.$extension.'.ini.disabled')) {
            $this->files->move($iniPath.'ext-'.$extension.'.ini.disabled', $iniPath.'ext-'.$extension.'.ini');
        }

        info('Enabled '.$extension);
        return true;
    }

    /**
     * @deprecated Deprecated in favor of Pecl#uninstallExtesnion();
     *
     * @param $extension
     * @return bool
     */
    function disableExtension($extension) {
        $iniPath = $this->iniPath();
        if($this->files->exists($iniPath.'ext-'.$extension.'.ini.disabled')) {
            info($extension.' was already disabled.');
            return false;
        }

        if($this->files->exists($iniPath.'ext-'.$extension.'.ini')) {
            $this->files->move($iniPath.'ext-'.$extension.'.ini', $iniPath.'ext-'.$extension.'.ini.disabled');
        }

        info('Disabled '.$extension);
        return true;
    }

    /**
     * @deprecated Deprecated in favor of Pecl#installed();
     *
     * @param $extension
     * @return bool
     */
    function isExtensionEnabled($extension) {

      $currentPhpVersion = $this->brew->linkedPhp();

      if(!$this->brew->installed($currentPhpVersion.'-'.$extension)) {
          $this->brew->ensureInstalled($currentPhpVersion.'-'.$extension);
      }

      $iniPath = $this->iniPath();

      if($this->files->exists($iniPath.'ext-'.$extension.'.ini')) {
          info($extension.' is enabled.');
      } else {
          info($extension.' is disabled.');
      }

      return true;
    }

    function enableAutoStart() {
        $iniPath = $this->iniPath();
        if ($this->files->exists($iniPath . 'z-performance.ini')) {
            $this->cli->passthru('sed -i "" "s/xdebug.remote_autostart=0/xdebug.remote_autostart=1/g" ' . $iniPath . 'z-performance.ini');
            info('xdebug.remote_autostart is now enabled.');
            return true;
        }
        warning('Cannot find z-performance.ini, please re-install Valet+');
        return false;
    }

    function disableAutoStart() {
        $iniPath = $this->iniPath();
        if ($this->files->exists($iniPath . 'z-performance.ini')) {
            $this->cli->passthru('sed -i "" "s/xdebug.remote_autostart=1/xdebug.remote_autostart=0/g" ' . $iniPath . 'z-performance.ini');
            info('xdebug.remote_autostart is now disabled.');
            return true;
        }
        warning('Cannot find z-performance.ini, please re-install Valet+');
        return false;
    }

    function checkInstallation(){
        // Check for errors within the installation of php.
        info('[php] Checking for errors within the php installation...');
        if($this->brew->installed('php56') &&
            $this->brew->installed('php70')  &&
            $this->brew->installed('php71')  &&
            $this->brew->installed('php72')  &&
            $this->brew->installed('n98-magerun')  &&
            $this->brew->installed('n98-magerun2')  &&
            $this->brew->installed('drush')  &&
            $this->files->exists('/usr/local/etc/php/5.6/ext-intl.ini')  &&
            $this->files->exists('/usr/local/etc/php/5.6/ext-mcrypt.ini')  &&
            $this->files->exists('/usr/local/etc/php/5.6/ext-apcu.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.0/ext-intl.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.0/ext-mcrypt.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.0/ext-apcu.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.1/ext-intl.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.1/ext-mcrypt.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.1/ext-apcu.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.2/ext-intl.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.2/ext-mcrypt.ini')  &&
            $this->files->exists('/usr/local/etc/php/7.2/ext-apcu.ini')  &&
            $this->brew->hasTap(self::DEPRECATED_PHP_TAP)
        ){
            // No errors found return, do not run fix logic.
            throw new DomainException("[php] Valet+ found errors within the installation.\n
            run: valet fix for valet to try and resolve these errors");
            return;
        }
    }

    /**
     * Fixes common problems with php installations from Homebrew.
     */
    function fix(){
        // Remove old homebrew/php tap packages.
        info('Removing all old php56- packages from homebrew/php tap');
        $this->cli->passthru('brew list | grep php56- | xargs brew uninstall');
        info('Removing all old php70- packages from homebrew/php tap');
        $this->cli->passthru('brew list | grep php70- | xargs brew uninstall');
        info('Removing all old php71- packages from homebrew/php tap');
        $this->cli->passthru('brew list | grep php71- | xargs brew uninstall');
        info('Removing all old php72- packages from homebrew/php tap');
        $this->cli->passthru('brew list | grep php72- | xargs brew uninstall');

        info('Removing all old n98-magerun packages from homebrew/php tap');
        $this->cli->passthru('brew list | grep n98-magerun | xargs brew uninstall');

        info('Removing drush package from homebrew/php tap');
        $this->cli->passthru('brew list | grep drush | xargs brew uninstall');

        // Disable extensions that are not managed by the PECL manager or within php core.
        $deprecatedVersions = ['5.6', '7.0', '7.1', '7.2'];
        $deprecatedExtensions = ['apcu', 'intl', 'mcrypt'];
        foreach($deprecatedVersions as $phpVersion) {
            info('[php'.$phpVersion.'] Disabling modules: '.implode(', ', $deprecatedExtensions));
            foreach($deprecatedExtensions as $extension) {
                if($this->files->exists("/usr/local/etc/php/$phpVersion/ext-$extension.ini")){
                    $this->files->move(
                        "/usr/local/etc/php/$phpVersion/ext-$extension.ini",
                        "/usr/local/etc/php/$phpVersion/ext-$extension.ini.disabled"
                    );
                }
            }
        }

        info('Trying to remove php56...');
        $this->cli->passthru('brew uninstall php56');
        info('Trying to remove php70...');
        $this->cli->passthru('brew uninstall php70');
        info('Trying to remove php71...');
        $this->cli->passthru('brew uninstall php71');
        info('Trying to remove php72...');
        $this->cli->passthru('brew uninstall php72');

        // If the current php is not 7.1, link 7.1.
        info('Installing and linking new PHP homebrew/core version.');
        $this->cli->passthru('brew uninstall ' . Brew::PHP_V71_FORMULAE);
        $this->cli->passthru('brew install ' . Brew::PHP_V71_FORMULAE);
        $this->cli->passthru('brew unlink '. Brew::PHP_V71_FORMULAE);
        $this->cli->passthru('brew link '.Brew::PHP_V71_FORMULAE.' --force --overwrite');

        if ($this->brew->hasTap(self::DEPRECATED_PHP_TAP)) {
            info('[brew] untapping formulae ' . self::DEPRECATED_PHP_TAP);
            $this->brew->unTap(self::DEPRECATED_PHP_TAP);
        }

        warning("Please check your linked php version, you might need to restart your terminal!".
            "\nLinked PHP should be php 7.1:");
        $this->cli->passthru('php -v');
    }

    /**
     * Strips 'php@' from a string or array of strings.
     *
     * @param $argument
     * @return array|mixed
     */
    private function sanitizeVersion($argument)
    {
        if(is_array($argument)){
            foreach($argument as $key => $version){
                $argument[$key] = str_replace('php@', '', $version);
            }
        }else{
            $argument = str_replace('php@', '', $argument);
        }


        return $argument;
    }
}
