<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use function Valet\info;

class Rabbitmq extends AbstractService
{
    /** @var string */
    protected const SERVICE_NAME = 'rabbitmq';

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
        $this->cli->quietlyAsUser('brew services stop ' . static::SERVICE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function restart(): void
    {
        if (!$this->installed() || !$this->isEnabled()) {
            info("Rabbitmq not installed or not enabled");

            return;
        }

        $this->brew->stopService(static::SERVICE_NAME);
        info("Starting " . static::SERVICE_NAME . "...");
        $this->cli->quietlyAsUser('brew services restart ' . static::SERVICE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(): void
    {
        $this->stop();
        $this->removeEnabled();
        $this->brew->uninstallFormula(static::SERVICE_NAME);

        if (file_exists(BREW_PREFIX . '/var/lib/rabbitmq')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/lib/rabbitmq');
        }
        if (file_exists(BREW_PREFIX . '/var/log/rabbitmq')) {
            $this->files->rmDirAndContents(BREW_PREFIX . '/var/log/rabbitmq');
        }
    }
}
