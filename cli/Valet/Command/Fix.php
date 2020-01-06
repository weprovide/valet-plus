<?php

declare(strict_types=1);

namespace Valet\Command;

use Valet\Pecl;
use Valet\PhpFpm;

final class Fix
{
    /**
     * @var \Valet\PhpFpm $php_fpm
     */
    private $php_fpm;

    /**
     * @var \Valet\Pecl $pecl
     */
    private $pecl;

    public function __construct(PhpFpm $php_fpm, Pecl $pecl)
    {
        $this->php_fpm = $php_fpm;
        $this->pecl = $pecl;
    }

    public function __invoke($reinstall)
    {
        if (file_exists($_SERVER['HOME'] . '/.my.cnf')) {
            warning(
                'You have an .my.cnf file in your home directory. This can affect the mysql installation negatively.'
            );
        }

        $this->php_fpm->fix($reinstall);
        $this->pecl->fix();
    }
}
