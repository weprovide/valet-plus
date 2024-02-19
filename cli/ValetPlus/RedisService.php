<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use function Valet\info;

/**
 * We name this class RedisService to avoid class collision when `shivammathur/extensions/redis@8.1` is installed.
 */
class RedisService extends AbstractService
{
    /** @var string */
    protected const SERVICE_NAME = 'redis';

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
