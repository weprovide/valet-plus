<?php

namespace Valet;

use Exception;
use DomainException;

abstract class AbstractPecl
{
    // Extension types.
    const NORMAL_EXTENSION_TYPE = 'extension';
    const ZEND_EXTENSION_TYPE = 'zend_extension';

    /**
     * Shared functionality example:
     *
     * Shared functionality can be used for both custom and PECL managed .so files. For example the 'default' key
     * allows one to disable the module by default. This is useful for modules that enable through on/off commands,
     * like ioncube and xdebug.
     *
     * @formatter:off
     *
     * 'extension_key_name' => [
     *    'default' => false
     * ]
     *
     * @formatter:on
     **/
    const EXTENSIONS = [

    ];

    var $cli, $files;

    /**
     * Create a new PECL instance.
     */
    function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * Get the extension type: zend_extension or extension for the custom extension.
     *
     * @param $extension
     *    The extension key name.
     * @return mixed
     */
    protected function getExtensionType($extension)
    {
        if (array_key_exists('extension_type', $this::EXTENSIONS[$extension])) {
            return $this::EXTENSIONS[$extension]['extension_type'];
        }
        throw new DomainException('extension_type key is required for PECL packages');
    }

    /**
     * Get the php.ini file path from the PECL config.
     *
     * @return mixed
     */
    public function getPhpIniPath()
    {
        return str_replace("\n", '', $this->cli->runAsUser('pecl config-get php_ini'));
    }

    /**
     * Get the current PHP version from the PECL config.
     *
     * @return string
     *    The php version as string: 5.6, 7.0, 7.1, 7.2
     */
    protected function getPhpVersion()
    {
        $version = $this->cli->runAsUser('pecl version | grep PHP');
        $version = str_replace('PHP Version:', '', $version);
        $version = str_replace(' ', '', $version);
        $version = substr($version, 0, 3);
        return $version;
    }

    /**
     * Check if the extension is enabled within the php installation.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     *   True if installed, false if not installed.
     */
    public function isEnabled($extension)
    {
        $alias = $this->getExtensionAlias($extension);
        $extensions = explode("\n", $this->cli->runAsUser("php -m | grep $alias"));
        return in_array($alias, $extensions);
    }

    /**
     * Get the extension directory from the PECL config.
     *
     * @return mixed
     */
    public function getExtensionDirectory()
    {
        return str_replace("\n", '', $this->cli->runAsUser('pecl config-get ext_dir'));
    }

    /**
     * Uninstall all extensions defined in EXTENSIONS.
     */
    function uninstallExtensions()
    {
        throw new \Exception(__METHOD__.' not implemented!');
    }

    /**
     * Install all extensions defined in EXTENSIONS.
     *
     * @param bool $onlyDefaults
     * @throws Exception if not overridden but used.
     */
    public function installExtensions($onlyDefaults = true){
        throw new \Exception(__METHOD__.' not implemented!');
    }

    /**
     * Check if the extension is installed.
     *
     * @param $extension
     *    The extension key name.
     * @return bool True if installed, false if not installed.
     * True if installed, false if not installed.
     * @throws Exception if not overridden but used.
     */
    protected function isInstalled($extension)
    {
        throw new \Exception(__METHOD__.' not implemented!');
    }

    /**
     * Get the extension alias for the extension. Should return the alias of the .so file without the .so extension.
     * E.G: apcu, apc, xdebug, geoip, etc...
     *
     * @param $extension
     *    The extension key name.
     * @return string
     * @throws Exception if not overridden but used.
     */
    protected function getExtensionAlias($extension)
    {
        throw new \Exception(__METHOD__.' not implemented!');
    }
}