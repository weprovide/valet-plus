<?php

namespace Squire;

class Squire
{
    var $cli, $files;

    var $squireBin = '/usr/local/bin/squire';

    /**
     * Create a new Squire instance.
     *
     * @param  CommandLine  $cli
     * @param  Filesystem  $files
     */
    function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * Symlink the Squire Bash script into the user's local bin.
     *
     * @return void
     */
    function symlinkToUsersBin()
    {
        $this->cli->quietlyAsUser('rm '.$this->squireBin);

        $this->cli->runAsUser('ln -s '.realpath(__DIR__.'/../../squire').' '.$this->squireBin);
    }

    /**
     * Get the paths to all of the Squire extensions.
     *
     * @return array
     */
    function extensions()
    {
        if (! $this->files->isDir(SQUIRE_HOME_PATH.'/Extensions')) {
            return [];
        }

        return collect($this->files->scandir(SQUIRE_HOME_PATH.'/Extensions'))
                    ->reject(function ($file) {
                        return is_dir($file);
                    })
                    ->map(function ($file) {
                        return SQUIRE_HOME_PATH.'/Extensions/'.$file;
                    })
                    ->values()->all();
    }

    /**
     * Determine if this is the latest version of Squire.
     *
     * @param  string  $currentVersion
     * @return bool
     */
    function onLatestVersion($currentVersion)
    {
        $response = \Httpful\Request::get('https://api.github.com/repos/weprovide/squire/releases/latest')->send();

        return version_compare($currentVersion, $response->body->tag_name, '>=');
    }
}
