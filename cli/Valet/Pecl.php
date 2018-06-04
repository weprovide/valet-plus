<?php

namespace Valet;

use Exception;
use DomainException;

class Pecl extends AbstractPecl
{

    // Extensions.
    const XDEBUG_EXTENSION = 'xdebug';
    const APCU_EXTENSION = 'apcu';
    const APCU_BC_EXTENSION = 'apcu_bc';
    const GEOIP_EXTENSION = 'geoip';

    // Extension aliases.
    const APCU_BC_ALIAS = 'apc';

    /**
     * Supported pecl extensions for the PECL manager. There are 2 types of extensions supported: zend_extensions and
     * extensions. Not all extension are available through PECL. This class handles packages that are handled by PECL.
     *
     * PECL example:
     *
     * The PECL extensions sometimes differ in version per PHP version. For example Xdebug can only be installed on
     * PHP 5.6 using the 2.2.7 version. In such a case one would define the version within the array as key. The value
     * would be 2.2.7 which is the version to be installed. See example below.
     *
     * The 'default' key allows one to disable the module by default. This is useful for modules that enable through
     * on/off commands, like Xdebug.
     *
     * @formatter:off
     *
     * 'extension_key_name' => [
     *    '5.6' => '2.2.7'
     *    'default' => false
     * ]
     *
     * @formatter:on
     */
    const EXTENSIONS = [
        self::XDEBUG_EXTENSION => [
            '5.6' => '2.2.7',
            'default' => false,
            'extension_type' => self::ZEND_EXTENSION_TYPE
        ],
        self::APCU_BC_EXTENSION => [
            '5.6' => false,
            'extension_type' => self::NORMAL_EXTENSION_TYPE
        ],
        self::APCU_EXTENSION => [
            '7.2' => false,
            '7.1' => false,
            '7.0' => false,
            '5.6' => '4.0.11',
            'extension_type' => self::NORMAL_EXTENSION_TYPE
        ],
        self::GEOIP_EXTENSION => [
            '7.2' => '1.1.1',
            '7.1' => '1.1.1',
            '7.0' => '1.1.1',
            'extension_type' => self::NORMAL_EXTENSION_TYPE
        ]
    ];

    var $peclCustom;

    /**
     * @inheritdoc
     */
    function __construct(CommandLine $cli, Filesystem $files, PeclCustom $peclCustom)
    {
        parent::__construct($cli, $files);
        $this->peclCustom = $peclCustom;
    }

    /**
     * @inheritdoc
     */
    public function installExtensions($onlyDefaults = true)
    {
        info("[PECL] Installing extensions");
        foreach (self::EXTENSIONS as $extension => $versions) {
            if ($onlyDefaults && $this->isDefaultExtension($extension) === false) {
                continue;
            }

            if($this->getVersion($extension) !== false){
                $this->installExtension($extension);
                $this->enableExtension($extension);
            }
        }
    }

    /**
     * Install a single extension if not already installed, default is true and has a version for this PHP version.
     * If version equals false the extension will not be installed for this PHP version.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     */
    function installExtension($extension)
    {
        if ($this->isInstalled($extension)) {
            output("\t$extension is already installed, skipping...");
            return false;
        }

        $this->install($extension, $this->getVersion($extension));
        return true;
    }

    /**
     * Install a single extension.
     *
     * @param $extension
     *    The extension key name.
     * @param null $version
     */
    protected function install($extension, $version = null)
    {
        if ($version === null) {
            $result = $this->cli->runAsUser("pecl install $extension");
        } else {
            $result = $this->cli->runAsUser("pecl install $extension-$version");
        }

        $alias = $this->getExtensionAlias($extension);
        if (!preg_match("/Installing '(.*$alias.so)'/", $result)) {
            throw new DomainException("Could not find installation path for: $extension\n\n$result");
        }

        if (strpos($result, "Error:")) {
            throw new DomainException("Installation path found, but installation failed:\n\n$result");
        }

        $phpIniPath = $this->getPhpIniPath();
        $phpIniFile = $this->files->get($phpIniPath);
        if (!preg_match('/(zend_extension|extension)\="(.*' . $alias . '.so)"/', $phpIniFile, $iniMatches)) {
            throw new DomainException("Could not find ini definition for: $extension in $phpIniPath");
        }

        output("\t$extension successfully installed");
    }

