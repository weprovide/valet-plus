<?php

namespace Valet;

use Exception;

class DevTools
{
    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;

    /**
     * Create a new Nginx instance.
     *
     * @param  Brew  $brew
     * @param  CommandLine  $cli
     * @param  Filesystem  $files
     * @param  Configuration  $configuration
     * @param  Site  $site
     * @return void
     */
    function __construct(Brew $brew, CommandLine $cli, Filesystem $files,
                         Configuration $configuration, Site $site)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->site = $site;
        $this->files = $files;
        $this->configuration = $configuration;
    }

    /**
     * Install the configuration files for Mysql.
     *
     * @return void
     */
    function install()
    {
        $tools = ['mailhog', 'wp-cli', 'n98-magerun', 'n98-magerun2', 'pv'];
        info('Installing developer tools...');

        foreach($tools as $tool) {
            if($this->brew->installed($tool)) {
                info($tool.' already installed');
            } else {
                $this->brew->ensureInstalled($tool);
            }
        }
    }

    function sshkey() {
        $this->cli->passthru('pbcopy < ~/.ssh/id_rsa.pub');
        info('Copied ssh key to your clipboard');
    }

    function phpstorm() {
        info('Opening PHPstorm...');
        $command = false;

        if($this->files->exists('/usr/local/bin/pstorm')) {
            $command = '/usr/local/bin/pstorm';
        }

        if($this->files->exists('/usr/local/bin/pstorm')) {
            $command = '/usr/local/bin/pstorm';
        }

        if(!$command) {
            throw new Exception('/usr/local/bin/pstorm command not found. Please install pstorm by opening PHPstorm and going to Tools -> Create command line launcher. When asked for the path enter: /usr/local/bin/pstorm');
        }

        $output = $this->cli->runAsUser($command.' $(git rev-parse --show-toplevel)');
        
        if(strpos($output, 'fatal: Not a git repository') !== false) {
            throw new Exception('Could not find git directory');
        }
    }

    function vscode() {
        info('Opening Visual Studio Code...');
        $command = false;

        if($this->files->exists('/usr/local/bin/code')) {
            $command = '/usr/local/bin/code';
        }

        if($this->files->exists('/usr/local/bin/vscode')) {
            $command = '/usr/local/bin/vscode';
        }

        if(!$command) {
            throw new Exception('/usr/local/bin/code command not found. Please install it.');
        }

        $output = $this->cli->runAsUser($command.' $(git rev-parse --show-toplevel)');
        
        if(strpos($output, 'fatal: Not a git repository') !== false) {
            throw new Exception('Could not find git directory');
        }
    }

    function tower() {
        info('Opening git tower...');      
        if(!$this->files->exists('/Applications/Tower.app/Contents/MacOS/gittower')) {
            throw new Exception('gittower command not found. Please install gittower by following the instructions provided here: https://www.git-tower.com/help/mac/integration/cli-tool');
        }

        $output = $this->cli->runAsUser('/Applications/Tower.app/Contents/MacOS/gittower $(git rev-parse --show-toplevel)');
        
        if(strpos($output, 'fatal: Not a git repository') !== false) {
            throw new Exception('Could not find git directory');
        }
    }
}
