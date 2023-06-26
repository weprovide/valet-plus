<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use function Valet\info;

class Redis extends AbstractService
{
    /** @var string */
    const SERVICE_NAME = 'redis';

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
            info("Redis not installed or not enabled");

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
    }
}