<?php

namespace Valet;

class Composer {
    private $cli;
    private $cachedPackages;

    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
        $this->cachedPackages = null;
    }

    function getPackages($directory)
    {
        if($this->cachedPackages === null) {
            $packages = explode(PHP_EOL, $this->cli->runCommand('composer show -d ' . $directory));
            $associations = [];

            foreach($packages as $package) {
                $parsed = $this->parsePackage($package);

                if($parsed !== null) {
                    $associations[$parsed['name']] = $parsed;
                }
            }

            $this->cachedPackages = $associations;
        }

        return $this->cachedPackages;
    }

    function getPackage($package, $directory)
    {
        $packages = $this->getPackages($directory);
        return isset($packages[$package]) ? $packages[$package] : null;
    }

    function isInstalled($package, $directory)
    {
        return isset($this->getPackages($directory)[$package]);
    }

    function parsePackage($package)
    {
        $data = array_values(array_filter(explode(' ', $package), function($bit) {
            return $bit;
        }));

        return ($data ? [
            'name' => $data[0],
            'version' => (isset($data[1]) ? $data[1] : null)
        ] : null);
    }
}