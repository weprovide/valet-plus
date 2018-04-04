<?php

namespace Valet;

use DomainException;

class PhpFpm
{
    var $brew, $cli, $files, $pecl;

    const DEPRECATED_PHP_TAP = 'homebrew/php';

    /**
     * Create a new PHP FPM class instance.
     *
     * @param  Brew $brew
     * @param  CommandLine $cli
     * @param  Filesystem $files
     */
    function __construct(Brew $brew, CommandLine $cli, Filesystem $files, Pecl $pecl)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->files = $files;
        $this->pecl = $pecl;
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
        $this->pecl->installExtensions($version, $this->getPhpIniPath());
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
    }

    function getPhpIniPath(){
        return str_replace('/conf.d/', '/php.ini', $this->iniPath());
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
     *
     * @return bool
     */
    function switchTo($version)
    {
        $versions = $this->sanitizeVersion(Brew::SUPPORTED_PHP_FORMULAE);
        $currentVersion = $this->brew->linkedPhp();

        if(!in_array($version, $versions)){
            throw new DomainException("This version of PHP not available. The following versions are available: " . implode(' ', $versions));
        }

        if ($version === $currentVersion) {
            return false;
        }

        $this->pecl->uninstallExtensions($currentVersion, $this->getPhpIniPath());

        $this->cli->runAsUser('brew unlink php@' . $currentVersion);
        $this->cli->runAsUser('sudo ln -s /usr/local/Cellar/jpeg/8d/lib/libjpeg.8.dylib /usr/local/opt/jpeg/lib/libjpeg.8.dylib');

        if (!$this->brew->installed('php@' . $version)) {
            $this->brew->ensureInstalled('php@' . $version);
        }

        $this->cli->runAsUser('brew unlink php@' . $version . ' && brew link php@' . $version.' --force --overwrite');
        $this->stop();
        $this->install();
        return true;
    }

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

    /**
     * Fixes common problems with php installations from Homebrew.
     */
    function fix(){
        $deprecatedVersions = ['56', '70', '71', '72'];
        $deprecatedExtensions = ['apcu', 'intl', 'mcrypt'];

        foreach($deprecatedVersions as $phpversion) {
            info('[php '.$phpversion.'] Disabling modules: ');
            foreach($deprecatedExtensions as $extension) {
                $this->disableExtension($extension);
            }
            $this->cli->passthru('brew cleanup php' . $phpversion);
        }

        if($this->brew->hasTap(self::DEPRECATED_PHP_TAP)){
            info('[brew] untapping formulae');
            $this->brew->unTap(self::DEPRECATED_PHP_TAP);
        }
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
