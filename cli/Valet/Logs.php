<?php
namespace Valet;

class Logs
{
    function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    public function open($file)
    {
        $this->cli->quietly('open ' . $file);
    }
}
