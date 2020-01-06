<?php

declare(strict_types=1);

namespace Valet\Command;

use Valet\Configuration;
use Valet\DnsMasq;
use Valet\Elasticsearch;
use Valet\Mailhog;
use Valet\Nginx;
use Valet\PhpFpm;
use Valet\Site;

final class Domain
{
    /**
     * @var \Valet\Configuration $configuration
     */
    private $configuration;

    /**
     * @var \Valet\Mailhog $mailhog
     */
    private $mailhog;

    /**
     * @var \Valet\Elasticsearch $elasticsearch
     */
    private $elasticsearch;

    /**
     * @var \Valet\DnsMasq $dns_masq
     */
    private $dns_masq;

    /**
     * @var \Valet\Site $site
     */
    private $site;

    /**
     * @var \Valet\PhpFpm $php_fpm
     */
    private $php_fpm;

    /**
     * @var \Valet\Nginx $nginx
     */
    private $nginx;

    public function __construct(
        Configuration $configuration,
        Mailhog $mailhog,
        Elasticsearch $elasticsearch,
        DnsMasq $dns_masq,
        Site $site,
        PhpFpm $php_fpm,
        Nginx $nginx
    ) {
        $this->configuration = $configuration;
        $this->mailhog = $mailhog;
        $this->elasticsearch = $elasticsearch;
        $this->dns_masq = $dns_masq;
        $this->site = $site;
        $this->php_fpm = $php_fpm;
        $this->nginx = $nginx;
    }

    public function __invoke($domain = null)
    {
        if ($domain === null) {
            return info($this->configuration->read()['domain']);
        }

        $this->mailhog->updateDomain($domain);
        $this->elasticsearch->updateDomain($domain);

        $this->dns_masq->updateDomain(
            $oldDomain = $this->configuration->read()['domain'],
            $domain = trim($domain, '.')
        );

        $this->configuration->updateKey('domain', $domain);

        $this->site->resecureForNewDomain($oldDomain, $domain);
        $this->php_fpm->restart();
        $this->nginx->restart();

        info('Your Valet domain has been updated to [' . $domain . '].');
    }

}
