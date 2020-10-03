<?php
namespace Valet;

class Logs
{
    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    public function open($file)
    {
        $this->cli->quietly('open ' . $this->resolvePath($file));
    }

    public function exists($file)
    {
        $file = $this->resolvePath($file);

        return file_exists($file);
    }

    private function resolvePath($file)
    {
        return str_replace('$HOME', $_SERVER['HOME'], $file);
    }
}
