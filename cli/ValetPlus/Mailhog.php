<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use Illuminate\Container\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Valet\Brew;
use Valet\CommandLine;
use Valet\Configuration;
use Valet\Filesystem;
use WeProvide\ValetPlus\Event\DataEvent;
use function Valet\info;

class Mailhog extends AbstractService
{
    /** @var string */
    const SERVICE_NAME = 'mailhog';

    /** @var string */
    const PHP_CONFIGURATION_STUB = __DIR__ . '/../stubs/mailhog.ini';
    /** @var string */
    const NGINX_CONFIGURATION_STUB = __DIR__ . '/../stubs/mailhog.conf';
    /** @var string */
    const NGINX_CONFIGURATION_PATH = VALET_HOME_PATH . '/Nginx/mailhog.conf';

    /** @var EventDispatcher */
    protected $eventDispatcher;

    /**
     * @param Configuration $configuration
     * @param Brew $brew
     * @param Filesystem $files
     * @param CommandLine $cli
     */
    public function __construct(
        Configuration $configuration,
        Brew          $brew,
        Filesystem    $files,
        CommandLine   $cli
    ) {
        parent::__construct($configuration, $brew, $files, $cli);

        $container             = Container::getInstance();
        $this->eventDispatcher = $container->get('event_dispatcher');
    }

    /**
     * Register events to listen to.
     */
    public function register()
    {
        $this->eventDispatcher->addListener('after_create_php_config', [$this, 'createPhpConfiguration']);
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
        $this->brew->uninstallFormula(static::SERVICE_NAME);
        $this->files->unlink(BREW_PREFIX . '/var/log/mailhog.log');
    }

    /**
     * Set the domain (TLD) to use.
     *
     * @param $domain
     */
    public function updateDomain($domain)
    {
        if ($this->installed()) {
            info('Updating mailhog domain...');
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

    /**
     * Create php ini files.
     *
     * @param DataEvent $event
     */
    public function createPhpConfiguration(DataEvent $event)
    {
        $this->files->putAsUser(
            $event->get('php_dir') . 'mailhog.ini',
            str_replace(
                ['BREW_PATH'],
                [BREW_PREFIX],
                $this->files->get(static::PHP_CONFIGURATION_STUB)
            )
        );
    }
}
