<?php

namespace Valet;

use Exception;
use ValetDriver;

class DevTools
{
    const WP_CLI_TOOL = 'wp-cli';
    const PV_TOOL = 'pv';
    const GEOIP_TOOL = 'geoip';
    const ZLIB_TOOL = 'zlib';
    const JQ = 'jq';
    const LIBYAML = 'libyaml';

    const SUPPORTED_TOOLS = [
        self::WP_CLI_TOOL,
        self::PV_TOOL,
        self::GEOIP_TOOL,
        self::ZLIB_TOOL,
        self::JQ,
        self::LIBYAML
    ];

    public $brew;
    public $cli;
    public $files;
    public $configuration;
    public $site;
    public $mysql;
    /**
     * @var Architecture
     */
    private $architecture;

    /**
     * Create a new Nginx instance.
     *
     * @param Architecture $architecture
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Configuration $configuration
     * @param Site $site
     * @param Mysql $mysql
     */
    public function __construct(
        Architecture $architecture,
        Brew $brew,
        CommandLine $cli,
        Filesystem $files,
        Configuration $configuration,
        Site $site,
        Mysql $mysql
    ) {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->site = $site;
        $this->files = $files;
        $this->configuration = $configuration;
        $this->mysql = $mysql;
        $this->architecture = $architecture;
    }

    /**
     * Install development tools using brew.
     *
     * @return void
     */
    public function install()
    {
        info('[devtools] Installing tools');

        foreach (self::SUPPORTED_TOOLS as $tool) {
            if ($this->brew->installed($tool)) {
                output("\t" . $tool . ' already installed, skipping...');
            } else {
                $this->brew->ensureInstalled($tool, []);
            }
        }
    }

    /**
     * Uninstall development tools using brew.
     *
     * @return void
     */
    public function uninstall()
    {
        info('[devtools] Uninstalling tools');

        foreach (self::SUPPORTED_TOOLS as $tool) {
            if (!$this->brew->installed($tool)) {
                info('[devtools] ' . $tool . ' already uninstalled');
            } else {
                $this->brew->ensureUninstalled($tool, ['--force']);
            }
        }
    }

    public function sshkey()
    {
        $this->cli->passthru('pbcopy < ~/.ssh/id_rsa.pub');
        info('Copied ssh key to your clipboard');
    }

    public function phpstorm()
    {
        info('Opening PHPstorm');

        $this->cli->runAsUser('open -a PhpStorm ./');
    }

    public function sourcetree()
    {
        info('Opening SourceTree');
        $this->cli->runAsUser('open -a SourceTree ./');
    }

    public function vscode()
    {
        info('Opening Visual Studio Code');
        $command = false;

        if ($this->files->exists($this->architecture->getBrewPath() . '/bin/code')) {
            $command = $this->architecture->getBrewPath() . '/bin/code';
        }

        if ($this->files->exists($this->architecture->getBrewPath() . '/bin/vscode')) {
            $command = $this->architecture->getBrewPath() . '/bin/vscode';
        }

        if (!$command) {
            throw new Exception($this->architecture->getBrewPath() . '/bin/code command not found. Please install it.');
        }

        $output = $this->cli->runAsUser($command . ' $(git rev-parse --show-toplevel)');

        if (strpos($output, 'fatal: Not a git repository') !== false) {
            throw new Exception('Could not find git directory');
        }
    }

    public function tower()
    {
        info('Opening git tower');
        if (!$this->files->exists('/Applications/Tower.app/Contents/MacOS/gittower')) {
            throw new Exception('gittower command not found. Please install gittower by following the instructions provided here: https://www.git-tower.com/help/mac/integration/cli-tool');
        }

        $output = $this->cli->runAsUser('/Applications/Tower.app/Contents/MacOS/gittower $(git rev-parse --show-toplevel)');

        if (strpos($output, 'fatal: Not a git repository') !== false) {
            throw new Exception('Could not find git directory');
        }
    }

    public function configure()
    {
        require realpath(__DIR__ . '/../drivers/require.php');

        $driver = ValetDriver::assign(getcwd(), basename(getcwd()), '/');

        $secured = $this->site->secured();
        $domain = $this->site->host(getcwd()) . '.' . $this->configuration->read()['domain'];
        $isSecure = in_array($domain, $secured);
        $url = ($isSecure ? 'https://' : 'http://') . $domain;

        if (method_exists($driver, 'configure')) {
            return $driver->configure($this, $url);
        }

        info('No configuration settings found.');
    }
}
