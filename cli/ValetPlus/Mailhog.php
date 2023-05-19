<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use Valet\Brew;
use Valet\CommandLine;
use Valet\Configuration;
use Valet\Filesystem;
use function Valet\info;

class Mailhog extends AbstractService
{
    /** @var string */
    const SERVICE_NAME = 'mailhog';

    /** @var string */
    const NGINX_CONFIGURATION_STUB = __DIR__ . '/../stubs/mailhog.conf';
    /** @var string */
    const NGINX_CONFIGURATION_PATH = VALET_HOME_PATH . '/Nginx/mailhog.conf';

    /** @var Brew */
    protected $brew;
    /** @var CommandLine */
    protected $cli;
    /** @var Filesystem */
    protected $files;

    /**
     * @param Configuration $configuration
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     */
    public function __construct(
        Configuration $configuration,
        Brew          $brew,
        CommandLine   $cli,
        Filesystem    $files
    ) {
        parent::__construct($configuration);

        $this->brew  = $brew;
        $this->cli   = $cli;
        $this->files = $files;
    }

    /**
     * Install mailhog and configuration the TLD to listen to.
     *
     * @param string $tld
     * @throws \JsonException
     */
    public function install(string $tld = 'test'): void
    {
        $this->brew->ensureInstalled(static::SERVICE_NAME);
        $this->updateDomain($tld);
        $this->setEnabled(static::STATE_ENABLED);
        $this->restart();
    }

    /**
     * @inheritdoc
     */
    public function installed(): mixed
    {
        return $this->brew->installed(static::SERVICE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function stop(): void
    {
        if (!$this->installed()) {
            return;
        }

        $this->brew->stopService(static::SERVICE_NAME);
        $this->cli->quietlyAsUser('brew services stop ' . static::SERVICE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function restart(): void
    {
        if (!$this->installed() || !$this->isEnabled()) {
            info("Mailhog not installed or not enabled");

            return;
        }

        $this->brew->restartService(static::SERVICE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(): void
    {
        $this->stop();
        // @todo: actually remove application and config files
    }

    /**
     * Set the domain (TLD) to use.
     *
     * @param $domain
     */
    public function updateDomain($domain)
    {
        if ($this->installed()) {
            $this->files->putAsUser(
                static::NGINX_CONFIGURATION_PATH,
                str_replace(
                    ['VALET_DOMAIN'],
                    [$domain],
                    $this->files->get(static::NGINX_CONFIGURATION_STUB)
                )
            );
        }
    }
}
