<?php

namespace Valet;

use Exception;
use DomainException;

class Pecl
{

    // Extensions.
    const XDEBUG_EXTENSION = 'xdebug';
    const APCU_EXTENSION = 'apcu';
    const APCU_BC_EXTENSION = 'apcu_bc';
    const GEOIP_EXTENSION = 'geoip';
    const IONCUBE_LOADER_EXTENSION = 'ioncube_loader_dar';

    // File extensions.
    const TAR_GZ_FILE_EXTENSION = '.tar.gz';

    // Extension aliases.
    const APCU_BC_ALIAS = 'apc';

    // Extension types.
    const NORMAL_EXTENSION_TYPE = 'extension';
    const ZEND_EXTENSION_TYPE = 'zend_extension';

    /**
     * Supported pecl extensions for the pecl manager. There are 2 types of extensions supported: zend_extensions and
     * extensions. Not all extension are available through PECL. Therefore there are also 2 installation methods: pecl
     * and custom.
     *
     * PECL example:
     *
     * The PECL extensions sometimes differ in version per PHP version. For example xDebug can only be installed on
     * PHP 5.6 using the 2.2.7 version. In such a case one would define the version within the array as key. The value
     * would be 2.2.7 which is the version to be installed. See example below:
     *
     * @formatter:off
     *
     * 'extension_key_name' => [
     *    '5.6' => '2.2.7'
     * ]
     *
     * @formatter:on
     *
     * CUSTOM example:
     *
     * Packages that are not available through PECL can be downloaded using the custom method. One needs to define the
     * 'custom' key with value true so the manager knows this package is custom and needs to be downloaded. Then every
     * PHP version requires a download link so the manager can download the .so file for the active PHP version.
     *
     * .so files can be packages by different file extensions. Use the file_extension key to set the file extension
     * of the downloaded file.
     *
     * Because packages files can contain a directory with a different name the manager will be unable to find the
     * unpackages folder. Set packaged_directory so that it equals the folder name after unpackaging the folder.
     *
     * Downloaded extensions can either be of type zend_extension or extension. Because the manager does not know
     * what kind of extension the downloaded.so file is one defines extension_type with one of the extension types.
     *
     * To check if the extension is active the manager uses extension_php_name. The manager check of php -m returns the
     * extension name.
     *
     * @formatter:off
     *
     * 'extension_key_name' => [
     *    '7.2' => 'https://example.com/packagename.extension',
     *    '7.1' => 'https://example.com/packagename.extension',
     *    '7.0' => 'https://example.com/packagename.extension',
     *    '5.6' => 'https://example.com/packagename.extension',
     *    'file_extension' => self::TAR_GZ_FILE_EXTENSION,
     *    'extension_type' => self::ZEND_EXTENSION_TYPE,
     *    'extension_php_name' => 'the ionCube PHP Loader',
     *    'custom' => true
     * ]
     *
     * @formatter:on
     *
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
     */
    const EXTENSIONS = [
        self::XDEBUG_EXTENSION => [
            '5.6' => '2.2.7',
            'default' => false
        ],
        self::APCU_BC_EXTENSION => [
            '5.6' => false
        ],
        self::APCU_EXTENSION => [
            '7.2' => false,
            '7.1' => false,
            '7.0' => false,
            '5.6' => '4.0.11'
        ],
        self::GEOIP_EXTENSION => [
            '7.2' => '1.1.1',
            '7.1' => '1.1.1',
            '7.0' => '1.1.1'
        ],
        self::IONCUBE_LOADER_EXTENSION => [
            '7.2' => 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_dar_x86-64.tar.gz',
            '7.1' => 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_dar_x86-64.tar.gz',
            '7.0' => 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_dar_x86-64.tar.gz',
            '5.6' => 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_dar_x86-64.tar.gz',
            'file_extension' => self::TAR_GZ_FILE_EXTENSION,
            'packaged_directory' => 'ioncube',
            'custom' => true,
            'default' => false,
            'extension_type' => self::ZEND_EXTENSION_TYPE,
            'extension_php_name' => 'the ionCube PHP Loader'
        ]
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
     * Get the extension directory from the PECL config.
     *
     * @return mixed
     */
    function getExtensionDirectory()
    {
        return str_replace("\n", '', $this->cli->runAsUser('pecl config-get ext_dir'));
    }

    /**
     * Get the php.ini file path from the PECL config.
     *
     * @return mixed
     */
    function getPhpIniPath()
    {
        return str_replace("\n", '', $this->cli->runAsUser('pecl config-get php_ini'));
    }

    /**
     * Get the current PHP version from the PECL config.
     *
     * @return string
     *    The php version as string: 5.6, 7.0, 7.1, 7.2
     */
    function getPhpVersion()
    {
        $version = $this->cli->runAsUser('pecl version | grep PHP');
        $version = str_replace('PHP Version:', '', $version);
        $version = str_replace(' ', '', $version);
        $version = substr($version, 0, 3);
        return $version;
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
     * Report to CLI whether or not the extension is installed.
     *
     * @param $extension
     *    The extension key name.
     */
    function isInstalled($extension)
    {
        if ($this->installed($extension)) {
            info("[PECL] $extension is installed");
        } else {
            info("[PECL] $extension is not installed");
        }
    }

    /**
     * Check if the extension is installed.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     *   True if installed, false if not installed.
     */
    function installed($extension)
    {
        if ($this->isCustomExtension($extension)) {
            // Because custom extensions are not managed by pecl check "php -m" for its existence.
            $extensionName = $this->getExtensionName($extension);
            return strpos($this->cli->runAsUser('php -m | grep \'' . $extensionName . '\''), $extensionName) !== false;
        } else {
            //@TODO: Evaluate if using php -m is more stable, package could be installed by PECL but not enabled in the php.ini file.
            return strpos($this->cli->runAsUser('pecl list | grep ' . $extension), $extension) !== false;
        }
    }

    /**
     * Install a single extension.
     *
     * @param $extension
     *    The extension key name.
     * @param null $version
     */
    private function install($extension, $version = null)
    {
        if ($version === null) {
            $result = $this->cli->runAsUser("pecl install $extension");
        } else {
            $result = $this->cli->runAsUser("pecl install $extension-$version");
        }

        $alias = $this->getExtensionAlias($extension);
        $phpIniPath = $this->getPhpIniPath();
        $phpIniFile = $this->files->get($phpIniPath);
        $phpIniFile = $this->replaceIniDefinition($alias, $phpIniFile, $result);
        $phpIniFile = $this->alternativeInstall($extension, $phpIniFile, $result);
        $this->saveIniFile($phpIniPath, $phpIniFile);
        output("$extension successfully installed");
    }

    /**
     * Install a single custom extension.
     *
     * @param $extension
     *    The extension key name.
     * @param $url
     */
    function customInstall($extension, $url)
    {

        // Get file name from url
        $urlSplit = explode('/', $url);
        $fileName = $urlSplit[count($urlSplit) - 1];

        // Check if .so is available
        $extensionDirectory = $this->getExtensionDirectory();
        $extensionAlias = $this->getExtensionAlias($extension);
        if ($this->files->exists($extensionDirectory . '/' . $extensionAlias) === false) {
            info("[PECL] $extension is not available from PECL, downloading from: $url");
            $this->downloadExtension($extension, $url, $fileName, $extensionAlias, $extensionDirectory);
        } else {
            info("[PECL] $extensionAlias found in $extensionDirectory skipping download..");
        }

        // Install php.ini directive.
        info("[PECL] Adding $extensionAlias to php.ini...");
        $extensionType = $this->getExtensionType($extension);
        $phpIniPath = $this->getPhpIniPath();
        $directive = $extensionType . '="' . $extensionAlias . '"';
        $phpIniFile = $this->files->get($phpIniPath);
        $this->saveIniFile($phpIniPath, $directive . "\n" . $phpIniFile);

        output("$extension successfully installed");
    }

    /**
     * Download and unpack a custom extension.
     *
     * @param $extension
     *    The extension key name.
     * @param $url
     *    The extension url
     * @param $fileName
     *    The file name of the packaged directory.
     * @param $extensionAlias
     *    The extension alias which equals the name of the .so file. E.G: xdebug.so
     * @param $extensionDirectory
     *    The directory where the .so file needs to be placed.
     */
    function downloadExtension($extension, $url, $fileName, $extensionAlias, $extensionDirectory)
    {
        $unpackagedDirectory = $this->getPackagedDirectory($extension);

        // Download and unzip
        $this->cli->passthru("cd /tmp && curl -O $url");

        // Unpackage the file using file extension.
        $fileExtension = $this->getFileExtension($extension);
        switch ($fileExtension) {
            case self::TAR_GZ_FILE_EXTENSION:
                info('[PECL] Unpackaging .tar.gz:');
                $this->cli->passthru("cd /tmp && tar -xvzf $fileName");
                break;
            default:
                throw new DomainException("File extension $fileExtension is not supported yet!");
        }

        // Search for extension file in unpackaged directory using the extension alias.
        $files = $this->files->scandir("/tmp/$unpackagedDirectory");
        if (in_array($extensionAlias, $files)) {
            info("[PECL] $extensionAlias was found, moving to extension directory: $extensionDirectory");
            $this->cli->runAsUser("cp /tmp/$unpackagedDirectory/$extensionAlias $extensionDirectory");
        } else {
            throw new DomainException("$extensionAlias could not be found!");
        }

        // Remove artifacts from /tmp folder.
        $this->cli->runAsUser("rm /tmp/$fileName");
        $this->cli->runAsUser("rm -r /tmp/$unpackagedDirectory/$extensionAlias $extensionDirectory");
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
        // Only call PECL uninstall if package is managed by PECL.
        if ($this->isCustomExtension($extension) === false) {
            if ($version === null || $version === false) {
                $this->cli->passthru("pecl uninstall $extension");
            } else {
                $this->cli->passthru("pecl uninstall $extension-$version");
            }
        }

        $this->removeIniDefinition($extension);
    }

    /**
     * Uninstall all extensions defined in EXTENSIONS.
     */
    function uninstallExtensions()
    {
        info("[PECL] Removing extensions");
        foreach (self::EXTENSIONS as $extension => $versions) {
            $this->uninstallExtension($extension);
        }
    }

    /**
     * Uninstall an extension if installed, by version.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     */
    function uninstallExtension($extension)
    {
        $version = $this->getVersion($extension);
        if ($this->installed($extension)) {
            $this->uninstall($extension, $version);
            return true;
        }
        return false;
    }

    /**
     * Install all extensions defined in EXTENSIONS.
     *
     * @param bool $onlyDefaults
     */
    function installExtensions($onlyDefaults = true)
    {
        info("[PECL] Installing extensions");
        foreach (self::EXTENSIONS as $extension => $versions) {
            if ($onlyDefaults && $this->isDefaultExtension($extension) === false) {
                continue;
            }

            $this->installExtension($extension);
        }
    }

    /**
     * Install a single extension if not already installed, default is true and has a version for this PHP version.
     *
     * If version is false the extension will never be installed for this PHP version.
     *
     * @param $extension
     *    The extension key name.
     * @return bool
     */
    function installExtension($extension)
    {
        $version = $this->getVersion($extension);
        $isCustom = $this->isCustomExtension($extension);

        if ($this->installed($extension)) {
            return false;
        }

        if ($isCustom) {
            $this->customInstall($extension, $version);
        } elseif ($isCustom === false && $version !== false) {
            $this->install($extension, $version);
        }

        return true;
    }

    /**
     * Because some extensions install others, like apcu_bc, the default install method will not be sufficient.
     * In that case use this method to for example add additional dependencies for the specific install. For example
     * apcu_bc install apcu and apc both will need to be linked within the php.ini file. The default would only link
     * apcu.so so we define apc.so here as alternative.
     *
     * @param $extension
     * @param $phpIniFile
     * @param $result
     * @return string
     */
    private function alternativeInstall($extension, $phpIniFile, $result)
    {
        switch ($extension) {
            case self::APCU_BC_EXTENSION:
                return $this->replaceIniDefinition($this->getExtensionAlias(self::APCU_EXTENSION), $phpIniFile, $result);
            default:
                return $phpIniFile;
        }
    }

    /**
     * Replace all definitions of the .so file to the given extension within the php.ini file.
     *
     * @param $extension
     *    The extension key name.
     */
    private function removeIniDefinition($extension)
    {
        $phpIniPath = $this->getPhpIniPath();
        info("[PECL] removing $extension from: $phpIniPath");
        $alias = $this->getExtensionAlias($extension);
        $phpIniFile = $this->files->get($phpIniPath);
        if ($this->isCustomExtension($extension)) {
            $phpIniFile = preg_replace('/;?(zend_extension|extension)\=".*' . $alias . '"/', '', $phpIniFile);
        } else {
            $phpIniFile = preg_replace('/;?(zend_extension|extension)\=".*' . $alias . '.so"/', '', $phpIniFile);
        }
        $this->saveIniFile($phpIniPath, $phpIniFile);
    }

    /**
     * Some extensions require to be loaded before others. Because the order is important within the php.ini file one
     * should always use this method before saving the php.ini file. This method makes sure that .so definitions within
     * the php.ini file are always ordered correctly.
     *
     * @param $phpIniPath
     *    The path to the php.ini file.
     * @param $phpIniFile
     *    The contents of the php.ini file.
     */
    private function saveIniFile($phpIniPath, $phpIniFile)
    {
        // Ioncube loader requires to be the first zend_extension loaded from the php.ini
        // before saving the ini file check if ioncube is enabled and move it to the top of the file.
        $ioncubeLoader = $this->getExtensionAlias(self::IONCUBE_LOADER_EXTENSION);
        if (preg_match('/(zend_extension|extension)\="(.*' . $ioncubeLoader . ')"/', $phpIniFile, $matches)) {
            $phpIniFile = preg_replace('/(zend_extension|extension)\="(.*' . $ioncubeLoader . ')"/', '', $phpIniFile);
            $phpIniFile = $matches[1] . '="' . $matches[2] . '"' . "\n" . $phpIniFile;
        }

        $this->files->putAsUser($phpIniPath, $phpIniFile);
    }

    /**
     * Replace the .so definition within the php.ini file. Initial .so definition will be set by
     * 'pecl install {extension}'. This needs to be overriden with the value returned by the install becuase the
     * extension_dir is not set within the php.ini file.
     *
     * Homebrew is working for a fix that should make the PECL directory PHP specific:
     *  https://github.com/Homebrew/homebrew-core/pull/26137
     *
     * @param $extension
     * @param $phpIniFile
     * @param $result
     * @return string
     */
    private function replaceIniDefinition($extension, $phpIniFile, $result)
    {
        if (!preg_match("/Installing '(.*$extension.so)'/", $result)) {
            throw new DomainException("Could not find installation path for: $extension\n\n$result");
        }

        if (strpos($result, "Error:")) {
            throw new DomainException("Installation path found, but installation failed:\n\n$result");
        }

        if (!preg_match('/(zend_extension|extension)\="(.*' . $extension . '.so)"/', $phpIniFile, $iniMatches)) {
            $phpIniPath = $this->getPhpIniPath();
            throw new DomainException("Could not find ini definition for: $extension in $phpIniPath");
        }

        $phpIniFile = preg_replace('/(zend_extension|extension)\="(.*' . $extension . '.so)"/', '', $phpIniFile);

        return $iniMatches[1] . '="' . $iniMatches[2] . '"'."\n". $phpIniFile;
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
     * Whether or not this extension is custom. Is set by settings the 'custom' key to true within the extensions
     * configuration. Custom extensions are not supported by PECL and will be downloaded.
     *
     * @param $extension
     * @return bool
     */
    private function isCustomExtension($extension)
    {
        if (array_key_exists('custom', self::EXTENSIONS[$extension])) {
            return true;
        } elseif (array_key_exists('custom', self::EXTENSIONS[$extension]) === false) {
            return false;
        } else {
            return false;
        }
    }

    /**
     * Get the extension alias for the extension.
     *
     * PECL managed extensions:
     * Should return the alias of the .so file without the .so extension. E.G: apcu, apc, xdebug
     *
     * CUSTOM managed extensions:
     * Should return the alias of the .so file with the .so extension. E.G: apcu.so, apc.so, xdebug.so
     *
     * @param $extension
     *    The extension key name.
     * @return string
     */
    private function getExtensionAlias($extension)
    {
        switch ($extension) {
            case self::APCU_BC_EXTENSION:
                return self::APCU_BC_ALIAS;
            case self::IONCUBE_LOADER_EXTENSION:
                return self::IONCUBE_LOADER_EXTENSION . '_' . $this->getPhpVersion() . '.so';
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
     * Get the file extension of the downloaded custom extension package.
     *
     * @param $extension
     *    The extension key name.
     * @return mixed
     */
    private function getFileExtension($extension)
    {
        if (array_key_exists('file_extension', self::EXTENSIONS[$extension])) {
            return self::EXTENSIONS[$extension]['file_extension'];
        }
        throw new DomainException('file_extension key is required for custom PECL packages');
    }

    /**
     * Get the directory within the packaged archive.
     *
     * @param $extension
     *    The extension key name.
     * @return mixed
     */
    private function getPackagedDirectory($extension)
    {
        if (array_key_exists('packaged_directory', self::EXTENSIONS[$extension])) {
            return self::EXTENSIONS[$extension]['packaged_directory'];
        }
        throw new DomainException('packaged_directory key is required for custom PECL packages');
    }

    /**
     * Get the extension type: zend_extension or extension for the custom extension.
     *
     * @param $extension
     *    The extension key name.
     * @return mixed
     */
    private function getExtensionType($extension)
    {
        if (array_key_exists('extension_type', self::EXTENSIONS[$extension])) {
            return self::EXTENSIONS[$extension]['extension_type'];
        }
        throw new DomainException('extension_type key is required for custom PECL packages');
    }

    /**
     * Get the extension name for the custom extension. Used for checking php -m output.
     *
     * @param $extension
     *    The extension key name.
     * @return mixed
     */
    private function getExtensionName($extension)
    {
        if (array_key_exists('extension_php_name', self::EXTENSIONS[$extension])) {
            return self::EXTENSIONS[$extension]['extension_php_name'];
        }
        throw new DomainException('extension_php_name key is required for custom PECL packages');
    }

}