    /**
     * Enable an single extension if not already enabled, default is true and has a version does not equal false.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     */
    function enableExtension($extension)
    {
        if ($this->isEnabled($extension) && $this->isEnabledCorrectly($extension)) {
            output("\t$extension is already enabled, skipping...");
            return false;
        }

        $this->enable($extension);
        return true;
    }

    /**
     * Enable an single extension.
     *
     * @param $extension
     *    The extension key name.
     */
    function enable($extension)
    {
        $phpIniPath = $this->getPhpIniPath();
        $phpIniFile = $this->files->get($phpIniPath);
        $phpIniFile = $this->replaceIniDefinition($extension, $phpIniFile);
        $phpIniFile = $this->alternativeInstall($extension, $phpIniFile);
        $this->peclCustom->saveIniFile($phpIniPath, $phpIniFile);

        output("\t$extension successfully enabled");
    }

    /**
     * @inheritdoc
     */
    function uninstallExtensions()
    {
        info("[PECL] Removing extensions");
        foreach (self::EXTENSIONS as $extension => $versions) {
            if($this->getVersion($extension) !== false){
                $this->disableExtension($extension);
            }
        }
    }

    /**
     * Uninstall and disable an extension if installed.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     *    Whether or not an uninstall happened.
     */
    function disableExtension($extension)
    {
        $version = $this->getVersion($extension);
        if($this->isEnabled($extension)){
            $this->disable($extension);
        }
        if ($this->isInstalled($extension)) {
            $this->uninstall($extension, $version);
            return true;
        }
        return false;
    }

    function disable($extension){
        $this->removeIniDefinition($extension);
        $this->alternativeDisable($extension);
    }

    /**
     * Replace and remove all directives of the .so file for the given extension within the php.ini file.
     *
     * @param $extension
     *    The extension key name.
     */
    private function removeIniDefinition($extension)
    {
        $phpIniPath = $this->getPhpIniPath();
        $alias = $this->getExtensionAlias($extension);
        $phpIniFile = $this->files->get($phpIniPath);
        $phpIniFile = preg_replace('/;?(zend_extension|extension)\=".*' . $alias . '.so"/', '', $phpIniFile);
        $this->peclCustom->saveIniFile($phpIniPath, $phpIniFile);
        output("\t$extension successfully disabled");
    }

    /**
     * Because some extensions install others, like apcu_bc, the default disable method will not be sufficient.
     * In that case use this method to for example add additional dependencies for the specific disable. For example
     * apcu_bc installs apcu and apc both will need to be removed from the php.ini file. The default would only remove
     * apcu.so so we define apc.so here as alternative.
     *
     * @param $extension
     * @return string
     */
    private function alternativeDisable($extension)
    {
        switch ($extension) {
            case self::APCU_BC_EXTENSION:
                $this->disable(self::APCU_EXTENSION);
                break;
            default:
                break;
        }
    }

    /**
     * Because some extensions install others, like apcu_bc, the default uninstall method will not be sufficient.
     * In that case use this method to for example add additional dependencies for the specific uninstall. For example
     * apcu_bc installs apcu and apc both will need to be uninstalled from pecl. The default would only uninstall
     * apcu.so so we define apc.so here as alternative.
     *
     * @param $extension
     * @return string
     */
    private function alternativeUninstall($extension)
    {
        switch ($extension) {
            case self::APCU_BC_EXTENSION:
                $version = $this->getVersion($extension);
                $this->uninstall(self::APCU_EXTENSION, $version);
                break;
            default:
                break;
        }
    }

    /**
     * Uninstall a single extension.
     *
     * @param $extension
     *    The extension key name.
     * @param null $version
     */
    private function uninstall($extension, $version = null)
    {
        if ($version === null || $version === false) {
            $this->cli->passthru("pecl uninstall $extension");
        } else {
            $this->cli->passthru("pecl uninstall $extension-$version");
        }

        $this->alternativeUninstall($extension);
    }

