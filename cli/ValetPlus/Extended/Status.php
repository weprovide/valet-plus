<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus\Extended;

use Valet\Brew;
use Valet\CommandLine;
use Valet\Configuration;
use Valet\Filesystem;
use Valet\Status as ValetStatus;
use WeProvide\ValetPlus\Mailhog;
use WeProvide\ValetPlus\Mysql;
use WeProvide\ValetPlus\Rabbitmq;
use WeProvide\ValetPlus\Redis;
use WeProvide\ValetPlus\Varnish;

class Status extends ValetStatus
{
    /** @var Mysql */
    protected $mysql;
    /** @var Mailhog */
    protected $mailhog;
    /** @var Varnish */
    protected $varnish;
    /** @var Redis */
    protected $redis;
    /** @var Rabbitmq */
    protected $rabbitmq;

    /**
     * @param Configuration $config
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Mysql $mysql
     * @param Mailhog $mailhog
     * @param Varnish $varnish
     * @param Redis $redis
     * @param Rabbitmq $rabbitmq
     */
    public function __construct(
        Configuration $config,
        Brew $brew,
        CommandLine $cli,
        Filesystem $files,
        Mysql $mysql,
        Mailhog $mailhog,
        Varnish $varnish,
        Redis $redis,
        Rabbitmq $rabbitmq
    ) {
        parent::__construct($config, $brew, $cli, $files);

        $this->mysql    = $mysql;
        $this->mailhog  = $mailhog;
        $this->varnish  = $varnish;
        $this->redis    = $redis;
        $this->rabbitmq = $rabbitmq;
    }

    /**
     * Returns list of Laravel Valet and ValetPlus checks.
     *
     * @return array
     */
    public function checks(): array
    {
        $checks = parent::checks();

        $mysqlVersion = $this->mysql->installedVersion();

        $checks[] = [
            'description' => '[Valet+] Is Mysql (' . $mysqlVersion . ') installed?',
            'check'       => function () {
                return $this->mysql->installedVersion();
            },
            'debug'       => 'Run `composer require weprovide/valet-plus` and `valet-plus install`.'
        ];
        $checks[] = [
            'description' => '[Valet+] Is Mailhog installed?',
            'check'       => function () {
                return $this->mailhog->installed();
            },
            'debug'       => 'Run `composer require weprovide/valet-plus` and `valet-plus install`.'
        ];

        if ($this->varnish->installed() || $this->varnish->isEnabled()) {
            $checks[] = [
                'description' => '[Valet+] Is Varnish installed?',
                'check'       => function () {
                    return $this->varnish->installed() && $this->varnish->isEnabled();
                },
                'debug'       => 'Varnish is installed but not enabled, you might run `valet-plus varnish on`.'
            ];
            //todo; actually test something?
        }
        if ($this->redis->installed() || $this->redis->isEnabled()) {
            $checks[] = [
                'description' => '[Valet+] Is Redis installed?',
                'check'       => function () {
                    return $this->redis->installed() && $this->redis->isEnabled();
                },
                'debug'       => 'Redis is installed but not enabled, you might run `valet-plus redis on`.'
            ];
            //todo; actually test something?
        }
        if ($this->rabbitmq->installed() || $this->rabbitmq->isEnabled()) {
            $checks[] = [
                'description' => '[Valet+] Is Rabbitmq installed?',
                'check'       => function () {
                    return $this->rabbitmq->installed() && $this->rabbitmq->isEnabled();
                },
                'debug'       => 'Rabbitmq is installed but not enabled, you might run `valet-plus rabbitmq on`.'
            ];
            //todo; actually test something?
        }

        return $checks;
    }
}
