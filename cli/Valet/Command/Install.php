<?php

declare(strict_types=1);

namespace Valet\Command;

use Valet\Binaries;
use Valet\Configuration;
use Valet\DevTools;
use Valet\DnsMasq;
use Valet\Elasticsearch;
use Valet\Mailhog;
use Valet\Mysql;
use Valet\Nginx;
use Valet\PhpFpm;
use Valet\RedisTool;
use Valet\Valet;

class Install
{
    /**
     * @var \Valet\Nginx $nginx
     */
    private $nginx;

    /**
     * @var \Valet\PhpFpm $php_fpm
     */
    private $php_fpm;

    /**
     * @var \Valet\Mysql $mysql
     */
    private $mysql;

    /**
     * @var \Valet\RedisTool $redis_tool
     */
    private $redis_tool;

    /**
     * @var \Valet\DevTools $dev_tools
     */
    private $dev_tools;

    /**
     * @var \Valet\Binaries $binaries
     */
    private $binaries;

    /**
     * @var \Valet\Configuration $configuration
     */
    private $configuration;

    /**
     * @var \Valet\DnsMasq $dns_masq
     */
    private $dns_masq;

    /**
     * @var \Valet\Mailhog $mailhog
     */
    private $mailhog;

    /**
     * @var \Valet\Elasticsearch $elasticsearch
     */
    private $elasticsearch;

    /**
     * @var \Valet\Valet $valet
     */
    private $valet;

    public function __construct(
        Nginx $nginx,
        PhpFpm $php_fpm,
        Mysql $mysql,
        RedisTool $redis_tool,
        DevTools $dev_tools,
        Binaries $binaries,
        Configuration $configuration,
        DnsMasq $dns_masq,
        Mailhog $mailhog,
        Elasticsearch $elasticsearch,
        Valet $valet
    ) {
        $this->nginx = $nginx;
        $this->php_fpm = $php_fpm;
        $this->mysql = $mysql;
        $this->redis_tool = $redis_tool;
        $this->dev_tools = $dev_tools;
        $this->binaries = $binaries;
        $this->configuration = $configuration;
        $this->dns_masq = $dns_masq;
        $this->mailhog = $mailhog;
        $this->elasticsearch = $elasticsearch;
        $this->valet = $valet;
    }

    public function __invoke($withMariadb)
    {
        $this->php_fpm->checkInstallation();

        $this->nginx->stop();
        $this->php_fpm->stop();
        $this->mysql->stop();
        $this->redis_tool->stop();
        $this->dev_tools->install();
        $this->binaries->installBinaries();

        $this->configuration->install();
        $domain = $this->nginx->install();
        $this->php_fpm->install();
        $this->dns_masq->install();
        $this->mysql->install($withMariadb ? 'mariadb' : 'mysql@5.7');
        $this->redis_tool->install();
        $this->mailhog->install();
        $this->nginx->restart();
        $this->valet->symlinkToUsersBin();
        $this->mysql->setRootPassword();

        $this->mailhog->updateDomain($domain);
        $this->elasticsearch->updateDomain($domain);

        output(PHP_EOL . '<info>Valet installed successfully!</info>');
    }
}