    /**
     * Update the default PECL channel.
     */
    function updatePeclChannel()
    {
        info('[PECL] Updating PECL channel: pecl.php.net');
        $this->cli->runAsUser('pecl channel-update pecl.php.net');
    }

    /**
     * @inheritdoc
     */
    function isInstalled($extension)
    {
        return strpos($this->cli->runAsUser('pecl list | grep ' . $extension), $extension) !== false;
    }

    /**
     * Because some extensions install others, like apcu_bc, the default install method will not be sufficient.
     * In that case use this method to for example add additional dependencies for the specific install. For example
     * apcu_bc installs apcu and apc both will need to be linked within the php.ini file. The default would only link
     * apcu.so so we define apc.so here as alternative.
     *
     * @param $extension
     *    The extension key name.
     * @param $phpIniFile
     * @return string
     */
    private function alternativeInstall($extension, $phpIniFile)
    {
        switch ($extension) {
            case self::APCU_BC_EXTENSION:
                return $this->replaceIniDefinition(self::APCU_EXTENSION, $phpIniFile);
            default:
                return $phpIniFile;
        }
    }

    /**
     * Replace the .so directive within the php.ini file. Initial .so directive will be set by
     * 'pecl install {extension}'. Replacing will prevent duplicate entries.
     *
     * @param $extension
     *    The extension key name.
     * @param $phpIniFile
     *
     * @return string
     */
    private function replaceIniDefinition($extension, $phpIniFile)
    {
        $alias = $this->getExtensionAlias($extension);
        $type = $this->getExtensionType($extension);
        $phpIniFile = preg_replace("/(zend_extension|extension)\=\"(.*$alias.so)\"/", '', $phpIniFile);

        return "$type=\"$alias.so\"\n" . $phpIniFile;
    }

    /**
     * Whether or not this extension should be enabled by default. Is set by setting the
     * 'default' key to true within the extensions configuration.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     */
    private function isDefaultExtension($extension)
    {
        if (array_key_exists('default', self::EXTENSIONS[$extension])) {
            return false;
        } elseif (array_key_exists('default', self::EXTENSIONS[$extension]) === false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    protected function getExtensionAlias($extension)
    {
        switch ($extension) {
            case self::APCU_BC_EXTENSION:
                return self::APCU_BC_ALIAS;
            default:
                return $extension;
        }
    }

    /**
     * Get the version of the extension supported by the current PHP version.
     *
     * @param $extension
     *    The extension key name.
     * @return null
     */
    private function getVersion($extension)
    {
        $phpVersion = $this->getPhpVersion();
        if (array_key_exists($phpVersion, self::EXTENSIONS[$extension])) {
            return self::EXTENSIONS[$extension][$phpVersion];
        }
        return null;
    }

    /**
     * Pecl sometimes adds a directive twice. This causes the PHP installation to see the extension as
     * installed. However the seconds directive is seen as faulty which causes the PHP installation to
     * fail. This van be solved by checking if the directive exists twice. If it does valet-plus will
     * deem the module as "disabled" and replace the existing directives with a single one.
     *
     * @param $extension
     * @return bool
     */
    private function isEnabledCorrectly($extension){
        $phpIniPath = $this->getPhpIniPath();
        $phpIniFile = $this->files->get($phpIniPath);
        $type = $this->getExtensionType($extension);
        $alias = $this->getExtensionAlias($extension);
        preg_match_all("/$type=\"$alias.so\"/m", $phpIniFile, $matches);
        $alternativeEnabledCorrectly = $this->isAlternativeEnabledCorrectly($extension);
        $isEnabledCorrectly = $alternativeEnabledCorrectly && (is_array($matches) && count($matches) === 1?count($matches[0]) === 1:false);
        return $isEnabledCorrectly;
    }

    /**
     * Alternative case for checking directives of modules that depend on other modules.
     *
     * @param $extension
     * @return bool
     */
    private function isAlternativeEnabledCorrectly($extension){
        switch ($extension) {
            case self::APCU_BC_EXTENSION:
                return $this->isEnabledCorrectly(self::APCU_EXTENSION);
            default:
                return true;
        }
    }
}