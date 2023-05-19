<?php

declare(strict_types=1);

namespace WeProvide\ValetPlus;

use function Valet\info;
use function Valet\user;

class Redis extends AbstractService
{
    /** @var string */
    const SERVICE_NAME = 'redis';

    /** @var string */
    const NGINX_CONFIGURATION_STUB = __DIR__ . '/../stubs/redis.conf';
    /** @var string */
    const NGINX_CONFIGURATION_PATH = VALET_HOME_PATH . '/Nginx/redis.conf';

    /**
     * @inheritdoc
     */
    public function install(): void
    {
        $this->brew->ensureInstalled(static::SERVICE_NAME);
        $this->installConfiguration();
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
        // @todo: actually remove application and config files
    }

    /**
     * Install configuration
     */
    public function installConfiguration()
    {
        if ($this->installed()) {
            info('Installing nginx configuration...');
            $this->files->putAsUser(
                static::NGINX_CONFIGURATION_PATH,
                str_replace(
                    ['VALET_USER', 'VALET_HOME_PATH', 'BREW_PATH'],
                    [user(), VALET_HOME_PATH, BREW_PREFIX],
                    $this->files->get(static::NGINX_CONFIGURATION_STUB)
                )
            );
        }
    }
}
