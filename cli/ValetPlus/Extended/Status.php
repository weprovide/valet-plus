<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Valet\Brew;
use Valet\CommandLine;
use Valet\Configuration;
use Valet\Filesystem;
use Valet\Status as ValetStatus;
use WeProvide\ValetPlus\Mailhog;

class Status extends ValetStatus
{
    /** @var Mailhog */
    protected $mailhog;

    /**
     * @param Configuration $config
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Mailhog $mailhog
     */
    public function __construct(
        Configuration $config,
        Brew          $brew,
        CommandLine   $cli,
        Filesystem    $files,
        Mailhog       $mailhog
    ) {
        parent::__construct($config, $brew, $cli, $files);

        $this->mailhog = $mailhog;
    }

    /**
     * Returns list of Laravel Valet and ValetPlus checks.
     *
     * @return array
     */
    public function checks(): array
    {
        $checks = parent::checks();

        $checks[] = [
            'description' => '[Valet+] Is Mailhog installed?',
            'check'       => function () {
                return $this->mailhog->installed();
            },
            'debug'       => 'Run `composer require weprovide/valet-plus` and `valet install`.'
        ];

        return $checks;
    }
}
