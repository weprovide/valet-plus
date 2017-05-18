<?php

namespace Valet;

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
        $tools = ['wp-cli', 'n98-magerun', 'n98-magerun2'];
        $currentVersion = $this->brew->linkedPhp();
        info('Installing developer tools...');

        foreach($tools as $tool) {
            if($this->brew->installed($tool)) {
                info($tool.' already installed');
            } else {
                $this->brew->ensureInstalled($tool);
            }
        }
    }
}
