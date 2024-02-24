<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use Valet\Brew;
use Valet\CommandLine;
use Valet\Configuration;
use Valet\Filesystem;

use function Valet\info;

/**
 * We name this class RedisService to avoid class collision when `shivammathur/extensions/redis@8.1` is installed.
 */
class RedisService extends AbstractService
{
    /** @var string */
    protected const SERVICE_NAME = 'redis';

    /** @var RedisPhpExtension */
    protected $redisPhpExtension;

    public function __construct(
        Configuration $configuration,
        Brew $brew,
        Filesystem $files,
        CommandLine $cli,
        RedisPhpExtension $redisPhpExtension
    ) {
        parent::__construct($configuration, $brew, $files, $cli);
        $this->redisPhpExtension = $redisPhpExtension;
    }

    /**
     * @inheritdoc
     */
    public function install(): void
    {
        $this->brew->ensureInstalled(static::SERVICE_NAME);
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

        $this->redisPhpExtension->uninstall();
        $this->brew->stopService(static::SERVICE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function restart(): void
    {
        if (!$this->installed() || !$this->isEnabled()) {
            return;
        }

        $this->redisPhpExtension->install();
        $this->brew->restartService(static::SERVICE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(): void
    {
        $this->stop();
        $this->removeEnabled();
        $this->brew->uninstallFormula(static::SERVICE_NAME);

        $this->files->unlink(BREW_PREFIX . '/var/log/redis.log');
    }
}
